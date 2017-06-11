<?php
/*-------------------------------------------------------------+
| GP StreetImporter Record Handlers                            |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * Abstract class bundle common GP importer functions
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
abstract class CRM_Streetimport_GP_Handler_GPRecordHandler extends CRM_Streetimport_RecordHandler {

  /** activity type cache */
  protected $_manual_update_required_id = NULL;
  protected $_response_activity_id = NULL;
  protected $_update_activity_id = NULL;

  /**
   * look up contact id with CiviCRM ID
   */
  protected function getContactIDbyCiviCRMID($contact_id) {
    // TODO: use identity tracker!
    return $contact_id;
  }

  /**
   * look up contact id with external ID
   */
  protected function getContactIDbyExternalID($external_identifier) {
    if (empty($external_identifier)) return NULL;

    // look up contact via external_identifier
    // TODO: use identity tracker!
    $contacts = civicrm_api3('Contact', 'get', array(
      'external_identifier' => $external_identifier,
      'return'              => 'id'));
    if ($contacts['count'] == 1) {
      return $contacts['id'];
    } elseif ($contacts['count'] > 1) {
      // not unique? this shouldn't happen
      return NULL;
    } else {
      // NOT found
      return NULL;
    }
  }

  /**
   * disable a contact with everything that entails
   * @param $mode  on of 'erase', 'disabled', 'deceased'
   */
  public function disableContact($contact_id, $mode, $record) {
    switch ($mode) {
      case 'erase':
        // erase means anonymise and delete
        civicrm_api3('Contact', 'create', array(
          'id'         => $contact_id,
          'is_deleted' => 1));
        // FIXME: anonymisation not yet available
        $this->tagContact($contact_id, 'anonymise', $record);
        $this->cancelAllContracts($contact_id, 'XX02', $record);
        break;

      case 'disable':
        // disabled (stillgelegt) means deleted + tagged
        civicrm_api3('Contact', 'create', array(
          'id'         => $contact_id,
          'is_deleted' => 1));
        $this->tagContact($contact_id, 'inaktiv', $record);
        $this->cancelAllContracts($contact_id, 'XX02', $record);
        break;

      case 'deceased':
        // disabled (verstorben) means deceased + tagged
        civicrm_api3('Contact', 'create', array(
          'id'            => $contact_id,
          // 'is_deleted'  => 1, // Marco said (27.03.2017): don't delete right away
          'deceased_date' => $this->getDate($record),
          'is_deceased'   => 1));
        $this->tagContact($contact_id, 'inaktiv', $record);
        $this->cancelAllContracts($contact_id, 'XX13', $record);
        break;

      default:
        $this->logger->logFatal("DisableContact mode '{$mode}' not implemented!", $record);
        break;
    }
  }

  /**
   * create a new contract
   *
   * FIELDS: Vertragsnummer  Bankleitzahl  Kontonummer Bic Iban  Kontoinhaber  Bankinstitut  Einzugsstart  JahresBetrag  BuchungsBetrag  Einzugsintervall  EinzugsEndeDatum
   */
  public function createContract($contact_id, $record) {
    $config = CRM_Streetimport_Config::singleton();

    // validate parameters
    if (    empty($record['IBAN'])
         || empty($record['BIC'])
         || empty($record['JahresBetrag'])
         || empty($record['Einzugsintervall'])) {
      return $this->logger->logError("Couldn't create mandate, information incomplete.", $record);
    }

    // get start date
    $now = date('YmdHis');
    $mandate_start_date = date('YmdHis', strtotime($record['Einzugsstart']));
    if (empty($mandate_start_date) || $mandate_start_date < $now) {
      $mandate_start_date = $now;
    }

    // FIRST: compile and create SEPA mandate
    $annual_amount = $record['JahresBetrag'];
    $frequency = $record['Einzugsintervall'];
    $amount = number_format($annual_amount / $frequency, 2);
    $mandate_params = array(
      'type'                => 'RCUR',
      'iban'                => $record['IBAN'],
      'bic'                 => $record['BIC'],
      'amount'              => $amount,
      'contact_id'          => $contact_id,
      'currency'            => 'EUR',
      'frequency_unit'      => 'month',
      'cycle_day'           => $config->getNextCycleDay($mandate_start_date),
      'frequency_interval'  => (int) (12.0 / $frequency),
      'start_date'          => $mandate_start_date,
      'campaign_id'         => $this->getCampaignID($record),
      'financial_type_id'   => 3, // Membership Dues
      );
    if (!empty($record['EinzugsEndeDatum'])) {
      $mandate_params['end_date'] = date('YmdHis', strtotime($record['EinzugsEndeDatum']));
    }

    // create and reload mandate
    $mandate = civicrm_api3('SepaMandate', 'createfull', $mandate_params);
    $mandate = civicrm_api3('SepaMandate', 'getsingle', array('id' => $mandate['id']));

    // make sure the bank account exists
    $this->addBankAccount($contact_id, 'IBAN', $record['IBAN'], $record, array(
      'BIC'     => $record['BIC'],
      'country' => substr($record['IBAN'], 0, 2)));

    // NEXT: create membership
    $membership_annual        = $config->getGPCustomFieldKey('membership_annual');
    $membership_frequency     = $config->getGPCustomFieldKey('membership_frequency');
    $membership_rcontribution = $config->getGPCustomFieldKey('membership_recurring_contribution');

    $membership_params = array(
      'contact_id'              => $contact_id,
      'membership_type_id'      => $this->getMembershipTypeID($record),
      'member_since'            => $this->getDate($record),
      'start_date'              => $mandate_start_date,
      'campaign_id'             => $this->getCampaignID($record),
      $membership_annual        => number_format($annual_amount, 2),
      $membership_frequency     => $frequency,
      $membership_rcontribution => $mandate['entity_id']
      );
    error_log("Contract.create: " . json_encode($membership_params));
    $membership = civicrm_api3('Contract', 'create', $membership_params);
  }

  /**
   * Create a OOFF mandate
   */
  public function createOOFFMandate($contact_id, $record) {
    $config = CRM_Streetimport_Config::singleton();

    // validate parameters
    if (    empty($record['IBAN'])
         || empty($record['BIC'])
         || empty($record['BuchungsBetrag'])) {
      return $this->logger->logError("Couldn't create mandate, information incomplete.", $record);
    }

    // get start date
    $now = date('YmdHis');
    $mandate_start_date = date('YmdHis', strtotime($record['Einzugsstart']));
    if (empty($mandate_start_date) || $mandate_start_date < $now) {
      $mandate_start_date = $now;
    }

    // compile and create SEPA mandate
    $mandate_params = array(
      'type'                => 'OOFF',
      'iban'                => $record['IBAN'],
      'bic'                 => $record['BIC'],
      'amount'              => number_format($record['BuchungsBetrag'], 2),
      'contact_id'          => $contact_id,
      'currency'            => 'EUR',
      'receive_date'        => $mandate_start_date,
      'campaign_id'         => $this->getCampaignID($record),
      'financial_type_id'   => 1, // Donation
      );

    // create mandate
    $mandate = civicrm_api3('SepaMandate', 'createfull', $mandate_params);
  }

  /**
   * update an existing contract:
   * If contractId is set, then all changes in column U-AE are related to this contractID.
   * if the response in field AM/AN is positive and there is data in columns U-AE.
   *
   * FIELDS: Vertragsnummer  Bankleitzahl  Kontonummer Bic Iban  Kontoinhaber  Bankinstitut  Einzugsstart  JahresBetrag  BuchungsBetrag  Einzugsintervall  EinzugsEndeDatum
   */
  public function updateContract($contract_id, $contact_id, $record) {
    if (empty($contract_id)) return; // this shoudln't happen
    $config = CRM_Streetimport_Config::singleton();

    // STEP 1: TRIGGER UPDATE

    // validate parameters
    if (    empty($record['IBAN'])
         || empty($record['BIC'])
         || empty($record['JahresBetrag'])
         || empty($record['Einzugsintervall'])) {
      return $this->logger->logError("Couldn't create mandate, information incomplete.", $record);
    }

    // find out update date
    $now = date('Y-m-d H:i:s');
    if (empty($record['Einzugsstart'])) {
      $new_start_date = $now;
    } else {
      $new_start_date = date('Y-m-d H:i:s', strtotime($record['Einzugsstart']));
      if ($new_start_date < $now) {
        $new_start_date = $now;
      }
    }

    // send upgrade notification
    $annual_amount = $record['JahresBetrag'];
    $frequency = $record['Einzugsintervall'];
    $amount = number_format($annual_amount / $frequency, 2);
    $contract_modification = array(
      'action'              => 'update',
      'date'                => $new_start_date,
      'id'                  => $contract_id,
      'medium_id'           => $this->getMediumID(),
      'campaign_id'         => $this->getCampaignID(),
      'iban'                => $record['IBAN'],
      'bic'                 => $record['BIC'],
      'amount'              => $amount,
      'frequency_interval'  => (int) (12.0 / $frequency),
      'frequency_unit'      => 'month',
      'cycle_day'           => $config->getNextCycleDay($new_start_date),
      // no 'end_date' in contracts any more
      );
    error_log("Contract.modify: " . json_encode($contract_modification));
    civicrm_api3('Contract', 'modify', $contract_modification);
    $this->logger->logDebug("Update for membership [{$contract_id}] scheduled.", $record);



    // STEP 2: STOP OLD MANDATE RIGHT AWAY IF REQUESTED

    if (!$record['weiterbuchen']) {
      $old_recurring_contribution_id = civicrm_api3('Membership', 'getvalue', array(
        'id'     => $contract_id,
        'return' => $config->getGPCustomFieldKey('membership_recurring_contribution')));
      $old_mandate_id = civicrm_api3('SepaMandate', 'get', array(
        'entity_id'    => $old_recurring_contribution_id,
        'entity_table' => 'civicrm_contribution_recur',
        'return'       => 'id'));
      if (!empty($old_mandate_id['id'])) {
        CRM_Sepa_BAO_SEPAMandate::terminateMandate($old_mandate_id['id'], $now, 'CHNG');
        CRM_Sepa_Logic_Batching::closeEnded();
        $this->logger->logDebug("SEPA mandate for membership [{$contract_id}] terminated right away on request.", $record);
      } else {
        $this->logger->logError("No mandate attached to membership [{$contract_id}], couldn't stop!", $record);
      }
    }


    // STEP 3: SCHEDULE END IF REQUESTED

    if (!empty($record['EinzugsEndeDatum'])) {
      $contract_modification = array(
        'action'        => 'cancel',
        'id'            => $contract_id,
        'medium_id'     => $this->getMediumID(),
        'campaign_id'   => $this->getCampaignID(),
        'cancel_reason' => 'MS02',
        'cancel_date'   => date('Y-m-d H:i:s', strtotime($record['EinzugsEndeDatum'])),
        );
      error_log("Contract.modify: " . json_encode($contract_modification));
      civicrm_api3('Contract', 'modify', $contract_modification);
      $this->logger->logDebug("Contract (membership) [{$contract_id}] scheduled for termination.", $record);
    }
  }

  /**
   * Cancel all active contracts of a given contact
   */
  public function cancelAllContracts($contact_id, $cancel_reason, $record) {
    $config = CRM_Streetimport_Config::singleton();

    // find all active memberships (contracts)
    $memberships = civicrm_api3('Membership', 'get', array(
      'contact_id' => $contact_id,
      'status_id'  => array('IN' => $config->getActiveMembershipStatuses()),
      'return'     => 'id,status_id'  // TODO: more needed for cancellation?
      ));
    foreach ($memberships['values'] as $membership) {
      $this->cancelContract($membership, $record, array('cancel_reason' => $cancel_reason));
    }
  }

  /**
   * end contract, i.e. membership _and_ mandate
   */
  public function cancelContract($membership, $record, $params = array()) {
    $config = CRM_Streetimport_Config::singleton();
    $end_date = date('YmdHis', strtotime('yesterday')); // end_date has to be now, not $this->getDate()

    // first load the membership
    if (empty($membership)) {
      return $this->logger->logError("Contract (membership) [{$membership['id']}] NOT FOUND.", $record);
    }

    // now check if it's still active
    if (!$this->isContractActive($membership)) {
      $this->logger->logError("Contract (membership) [{$membership['id']}] is not active.", $record);
    }

    // finally call contract extesion
    $contract_modification = array(
      'action'        => 'cancel',
      'id'            => $membership['id'],
      'medium_id'     => $this->getMediumID(),
      'campaign_id'   => $this->getCampaignID(),
      'cancel_reason' => CRM_Utils_Array::value('cancel_reason', $params, 'MS02'),
      'cancel_date'   => date('Y-m-d H:i:s', strtotime($this->getDate($record))),
      );
    error_log("Contract.modify: " . json_encode($contract_modification));
    civicrm_api3('Contract', 'modify', $contract_modification);
    $this->logger->logDebug("Contract (membership) [{$membership['id']}] scheduled for termination.", $record);
  }

  /**
   * check if the given contract is still active
   */
  public function isContractActive($membership) {
    $config = CRM_Streetimport_Config::singleton();
    return in_array($membership['status_id'], $config->getActiveMembershipStatuses());
  }

  /**
   * check if the given mandate is active
   */
  public function isMandateActive($mandate) {
    return  $mandate['status'] == 'RCUR'
         || $mandate['status'] == 'FRST'
         || $mandate['status'] == 'INIT'
         || $mandate['status'] == 'SENT';
  }

  /**
   * check if the given recurring contribution is active
   */
  public function isContributionRecurActive($contribution_recur) {
    return  $contribution_recur['contribution_status_id'] == 2 // pending
         || $contribution_recur['contribution_status_id'] == 5; // in progress
  }

  /**
   * Create a new bank account unless it already exists
   */
  public function addBankAccount($contact_id, $reference_type, $reference, $record, $data = array()) {
    try {
      // look up reference type option value ID(!)
      $reference_type_value = civicrm_api3('OptionValue', 'getsingle', array(
        'value'           => $reference_type,
        'option_group_id' => 'civicrm_banking.reference_types',
        'is_active'       => 1));

      // find existing references
      $existing_references = civicrm_api3('BankingAccountReference', 'get', array(
        'reference'         => $reference,
        'reference_type_id' => $reference_type_value['id'],
        'option.limit'      => 0));

      // get the accounts for this
      $bank_account_ids = array();
      foreach ($existing_references['values'] as $account_reference) {
        $bank_account_ids[] = $account_reference['ba_id'];
      }
      if (!empty($bank_account_ids)) {
        $contact_bank_accounts = civicrm_api3('BankingAccount', 'get', array(
          'id'           => array('IN' => $bank_account_ids),
          'contact_id'   => $contact_id,
          'option.limit' => 1));
        if ($contact_bank_accounts['count']) {
          // bank account already exists with the contact
          return;
        }
      }

      // if we get here, that means that there is no such bank account
      //  => create one
      $bank_account = civicrm_api3('BankingAccount', 'create', array(
        'contact_id'  => $contact_id,
        'description' => "Bulk Importer",
        'data_parsed' => json_encode($data)));

      $bank_account_reference = civicrm_api3('BankingAccountReference', 'create', array(
        'reference'         => $reference,
        'reference_type_id' => $reference_type_value['id'],
        'ba_id'             => $bank_account['id']));
    } catch (Exception $e) {
      $this->logger->logError("Couldn't add bank account {$reference} [{$reference_type}]", $record);
    }
  }

  /**
   * take address data and see what to do with it:
   * - if it's not enough data -> create ticket (activity) for manual processing
   * - else: if no address is present -> create a new one
   * - else: if new data wouldn't replace ALL the data of the old address -> create ticket (activity) for manual processing
   * - else: update address
   */
  public function createOrUpdateAddress($contact_id, $address_data, $record) {
    if (empty($address_data)) return;

    // check if address is complete
    $address_complete = TRUE;
    $config = CRM_Streetimport_Config::singleton();
    $required_attributes = $config->getRequiredAddressAttributes();
    foreach ($required_attributes as $required_attribute) {
      if (empty($address_data[$required_attribute])) {
        $address_complete = FALSE;
      }
    }

    if (!$address_complete) {
      $this->logger->logDebug("Manual address update required for [{$contact_id}].", $record);
      return $this->createManualUpdateActivity(
          $contact_id, 'Manual Address Update', $record, 'activities/ManualAddressUpdate.tpl',
          array('title'   => 'Please update contact\'s address',
                'fields'  => $config->getAllAddressAttributes(),
                'address' => $address_data));
    }

    // find the old address
    $old_address_id = $this->getAddressId($contact_id, $record);
    if (!$old_address_id) {
      // CREATION (there is no address)
      $address_data['location_type_id'] = $config->getLocationTypeId();
      $address_data['contact_id'] = $contact_id;
      $this->resolveFields($address_data, $record);
      $this->logger->logDebug("Creating address for contact [{$contact_id}]: " . json_encode($address_data), $record);
      civicrm_api3('Address', 'create', $address_data);
      return $this->createContactUpdatedActivity($contact_id, $config->translate('Contact Address Created'), NULL, $record);
    }

    // load old address
    $old_address = civicrm_api3('Address', 'getsingle', array('id' => $old_address_id));

    // check if we'd overwrite EVERY one the relevant fields
    //  to avoid inconsistent addresses
    $full_overwrite = TRUE;
    $all_fields = $config->getAllAddressAttributes();
    foreach ($all_fields as $field) {
      if (empty($address_data[$field]) && !empty($old_address[$field])) {
        $full_overwrite = FALSE;
        break;
      }
    }

    if ($full_overwrite) {
      // this is a proper address update
      $address_data['id'] = $address_id;
      $this->logger->logDebug("Updating address for contact [{$contact_id}]: " . json_encode($address_data), $record);
      civicrm_api3('Address', 'create', $address_data);
      return $this->createContactUpdatedActivity($contact_id, $config->translate('Contact Address Updated'), NULL, $record);

    } else {
      // this would create inconsistent/invalid addresses -> manual interaction required
      $this->logger->logDebug("Manual address update required for [{$contact_id}].", $record);
      return $this->createManualUpdateActivity(
          $contact_id, 'Manual Address Update', $record, 'activities/ManualAddressUpdate.tpl',
          array('title'       => 'Please update contact\'s address',
                'fields'      => $config->getAllAddressAttributes(),
                'address'     => $address_data,
                'old_address' => $old_address));
    }
  }

  /**
   * Will resolve known fields (e.g. prefix_id, country_id, ...)
   * that require IDs rather than the value in the given data array
   *
   * @todo move to parent class
   */
  public function resolveFields(&$data, $record) {
    if (isset($data['prefix_id']) && !is_numeric($data['prefix_id'])) {
      $prefix_id = CRM_Core_OptionGroup::getValue('individual_prefix', $data['prefix_id']);
      if ($prefix_id) {
        $data['prefix_id'] = $prefix_id;
      } else {
        // not found!
        $this->logger->logError("Couldn't resolve prefix '{$data['prefix_id']}'.", $record);
        $data['prefix_id'] = '';
      }
    }
  }




  /*****************************************************
   *               ACTIVITY CREATION                   *
   ****************************************************/


  /**
   * Create a "Manual Update" activity
   *
   * @param $contact_id        well....
   * @param $subject           subject for the activity
   * @param $record            the data record that's being processed
   * @param $messageOrTemplate either the full details body of the activity (if $data empty)
   *                            or a template path (if $data not empty), in which case $data will be assigned as template variables
   */
  public function createManualUpdateActivity($contact_id, $subject, $record, $messageOrTemplate=NULL, $data=NULL) {
    $config = CRM_Streetimport_Config::singleton();

    // first get contact called activity type
    if ($this->_manual_update_required_id == NULL) {
      $this->_manual_update_required_id = CRM_Core_OptionGroup::getValue('activity_type', 'manual_update_required', 'name');
      if (empty($this->_manual_update_required_id)) {
        // couldn't be found => create
        $activity = civicrm_api3('OptionValue', 'create', array(
          'option_group_id' => 'activity_type',
          'name'            => 'manual_update_required',
          'label'           => $config->translate('Manual Update Required'),
          'is_active'       => 1
          ));
        $this->_manual_update_required_id = CRM_Core_OptionGroup::getValue('activity_type', 'manual_update_required', 'name');
      }
    }

    // NOW create the activity
    $activityParams = array(
      'activity_type_id'    => $this->_manual_update_required_id,
      'subject'             => $config->translate($subject),
      'status_id'           => $config->getActivityCompleteStatusId(),
      'campaign_id'         => $this->getCampaignID($record),
      'activity_date_time'  => $this->getDate($record),
      'target_contact_id'   => (int) $contact_id,
      'source_contact_id'   => (int) $config->getCurrentUserID(),
      'assignee_contact_id' => (int) $config->getFundraiserContactID(),
    );

    // calculate details
    if ($messageOrTemplate) {
      if ($data) {
        // this is should be a template -> render it!
        $activityParams['details'] = $this->renderTemplate($messageOrTemplate, $data);
      } else {
        $activityParams['details'] = $messageOrTemplate;
      }
    }

    $this->createActivity($activityParams, $record, array($config->getFundraiserContactID()));
  }


  /**
   * Create a RESPONSE activity
   */
  public function createResponseActivity($contact_id, $title, $record) {
    $config = CRM_Streetimport_Config::singleton();

    // first get Response activity type
    if ($this->_response_activity_id == NULL) {
      $this->_response_activity_id = CRM_Core_OptionGroup::getValue('activity_type', 'Response', 'name');
      if (empty($this->_response_activity_id)) {
        // couldn't be found => create
        $activity = civicrm_api3('OptionValue', 'create', array(
          'option_group_id' => 'activity_type',
          'name'            => 'Response',
          'label'           => $config->translate('Manual Update Required'),
          'is_active'       => 1
          ));
        $this->_response_activity_id = CRM_Core_OptionGroup::getValue('activity_type', 'Response', 'name');
      }
    }

    // determine the subject
    $campaign = $this->loadEntity('Campaign', $this->getCampaignID($record));
    // $subject = $campaign['title'] . ' - ' . $title;
    $subject = $title; // Marco: drop the title

    // NOW create the activity
    $activityParams = array(
      'activity_type_id'    => $this->_response_activity_id,
      'subject'             => $subject,
      'status_id'           => $config->getActivityCompleteStatusId(),
      'campaign_id'         => $this->getCampaignID($record),
      'activity_date_time'  => $this->getDate($record),
      'source_contact_id'   => (int) $config->getCurrentUserID(),
      'target_contact_id'   => (int) $contact_id,
      // 'assignee_contact_id' => (int) $config->getFundraiserContactID(),
    );

    $activity = $this->createActivity($activityParams, $record);
  }


  /**
   * Create a "Contact Updated" activity
   */
  public function createContactUpdatedActivity($contact_id, $subject, $details, $record) {
    $config = CRM_Streetimport_Config::singleton();

    // first get contact called activity type
    if ($this->_update_activity_id == NULL) {
      $this->_update_activity_id = CRM_Core_OptionGroup::getValue('activity_type', 'contact_updated', 'name');
      if (empty($this->_update_activity_id)) {
        // couldn't be found => create
        $activity = civicrm_api3('OptionValue', 'create', array(
          'option_group_id' => 'activity_type',
          'name'            => 'contact_updated',
          'label'           => $config->translate('Contact Updated'),
          'is_active'       => 1
          ));
        $this->_update_activity_id = CRM_Core_OptionGroup::getValue('activity_type', 'contact_updated', 'name');
      }
    }

    // NOW create the activity
    $activityParams = array(
      'activity_type_id'    => $this->_update_activity_id,
      'subject'             => $subject,
      'details'             => $details,
      'status_id'           => $config->getActivityCompleteStatusId(),
      'campaign_id'         => $this->getCampaignID($record),
      'activity_date_time'  => date('YmdHis'), // has to be now
      'source_contact_id'   => (int) $config->getCurrentUserID(),
      'target_contact_id'   => (int) $contact_id,
      // 'assignee_contact_id' => (int) $config->getFundraiserContactID(),
    );

    $this->createActivity($activityParams, $record);
  }
}
