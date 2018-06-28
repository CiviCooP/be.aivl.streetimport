<?php
/*-------------------------------------------------------------+
| GP StreetImporter Record Handlers                            |
| Copyright (C) 2018 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * GP Styria Handler
 *
 * @see https://redmine.greenpeace.at/issues/1468
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
class CRM_Streetimport_GP_Handler_StyriaRecordHandler extends CRM_Streetimport_GP_Handler_GPRecordHandler {

  protected static $STYRIA_PATTERN = "#(?P<org>[a-zA-Z\-]+)_(?P<project1>\w+)_styria_C(?P<campaign_id>\d{4})_(?P<date>\d{8})_(?P<time>\d{6}).csv$#";

  protected $campaign_id = NULL;

  /**
   * Check if the given handler implementation can process the record
   *
   * @param $record  an array of key=>value pairs
   * @return true or false
   */
  public function canProcessRecord($record, $sourceURI) {
    return $this->parseStyriaFile($sourceURI);
  }

  /**
   * Will try to parse the given name and extract the parameters outlined in TM_PATTERN
   *
   * @return NULL if not matched, data else
   */
  protected function parseStyriaFile($sourceID) {
    if (preg_match(self::$STYRIA_PATTERN, $sourceID, $matches)) {
      return $matches;
    } else {
      return NULL;
    }
  }

  /**
   * process the given record
   *
   * @param $record  an array of key=>value pairs
   * @return true
   * @throws exception if failed
   */
  public function processRecord($record, $sourceURI) {
    $config = CRM_Streetimport_Config::singleton();
    $this->file_name_data = $this->parseStyriaFile($sourceURI);

    $contact_fields = array(
      'anrede'       => 'prefix',
      'titel'        => 'formal_title',
      'vorname'      => 'first_name',
      'nachname'     => 'last_name',
      'geburtsdatum' => 'birth_date',
      'email'        => 'email',
      'strasse'      => 'street_address',
      'plz'          => 'postal_code',
      'ort'          => 'city',
      'telefon1'     => 'phone',
    );

    // compile contact record
    $contact_data = array();
    foreach ($contact_fields as $record_field => $civi_field) {
      if (!empty($record[$record_field])) {
        $contact_data[$civi_field] = trim($record[$record_field]);
      }
    }

    // resolve via XCM
    $contact = civicrm_api3('Contact', 'getorcreate', $contact_data);

    // run it again with the second phone number (if there is one)
    if (!empty($record['telefon2'])) {
      $contact_data['phone'] = trim($record['telefon2']);
      $contact = civicrm_api3('Contact', 'getorcreate', $contact_data);
    }
    $this->logger->logDebug("Contact [{$contact['id']}] created/identified.", $record);

    // create a petition signature
    if (empty($record['petition_signature'])) {
      $this->logger->logWarning("No petition signature.", $record);
      $this->logger->logImport($record, false, $config->translate('TM Contact'));
      return;
    }

    // identify the petition
    $petition = civicrm_api3('Survey', 'get', array(
      'title' => $record['petition_signature']));
    if (empty($petition['id'])) {
      $this->logger->logError("Petition '{$record['petition_signature']}' couldn't be identified.", $record);
      $this->logger->logImport($record, false, $config->translate('TM Contact'));
      return;
    }
    $petition = reset($petition['values']);

    // create signature activity
    civicrm_api3('Activity', 'create', array(
      'source_contact_id'   => CRM_Core_Session::singleton()->getLoggedInContactID(),
      'activity_type_id'    => $petition['activity_type_id'],
      'status_id'           => CRM_Core_OptionGroup::getValue('activity_status', 'Completed'),
      'medium_id'           => 2, // Phone
      'target_contact_id'   => $contact['id'],
      'source_record_id'    => $petition['id'],
      'subject'             => $petition['title'],
      'campaign_id'         => $this->getCampaign($record),
      'activity_date_time'  => $this->getDate($record),
    ));

    $this->logger->logDebug("Petition [{$petition['id']}] signed.", $record);
    $this->logger->logImport($record, true, $config->translate('TM Contact'));
  }

  /**
   *
   */
  protected function getCampaign($record) {
    if ($this->campaign_id === NULL) {
      $this->campaign_id = ''; // set to empty
      $campaign = civicrm_api3('Campaign', 'get', array(
        'id' => $this->file_name_data['campaign_id']));
      if (empty($campaign['id'])) {
        $this->logger->logFatal("Campaign [{$this->file_name_data['campaign_id']}] doesn't exist!", $record);
        // $this->logger->logWarning("Campaign [{$this->file_name_data['campaign_id']}] doesn't exist!", $record);
      } else {
        $this->campaign_id = $campaign['id'];
      }
    }
    return $this->campaign_id;
  }

  /**
   * Extracts the specific activity date for this line
   */
  protected function getDate($record) {
    return $this->file_name_data['date'] . $this->file_name_data['time'];
  }
}
