<?php
/*-------------------------------------------------------------+
| GP StreetImporter Record Handlers                            |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/


define('TM_PHONE_NOT_CALLED', 0);
define('TM_PHONE_CALLED',     1);
define('TM_PHONE_CHANGED',    2);
define('TM_PHONE_DELETED',    3);
define('TM_PHONE_NEW',        4);

/**
 * GP TEDI Handler
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
class CRM_Streetimport_Handler_GP_TEDITelephoneRecordHandler extends CRM_Streetimport_Handler_TMRecordHandler {


  /**
   * Check if the given handler implementation can process the record
   *
   * @param $record  an array of key=>value pairs
   * @return true or false
   */
  public function canProcessRecord($record, $sourceURI) {
    $parsedFileName = $this->parseTmFile($sourceURI);
    return ($parsedFileName && $parsedFileName['file_type'] == 'Telefon' && $parsedFileName['tm_company'] == 'tedi');
  }

  /**
   * process the given record
   *
   * @param $record  an array of key=>value pairs
   * @return true
   * @throws exception if failed
   */
  public function processRecord($record, $sourceURI) {
    $parsedFileName = $this->parseTmFile($sourceURI);

    $contact_id = $this->getContactID($record);
    $phone_ids  = $this->getPhoneIDs($record, $contact_id);

    switch ($record['Status']) {
      case TM_PHONE_NOT_CALLED:
        // TODO: What should happen here?
        break;

      case TM_PHONE_CALLED:
        // TODO: What should happen here?
        break;

      case TM_PHONE_CHANGED:
        // TODO: What should happen here?
        break;

      case TM_PHONE_DELETED:
        // TODO: What should happen here?
        break;

      case TM_PHONE_NEW:
        // TODO: What should happen here?
        break;

      default:
        // Undefined status!
        // TODO: What should happen here?
        break;
    }
  }


  protected function getPhoneNumber($record) {
    return "+{$record['TelLand']} {$record['TelVorwahl']} {$record['TelNummer']}";
  }

  protected function getPhoneType($record) {
    // TODO: deduce from area code ($record['TelVorwahl'])
    // TODO: what's the default?
    return NULL;
  }

  /**
   * Will resolve the phone number
   */
  protected function getPhoneIDs($record, $contact_id) {
    if ($this->isCompatibilityMode($record)) {
      // these are IMB phone IDs -> look up the phone number instead
      $phone_ids = array();
      $phone_numeric = "{$record['TelLand']}{$record['TelVorwahl']}{$record['TelNummer']}";

      if (!empty($phone_numeric) && !empty($contact_id)) {
        $search_result = civicrm_api3('Phone', 'get', array(
          'phone_numeric' => $phone_numeric,
          'contact_id'    => $contact_id,
          'return'        => 'id'
          ));
        foreach ($search_result['values'] as $phone) {
          $phone_ids[] = $phone['id'];
        }
      }
      return $phone_ids;

    } else {
      return array($record['TelID']);
    }
  }


}
