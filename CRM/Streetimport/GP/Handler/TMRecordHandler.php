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
abstract class CRM_Streetimport_GP_Handler_TMRecordHandler extends CRM_Streetimport_GP_Handler_GPRecordHandler {

  /** file name pattern as used by TM company */
  protected static $TM_PATTERN = '#(?P<org>[a-zA-Z\-]+)_(?P<project1>\w+)_(?P<tm_company>[a-z]+)_(?P<code>C?\d{4})_(?P<date>\d{8})_(?P<time>\d{6})_(?P<project2>.+)_(?P<file_type>[a-zA-Z]+)[.]csv$#';

  /** stores the parsed file name */
  protected $file_name_data = NULL;

  /**
   * Checks if this record uses IMB or CiviCRM IDs
   * aka legacy mode
   */
  protected function isCompatibilityMode($record) {
    return substr($this->file_name_data['code'], 0, 1) != 'C';
  }

  /**
   * Checks if this record uses IMB or CiviCRM IDs
   */
  protected function getCampaignID($record) {
    $campaign_identifier = $this->file_name_data['code'];
    if ($this->isCompatibilityMode($record)) {
      // these are IMB campaign IDs, look up the internal Id
      $campaign = civicrm_api3('Campaign', 'getsingle', array(
        'external_identifier' => 'AKTION-' . $campaign_identifier,
        'return' => 'id'));
      return $campaign['id'];

    } else {
      // this should be an internal campaign id, with prefix 'C'
      return (int) substr($campaign_identifier, 1);
    }
  }

  /**
   * this will return the membership object representing the contract
   */
  protected function getContract($record, $contact_id) {
    $membership_id = $this->getContractID($contact_id, $record);
    if (empty($membership_id)) return NULL;

    $membership = civicrm_api3('Membership', 'get', array('id' => $membership_id));
    if ($membership['id']) {
      return reset($membership);
    } else {
      return NULL;
    }
  }

  /**
   * Will resolve the referenced contact id
   */
  protected function getContactID($record) {
    if ($this->isCompatibilityMode($record)) {
      // these are IMB contact numbers
      $external_identifier = 'IMB-' . trim($record['id']);
      return $this->getContactIDbyExternalID($external_identifier);
    } else {
      return $this->getContactIDbyCiviCRMID($record['id']);
    }
  }

  /**
   * Get the relevant address Id for the contact
   */
  protected function getAddressId($contact_id, $record) {
    if ($this->isCompatibilityMode($record) || empty($record['address_id'])) {
      // in compatibility mode we don't have an ID, just get the primary address
      $addresses_search = array(
        'contact_id' => $contact_id,
        'is_primary' => 1);
    } else {
      // check if the address_id is (still) there
      $addresses_search = array('id' => $record['address_id']);
    }

    $address_query = civicrm_api3('Address', 'get', $addresses_search);
    if (isset($address_query['id']) && $address_query['id']) {
      // address found
      return $address_query['id'];
    } else {
      return NULL;
    }
  }

  /**
   * Will try to parse the given name and extract the parameters outlined in TM_PATTERN
   *
   * @return NULL if not matched, data else
   */
  protected function parseTmFile($sourceID) {
    if (preg_match(self::$TM_PATTERN, $sourceID, $matches)) {
      return $matches;
    } else {
      return NULL;
    }
  }
}
