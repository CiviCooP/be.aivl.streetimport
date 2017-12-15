<?php
/*-------------------------------------------------------------+
| GP StreetImporter Record Handlers                            |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
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
  protected $_webshop_order_activity_id = NULL;

  protected $_contract_changes_produced = FALSE;
  protected $_external_identifier_to_campaign_id = array();
  protected $_external_identifier_to_contact_id = array();
  protected $_internal_identifier_to_contact_id = array();
  protected $_iban_to_bic = array();

  /**
   * This event is triggered AFTER the last record of a datasource has been processed
   *
   * @param $sourceURI string  source identifier, e.g. file name
   */
  public function finishProcessing($sourceURI) {
    if ($this->_contract_changes_produced) {
      // if contract changes have been produced, call the
      //  Contract processor to execute them
      civicrm_api3('Contract', 'process_scheduled_modifications', array());
    }
  }

  /**
   * look up contact id with CiviCRM ID
   * @todo use resolveContactID
   */
  protected function getContactIDbyCiviCRMID($contact_id) {
    if (!array_key_exists($contact_id, $this->_internal_identifier_to_contact_id)) {
      if (function_exists('identitytracker_civicrm_install')) {
        // identitytracker is enabled
        $contacts = civicrm_api3('Contact', 'findbyidentity', array(
          'identifier_type' => 'internal',
          'identifier'      => $contact_id));
        if ($contacts['count'] == 1) {
          $current_contact_id = $contacts['id'];
        } else {
          // NOT found or multiple
          $current_contact_id = NULL;
        }

      } else {
        // identitytracker is NOT enabled
        $current_contact_id = $contact_id;
      }
      $this->_internal_identifier_to_contact_id[$contact_id] = $current_contact_id;
    }
    return $this->_internal_identifier_to_contact_id[$contact_id];
  }

  /**
   * look up contact id with external ID
   * @todo use resolveContactID
   */
  protected function getContactIDbyExternalID($external_identifier) {
    if (empty($external_identifier)) return NULL;

    if (!array_key_exists($external_identifier, $this->_external_identifier_to_contact_id)) {
      if (function_exists('identitytracker_civicrm_install')) {
        // identitytracker is enabled
        $contacts = civicrm_api3('Contact', 'findbyidentity', array(
          'identifier_type' => 'external',
          'identifier'      => $external_identifier));
      } else {
        // identitytracker is NOT enabled
        $contacts = civicrm_api3('Contact', 'get', array(
          'external_identifier' => $external_identifier,
          'return'              => 'id'));
      }

      // evaluate results
      if ($contacts['count'] == 1) {
        $this->_external_identifier_to_contact_id[$external_identifier] = $contacts['id'];
      } elseif ($contacts['count'] > 1) {
        // not unique? this shouldn't happen
        $this->_external_identifier_to_contact_id[$external_identifier] = NULL;
      } else {
        // NOT found
        $this->_external_identifier_to_contact_id[$external_identifier] = NULL;
      }
    }
    return $this->_external_identifier_to_contact_id[$external_identifier];
  }

  /**
   * add a detail entity (Phone, Email, Website, ) to a contact
   *
   * @param $record          the data record (for logging)
   * @param $contact_id      the contact
   * @param $entity          the entity type to be created, i.e. 'Phone'
   * @param $data            the data, e.g. ['phone' => '23415425']
   * @param $create_activity should a 'contact changed' activity be created?
   * @param $create_data     data to be used if a new entity has to be created.
   *                           if no location is set, $config->getLocationTypeId() will be used
   * @return the id of the entity (either created or found)
   */
  protected function addDetail($record, $contact_id, $entity, $data, $create_activity=FALSE, $create_data=array()) {
    // make sure they're not empty...
    $print_value = implode('|', array_values($data));
    if (empty($print_value)) return;

    // first: try to find it
    $search = civicrm_api3($entity, 'get', $data + array(
      'contact_id' => $contact_id,
      'return'     => 'id'));
    if ($search['count'] > 0) {
      // this entity already exists, log it:
      $print_value = implode('|', array_values($data));
      $this->logger->logDebug("Contact [{$contact_id}] already has {$entity} '{$print_value}'", $record);

      // return it
      return reset($search['values'])['id'];

    } else {
      // not found: create it
      $config = CRM_Streetimport_Config::singleton();

      // prepare data
      $create_data = $data + $create_data;
      $create_data['contact_id'] = $contact_id;
      if (empty($create_data['location_type_id'])) {
        $create_data['location_type_id'] = $config->getLocationTypeId();
      }

      // create a new  entity
      $new_entity = civicrm_api3($entity, 'create', $create_data);

      // log it
      $print_value = implode('|', array_values($data));
      $this->logger->logDebug("Contact [{$contact_id}] new {$entity} added: {$print_value}", $record);

      // create activity if requested
      if ($create_activity) {
        $this->createContactUpdatedActivity($contact_id, "Contact {$entity} Added", NULL, $record);
      }

      // return
      return $new_entity['id'];
    }
  }

  /**
   * look up campaign id with external identifier (cached)
   */
  protected function getCampaignIDbyExternalIdentifier($external_identifier) {
    if (!array_key_exists($external_identifier, $this->_external_identifier_to_campaign_id)) {
      $campaign = civicrm_api3('Campaign', 'getsingle', array(
        'external_identifier' => $external_identifier,
        'return'              => 'id'));
      $this->_external_identifier_to_campaign_id[$external_identifier] = $campaign['id'];
    }

    return $this->_external_identifier_to_campaign_id[$external_identifier];
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
         || empty($this->getBIC($record, $record['IBAN']))
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
      'bic'                 => $this->getBIC($record, $record['IBAN']),
      'amount'              => $amount,
      'contact_id'          => $contact_id,
      'currency'            => 'EUR',
      'frequency_unit'      => 'month',
      'cycle_day'           => $config->getNextCycleDay($mandate_start_date, $now),
      'frequency_interval'  => (int) (12.0 / $frequency),
      'start_date'          => $mandate_start_date,
      'campaign_id'         => $this->getCampaignID($record),
      'financial_type_id'   => 2, // Membership Dues
      );
    if (!empty($record['EinzugsEndeDatum'])) {
      $mandate_params['end_date'] = date('YmdHis', strtotime($record['EinzugsEndeDatum']));
    }

    // create and reload mandate
    $mandate = civicrm_api3('SepaMandate', 'createfull', $mandate_params);
    $mandate = civicrm_api3('SepaMandate', 'getsingle', array('id' => $mandate['id']));

    // NEXT: create membership
    $membership_params = array(
      'contact_id'                                           => $contact_id,
      'membership_type_id'                                   => $this->getMembershipTypeID($record),
      'member_since'                                         => $this->getDate($record),
      'start_date'                                           => $mandate_start_date,
      'join_date'                                            => $this->getDate($record),
      'campaign_id'                                          => $this->getCampaignID($record),
      'membership_payment.membership_recurring_contribution' => $mandate['entity_id'],
      'membership_payment.from_ba'                           => CRM_Contract_BankingLogic::getOrCreateBankAccount($contact_id, $record['IBAN'], $this->getBIC($record, $record['IBAN'])),
      'membership_payment.to_ba'                             => CRM_Contract_BankingLogic::getCreditorBankAccount(),
      );

    $this->logger->logDebug("Calling Contract.create: " . json_encode($membership_params), $record);
    $membership = civicrm_api3('Contract', 'create', $membership_params);
    $this->_contract_changes_produced = TRUE;
    return $membership['id'];
  }

  /**
   * Create a OOFF mandate
   */
  public function createOOFFMandate($contact_id, $record) {
    $config = CRM_Streetimport_Config::singleton();

    // validate parameters
    if (    empty($record['IBAN'])
         || empty($this->getBIC($record, $record['IBAN']))
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
      'bic'                 => $this->getBIC($record, $record['IBAN']),
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
   * @param $contract_id  the contract/membership ID
   * @param $record       the record expected to contain the following data: Vertragsnummer  Bankleitzahl  Kontonummer Bic Iban  Kontoinhaber  Bankinstitut  Einzugsstart  JahresBetrag  BuchungsBetrag  Einzugsintervall  EinzugsEndeDatum
   * @param $new_type     can provide a new membership_type_id
   * @param $action       the Contract.modfify action: 'update' or 'revive'
   */
  public function updateContract($contract_id, $contact_id, $record, $new_type = NULL, $action = 'update') {
    if (empty($contract_id)) return; // this shoudln't happen
    $config = CRM_Streetimport_Config::singleton();

    // STEP 1: TRIGGER UPDATE

    // validate parameters
    if (    empty($record['IBAN'])
         || empty($this->getBIC($record, $record['IBAN']))
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
    $contract_modification = array(
      'action'                                  => $action,
      'date'                                    => $new_start_date,
      'id'                                      => $contract_id,
      'medium_id'                               => $this->getMediumID(),
      'campaign_id'                             => $this->getCampaignID($record),
      'membership_payment.from_ba'              => CRM_Contract_BankingLogic::getOrCreateBankAccount($contact_id, $record['IBAN'], $this->getBIC($record, $record['IBAN'])),
      'membership_payment.to_ba'                => CRM_Contract_BankingLogic::getCreditorBankAccount(),
      'membership_payment.membership_annual'    => number_format($annual_amount, 2),
      'membership_payment.membership_frequency' => $frequency,
      'membership_payment.cycle_day'            => $config->getNextCycleDay($new_start_date, $now),
      // no 'end_date' in contracts any more
      );

    // add membership type change (if requested)
    if ($new_type) {
      $contract_modification['membership_type_id'] = $new_type;
    }

    $this->logger->logDebug("Calling Contract.modify: " . json_encode($contract_modification), $record);
    civicrm_api3('Contract', 'modify', $contract_modification);
    $this->_contract_changes_produced = TRUE;
    $this->logger->logDebug("Update for membership [{$contract_id}] scheduled.", $record);



    // STEP 2: STOP OLD MANDATE RIGHT AWAY IF REQUESTED
    if (isset($record['weiterbuchen']) && $record['weiterbuchen']=='0') {
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
        'action'                                           => 'cancel',
        'id'                                               => $contract_id,
        'medium_id'                                        => $this->getMediumID(),
        'campaign_id'                                      => $this->getCampaignID($record),
        'membership_cancellation.membership_cancel_reason' => 'MS02',
        'date'                                             => date('Y-m-d H:i:s', strtotime($record['EinzugsEndeDatum'])),
        );

      $this->logger->logDebug("Calling Contract.modify: " . json_encode($contract_modification), $record);
      civicrm_api3('Contract', 'modify', $contract_modification);
      $this->_contract_changes_produced = TRUE;
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
    try {
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

      // finally call contract extension
      $contract_modification = array(
        'action'                                           => 'cancel',
        'id'                                               => $membership['id'],
        'medium_id'                                        => $this->getMediumID(),
        'campaign_id'                                      => $this->getCampaignID($record),
        'membership_cancellation.membership_cancel_reason' => CRM_Utils_Array::value('cancel_reason', $params, 'MS02'),
        );

      // add cancel date if in the future:
      $requested_cancel_date = strtotime($this->getDate($record));
      if ($requested_cancel_date > strtotime("now")) {
        $contract_modification['date'] = date('Y-m-d H:i:00', $requested_cancel_date);
      }

      $this->logger->logDebug("Calling Contract.modify: " . json_encode($contract_modification), $record);
      civicrm_api3('Contract', 'modify', $contract_modification);
      $this->_contract_changes_produced = TRUE;
      $this->logger->logDebug("Contract (membership) [{$membership['id']}] scheduled for termination.", $record);
    } catch (Exception $e) {
      $this->logger->logError("Contract (membership) [{$membership['id']}] received an exception when trying to terminate it: " . $e->getMessage(), $record);
    }
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
   * Get the BIC from a record. If no BIC field is in the record
   * it'll try to look up the BIC using the 'Little BIC Extension'
   * If no BIC can be found, an error is logged
   */
  public function getBIC($record, $iban, $bic_field='BIC') {
    if (!empty($record[$bic_field])) {
      return $record[$bic_field];
    }

    if (empty($iban)) {
      $this->logger->logError("Couldn't resolve BIC, no IBAN given.", $record);
      return NULL;
    }

    if (!empty($this->_iban_to_bic[$iban])) {
      return $this->_iban_to_bic[$iban];
    }

    $config = CRM_Streetimport_Config::singleton();
    if (!empty($iban) && $config->isLittleBicExtensionAccessible()) {
      // use Little BIC extension to look up IBAN
      $result = civicrm_api3('Bic', 'getfromiban', array('iban' => $iban));
      if (!empty($result['bic'])) {
        $this->_iban_to_bic[$iban] = $result['bic'];
        return $result['bic'];
      }
    }

    if (!isset($this->_iban_to_bic[$iban])) {
      $this->_iban_to_bic[$iban] = '';
      $this->logger->logError("Couldn't resolve BIC for IBAN '{$iban}'", $record);
    }
    return NULL;
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
      $address_data['id'] = $old_address_id;
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
      'status_id'           => $config->getImportErrorActivityStatusId(),
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
    // $campaign = $this->loadEntity('Campaign', $this->getCampaignID($record));
    // $subject = $campaign['title'] . ' - ' . $title;
    $subject = $title; // Marco said: drop the title

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
   * Create a "Webshop Order" activity
   *
   * @param $contact_id        well....
   * @param $record            the data record that's being processed
   * @param $data              additional data (e.g. custom fields) for the activity
   */
  public function createWebshopActivity($contact_id, $record, $data) {
    $config = CRM_Streetimport_Config::singleton();

    // first get contact called activity type
    if ($this->_webshop_order_activity_id == NULL) {
      $this->_webshop_order_activity_id = CRM_Core_OptionGroup::getValue('activity_type', 'Webshop Order', 'name');
    }

    if (empty($this->_webshop_order_activity_id)) {
      $this->logger->logError("Activity type 'Webshop Order' unknown. No activity created.", $record);
      return;
    }

    // NOW create the activity
    $activityParams = array(
      'activity_type_id'    => $this->_webshop_order_activity_id,
      'subject'             => CRM_Utils_Array::value('subject', $data, 'Webshop Order'),
      'status_id'           => $config->getActivityScheduledStatusId(),
      'campaign_id'         => $this->getCampaignID($record),
      'activity_date_time'  => $this->getDate($record),
      'source_contact_id'   => (int) $config->getCurrentUserID(),
      'target_contact_id'   => (int) $contact_id,
    );

    $this->createActivity($activityParams + $data, $record);
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
