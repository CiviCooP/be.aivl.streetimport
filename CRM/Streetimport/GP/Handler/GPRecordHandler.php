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
   * Create a "Manual Update" activity
   */
  public function createManualUpdateActivity($contact_id, $message, $record) {
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
        $this->_manual_update_required_id = $activity['id'];
      }
    }

    // NOW create the activity
    $activityParams = array(
      'activity_type_id'    => $this->_manual_update_required_id,
      'subject'             => $config->translate('Manual Update Required'),
      'details'             => $message,
      'status_id'           => $config->getActivityCompleteStatusId(),
      'campaign_id'         => $this->getCampaignID($record),
      'activity_date_time'  => $this->getDate($record),
      'source_contact_id'   => (int) $contact_id,
      'assignee_contact_id' => (int) $config->getFundraiserContactID(),
    );

    $this->createActivity($activityParams, $record, array($config->getFundraiserContactID()));
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
        $this->tagContact($contact_id, 'anonymise');
        $this->cancelContract($contact_id, $record);
        break;

      case 'disabled':
        // disabled (stillgelegt) means deleted + tagged
        civicrm_api3('Contact', 'create', array(
          'id'         => $contact_id,
          'is_deleted' => 1));
        $this->tagContact($contact_id, 'inaktiv');
        $this->cancelContract($contact_id, $record);
        break;

      case 'deceased':
        // disabled (stillgelegt) means deleted + tagged
        civicrm_api3('Contact', 'create', array(
          'id'          => $contact_id,
          'is_deleted'  => 1,
          'is_deceased' => 1));
        $this->tagContact($contact_id, 'inaktiv');
        $this->cancelContract($contact_id, $record);
        break;

      default:
        $this->logger->logFatal("DisableContact mode '{$mode}' not implemented!", $record);
        break;
    }
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
        $this->_response_activity_id = $activity['id'];
      }
    }

    // determine the subject
    $campaign = $this->loadEntity('Campaign', $this->getCampaignID($record));
    $subject = $campaign['title'] . ' - ' . $title;

    // NOW create the activity
    $activityParams = array(
      'activity_type_id'    => $this->_response_activity_id,
      'subject'             => $subject,
      'status_id'           => $config->getActivityCompleteStatusId(),
      'campaign_id'         => $this->getCampaignID($record),
      'activity_date_time'  => $this->getDate($record),
      'source_contact_id'   => (int) $contact_id,
      'assignee_contact_id' => (int) $config->getFundraiserContactID(),
    );

    $this->createActivity($activityParams, $record, array($config->getFundraiserContactID()));
  }

  /**
   * create a new contract
   *
   * FIELDS: Vertragsnummer  Bankleitzahl  Kontonummer Bic Iban  Kontoinhaber  Bankinstitut  Einzugsstart  JahresBetrag  BuchungsBetrag  Einzugsintervall  EinzugsEndeDatum
   */
  public function createContract($contact_id, $record) {
    $config = CRM_Streetimport_Config::singleton();

    // TODO: validate parameters

    // TODO: adjust start date
    $mandate_start_date = date('YmdHis', strtotime($record['Einzugsstart']));

    // FIRST: compile and create SEPA mandate
    $annual_amount = $record['JahresBetrag'];
    $frequency = $record['Einzugsintervall'];
    $amount = number_format($annual_amount / $frequency, 2);
    $mandate_params = array(
      'type'                => 'RCUR',
      'iban'                => $record['Iban'],
      'bic'                 => $record['Bic'],
      'contact_id'          => $contact_id,
      'currency'            => 'EUR',
      'frequency_unit'      => 'month',
      'frequency_interval'  => (int) (12.0 / $frequency),
      'start_date'          => $mandate_start_date,
      'campaign_id'         => $this->getCampaignID($record),
      'financial_type_id'   => 3, // Membership Dues
      );
    if (!empty($record['EinzugsEndeDatum'])) {
      $mandate_params['end_date'] = date('YmdHis', strtotime($record['EinzugsEndeDatum']));
    }
    $mandate = civicrm_api3('SepaMandate', 'createfull', $mandate_params);
    // relead freshly created mandate to get all attributes
    $mandate = civicrm_api3('SepaMandate', 'getsingle', array('id' => $mandate['id']));

    // NEXT: create membership
    $membership_annual        = $config->getGPCustomFieldKey('membership_annual');
    $membership_frequency     = $config->getGPCustomFieldKey('membership_frequency');
    $membership_rcontribution = $config->getGPCustomFieldKey('membership_recurring_contribution');

    $membership_params = array(
      'contact_id'              => $contact_id,
      'membership_type_id'      => $this->getMembershipTypeID($record),
      'member_since'            => $this->getDate(),
      'start_date'              => $mandate_start_date,
      'campaign_id'             => $this->getCampaignID($record),
      $membership_annual        => number_format($annual_amount, 2),
      $membership_frequency     => $frequency,
      $membership_rcontribution => $mandate['entity_id']
      );
    $membership = civicrm_api3('Membership', 'create', $membership_params);
  }

  /**
   * update an existing contract:
   * If contractId is set, then all changes in column U-AE are related to this contractID.
   * In conversion projects you will find no contractid here, which means you have to create a new one,
   * if the response in field AM/AN is positive and there is data in columns U-AE.
   *
   * FIELDS: Vertragsnummer  Bankleitzahl  Kontonummer Bic Iban  Kontoinhaber  Bankinstitut  Einzugsstart  JahresBetrag  BuchungsBetrag  Einzugsintervall  EinzugsEndeDatum
   */
  public function updateContract($contract_id, $contact_id, $record) {
    // TODO: implement
    // CAUTION: "weiterbuchen"
    // This field kann take "0", "1" and "".
    //    "1" => no break (normal data entry for contract data and Sepa DD issue date will be set as soon as possible;
    //    "0" => break! (If there is a debit planned inbetween of date in field AA (Einzugsstart) and import date) the contract shall be paused and NOT debited asap.
    //    ""  => nothing happens
    $this->logger->logError("UPDATE CONTRACT NOT IMPLEMENTED YET!", $record);
  }

  /**
   * end a contract
   */
  public function cancelContract($contact_id, $record) {
    // TODO: implement
    $this->logger->logError("CANCEL CONTRACT NOT IMPLEMENTED YET!", $record);
  }
}
