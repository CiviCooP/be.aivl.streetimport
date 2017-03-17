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
  protected $_manual_update_required_id;

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
    // TODO: implement
  }

  /**
   * Create a RESPONSE activity
   */
  public function createResponseActivity($contact_id, $record) {
    // TODO: implement
  }

  /**
   * create a new contract
   *
   * FIELDS: Vertragsnummer  Bankleitzahl  Kontonummer Bic Iban  Kontoinhaber  Bankinstitut  Einzugsstart  JahresBetrag  BuchungsBetrag  Einzugsintervall  EinzugsEndeDatum
   */
  public function createContract($contact_id, $record) {
    // TODO: implement
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

  }

  /**
   * end a contract
   */
  public function cancelContract($contact_id, $record) {
    // TODO: implement
  }
}
