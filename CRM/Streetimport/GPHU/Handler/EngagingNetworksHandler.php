<?php
/*-------------------------------------------------------------+
| Greenpeace Poland StreetImporter Record Handlers             |
| Copyright (C) 2018 Greenpeace CEE                            |
| Author: P. Figel (pfigel@greenpeace.org)                     |
+--------------------------------------------------------------*/

/**
 * Greenpeace Hungary Engaging Networks Import
 *
 * @author Patrick Figel <pfigel@greenpeace.org>
 * @license AGPL-3.0
 */
class CRM_Streetimport_GPHU_Handler_EngagingNetworksHandler extends CRM_Streetimport_RecordHandler {

  const PATTERN = '#/eaexport\-(?P<date>\d{8})\.csv$#';

  private $_date;
  private $_fileMatches;

  public function __construct($logger) {
    parent::__construct($logger);
  }

  /**
   * @param array $record
   * @param $sourceURI
   *
   * @return bool|null|true
   */
  public function canProcessRecord($record, $sourceURI) {
    if (is_null($this->_fileMatches)) {
      $this->_fileMatches = (bool) preg_match(self::PATTERN, $sourceURI, $matches);
      if ($this->_fileMatches) {
        $this->_date = $matches['date'];
      }
    }
    return $this->_fileMatches;
  }

  /**
   * @param array $record
   * @param $sourceURI
   *
   * @return void
   * @throws \CiviCRM_API3_Exception
   */
  public function processRecord($record, $sourceURI) {
    switch ($record['Campaign ID']) {
      case 'email_ok_hungary':
        $this->processEmail($record);
        break;

      default:
        $this->logger->logImport($record, TRUE, 'Engaging Networks', "Ignored Campaign ID {$record['Campaign ID']}");
        break;
    }
  }

  /**
   * Process email opt-in and opt-out requests
   *
   * @param $record
   */
  private function processEmail($record) {
    $config = CRM_Streetimport_Config::singleton();
    if ($record['Campaign Status'] == 'N') {
      // for opt-outs, we explicitly want to cover possible duplicates, so match
      // via email and remove mail flags for all contacts.
      $contacts = civicrm_api3('Contact', 'get',[
        'return' => 'id',
        'email' => $record['email'],
      ]);
      foreach ($contacts['values'] as $contact) {
        $this->removeContactFromGroup($contact['id'], $config->getGPGroupID('Newsletter'), $record);
        $this->logger->logDebug("Opting out Contact ID {$contact['id']}", $record);
      }
      $this->logger->logImport($record, TRUE, 'Engaging Networks', 'Processed Opt-out');
    }
    elseif ($record['Campaign Status'] == 'Y') {
      $contact = $this->findContactById($record);
      if (is_null($contact)) {
        $contact = $contact = $this->getOrCreateContact($record);
      }
      civicrm_api3('Contact', 'create', [
        'id' => $contact['id'],
        'do_not_email' => 0,
        'is_opt_out' => 0,
      ]);
      $this->addContactToGroup($contact['id'], $config->getGPGroupID('Newsletter'), $record);
      $this->logger->logImport($record, TRUE, 'Engaging Networks', "Processed Opt-in for Contact ID {$contact['id']}");
    }
    else {
      $this->logger->logImport($record, FALSE, 'Engaging Networks', "Invalid value for Campaign Status: {$record['Campaign Status']}");
    }
  }

  private function findContactById($record) {
    $params = [
      'return' => 'id',
    ];
    if (!empty($record['civi_id'])) {
      $params['id'] = $record['civi_id'];
    }
    elseif (!empty($record['supporter_id'])) {
      // Friends ID
      $params['external_identifier'] = $record['supporter_id'];
    }
    else {
      return NULL;
    }
    try {
      return civicrm_api3('Contact', 'getsingle', $params);
    } catch (CiviCRM_API3_Exception $e) {
      $this->logger->logWarning("Couldn't find contact with supporter_id='{$record['supporter_id']}', civi_id='{$record['civi_id']}'.", $record);
      return NULL;
    }
  }

  private function getOrCreateContact($record) {
    $params = [
      'first_name' => CRM_Utils_Array::value('first_name', $record),
      'last_name'  => CRM_Utils_Array::value('last_name', $record),
      'email'      => CRM_Utils_Array::value('email', $record),
    ];
    return civicrm_api3('Contact', 'getorcreate', $params);
  }
}
