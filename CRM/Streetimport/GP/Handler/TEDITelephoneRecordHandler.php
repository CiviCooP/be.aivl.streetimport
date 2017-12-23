<?php
/*-------------------------------------------------------------+
| GP StreetImporter Record Handlers                            |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
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
class CRM_Streetimport_GP_Handler_TEDITelephoneRecordHandler extends CRM_Streetimport_GP_Handler_TMRecordHandler {

  /** cached ids */
  protected $_contact_called_activity_id = NULL;
  protected $_contact_phone_changed_id = NULL;

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
    $config = CRM_Streetimport_Config::singleton();
    $this->file_name_data = $this->parseTmFile($sourceURI);

    $contact_id = $this->getContactID($record);
    $phone_ids  = $this->getPhoneIDs($record, $contact_id);

    if (empty($contact_id)) {
      return $this->logger->logError("Contact [{$record['id']}] couldn't be identified.", $record);
    }

    switch ($record['Status']) {
      case TM_PHONE_NOT_CALLED:
        // NOTHING TO DO HERE
        $this->logger->logDebug("Contact [{$contact_id}] was NOT called", $record);
        break;

      case TM_PHONE_CALLED:
        // just log an activity
        $this->createContactCalledActivity($contact_id, $record);
        $this->logger->logDebug("Contact [{$contact_id}] was called", $record);
        break;

      case TM_PHONE_CHANGED:
        // update phone numbers
        if (empty($phone_ids)) {
          $this->createManualUpdateActivity($contact_id, "Phone number has changed to: " . $this->getPhoneNumber($record), $record);
          return $this->logger->logError("Phone [{$record['TelID']}] couldn't be identified.", $record);
        } else {
          // update all identified phones (usually one)
          foreach ($phone_ids as $phone_id) {
            civicrm_api3('Phone', 'create', array(
              'id'    => $phone_id,
              'phone' => $this->getPhoneNumber($record)));
          }
          $this->createPhoneUpdatedActivity(TM_PHONE_CHANGED, $contact_id, $record);
          $this->logger->logDebug("Phone number of contact [{$contact_id}] was changed", $record);
        }
        break;

      case TM_PHONE_DELETED:
        // delete phone numbers
        if (empty($phone_ids)) {
          return $this->logger->logError("Phone [{$record['TelID']}] couldn't be identified.", $record);
        } else {
          // delete all identified phones (usually one)
          $deleted_count = 0;
          foreach ($phone_ids as $phone_id) {
            try {
              $this->logger->logDebug("Deleting phone [{$phone_id}] of contact [{$contact_id}]...", $record);
              civicrm_api3('Phone', 'delete', array('id' => $phone_id));
              $deleted_count += 1;
            } catch (Exception $e) {
              $this->logger->logWarning("Couldn't delete phone [{$phone_id}] of contact [{$contact_id}]!", $record);
              // $this->logger->logError("Phone [{$phone_id}] of contact [{$contact_id}] couldn't be deleted", $record);
            }
          }

          if ($deleted_count > 0) {
            $this->createPhoneUpdatedActivity(TM_PHONE_DELETED, $contact_id, $record);
            $this->logger->logDebug("{$deleted_count} phone number(s) of contact [{$contact_id}] deleted", $record);
          }
        }
        break;

      case TM_PHONE_NEW:
        // add phone numbers
        civicrm_api3('Phone', 'create', array(
          'contact_id'       => $contact_id,
          'phone'            => $this->getPhoneNumber($record),
          'phone_type_id'    => $config->getPhonePhoneTypeId(),
          'location_type_id' => $config->getLocationTypeId()));
          $this->createPhoneUpdatedActivity(TM_PHONE_NEW, $contact_id, $record);
          $this->logger->logDebug("Phone number of contact [{$contact_id}] was added", $record);
        break;

      default:
        return $this->logger->logError("Undefined status [{$record['Status']}]. Row ignored.", $record);
    }

    $this->logger->logImport($record, true, $config->translate('TM Phone'));
  }


  protected function getPhoneNumber($record) {
    return "+{$record['TelLand']} {$record['TelVorwahl']} {$record['TelNummer']}";
  }

  protected function getPhoneType($record) {
    $config = CRM_Streetimport_Config::singleton();

    // TODO: deduce mobile from area code ($record['TelVorwahl'])?
    return $config->getPhonePhoneTypeId();
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

  /**
   * Create a "Contact Called" activity
   */
  public function createContactCalledActivity($contact_id, $record) {
    $this->config = CRM_Streetimport_Config::singleton();

    // first get contact called activity type
    if ($this->_contact_called_activity_id == NULL) {
      $this->_contact_called_activity_id = CRM_Core_OptionGroup::getValue('activity_type', 'contact_called', 'name');
      if (empty($this->_contact_called_activity_id)) {
        // couldn't be found => create
        $activity = civicrm_api3('OptionValue', 'create', array(
          'option_group_id' => 'activity_type',
          'name'            => 'contact_called',
          'label'           => $this->config->translate('Contact Called'),
          'is_active'       => 1
          ));
        $activity = civicrm_api3('OptionValue', 'getsingle', array('id' => $activity['id'], 'return' => 'value'));
        $this->_contact_called_activity_id = $activity['value'];
      }
    }

    // NOW create the activity
    $activityParams = array(
      'activity_type_id'    => $this->_contact_called_activity_id,
      'subject'             => $this->config->translate('Contact Called'),
      'status_id'           => $this->config->getActivityCompleteStatusId(),
      'activity_date_time'  => $this->getDate($record),
      'campaign_id'         => $this->getCampaignID($record),
      'source_contact_id'   => $this->config->getCurrentUserID(),
      'target_contact_id'   => (int) $contact_id,
      // 'assignee_contact_id' => (int) $this->config->getFundraiserContactID(),
      'details'             => $this->config->translate('Called on number ') . $this->getPhoneNumber($record),
    );

    $this->createActivity($activityParams, $record);
  }


  /**
   * Create a "Contact Called" activity
   */
  public function createPhoneUpdatedActivity($type, $contact_id, $record) {
    $this->config = CRM_Streetimport_Config::singleton();

    // calculate subject based on type
    $new_number = $this->getPhoneNumber($record);
    switch ($type) {
      case TM_PHONE_CHANGED:
        $subject = $this->config->translate('Contact Phone Updated');
        $details = sprintf($this->config->translate('Corrected number %s to %s'), $new_number, $new_number);
        break;

      case TM_PHONE_DELETED:
        $subject = $this->config->translate('Contact Phone Deleted');
        $details = sprintf($this->config->translate('Deleted phone number %s'), $new_number);
        break;

      case TM_PHONE_NEW:
        $subject = $this->config->translate('New Phone Number');
        $details = sprintf($this->config->translate('Importer phone number %s'), $new_number);
        break;

      default:
        return $this->logger->logError("Undefined status [{$record['Status']}]. Row ignored.", $record);
    }

    $this->createContactUpdatedActivity($contact_id, $subject, $details, $record);
  }

  /**
   * get the activity date from the file name
   */
  protected function getDate($record) {
    // there's no date in the file
    return date('YmdHis');

    // this is unreliable:
    // return $this->file_name_data['date'] . $this->file_name_data['time'];
  }

}
