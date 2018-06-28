<?php
/*-------------------------------------------------------------+
| GP StreetImporter Record Handlers                            |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

define('REPETITION_FRAME_DECEASED',   "2 years");

/**
 * Processes PostRetour barcode lists (GP-331)
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
class CRM_Streetimport_GP_Handler_PostRetourRecordHandler extends CRM_Streetimport_GP_Handler_GPRecordHandler {

  /** file name / reference patterns as defined in GP-331 */
  protected static $FILENAME_PATTERN       = '#^RTS_(?P<category>[a-zA-Z\-]+)(_[0-9]*)?[.][a-zA-Z]+$#';
  protected static $REFERENCE_PATTERN_NEW  = '#^0?(?P<campaign_id>[0-9]{4})C(?P<contact_id>[0-9]{9})$#';
  protected static $REFERENCE_PATTERN_OLD  = '#^(?P<campaign_id>[0-9]{4})(?P<contact_id>[0-9]{6,9})$#';
  protected static $REFERENCE_PATTERN_1296 = '#^1(?P<campaign_id>[0-9]{5})(?P<contact_id>[0-9]{9})$#';

  /** stores the parsed file name */
  protected $file_name_data = 'not parsed';

  /**
   * Check if the given handler implementation can process the record
   *
   * @param $record  an array of key=>value pairs
   * @return true or false
   */
  public function canProcessRecord($record, $sourceURI) {
    if ($this->file_name_data === 'not parsed') {
      $this->file_name_data = $this->parseRetourFile($sourceURI);
    }
    return $this->file_name_data != NULL;
  }


  /**
   * process the given record
   *
   * @param $record  an array of key=>value pairs
   * @return true
   * @throws exception if failed
   */
  public function processRecord($record, $sourceURI) {
    $config          = CRM_Streetimport_Config::singleton();
    $category        = $this->getCategory();
    $reference       = $this->getReference($record);
    $campaign_id     = $this->getCampaignID($record);
    $contact_id      = $this->getContactID($record);

    if (!$campaign_id) {
      return $this->logger->logImport($record, FALSE, 'RTS', "Couldn't identify campaign for reference '{$reference}'");
    }

    if (!$contact_id) {
      return $this->logger->logImport($record, FALSE, 'RTS', "Couldn't identify contact for reference '{$reference}'");
    }

    $primary_address = $this->getPrimaryAddress($contact_id, $record);

    switch (strtolower($category)) {
      case 'unused':
      case 'incomplete':
      case 'badcode':
      case 'rejected':
      case 'notretrieved':
      case 'other':
      case 'unknown':
      case 'moved':
        $lastRTS = $this->findLastRTS($contact_id, $record);
        if ($lastRTS) {
          if (!$this->addressChangeRecordedSince($contact_id, $lastRTS['activity_date_time'], $record)) {
            $this->increaseRTSCounter($primary_address, $record);
          } else {
            // address had been changed
            // TODO: reset counter?
          }
        } else {
          $this->increaseRTSCounter($primary_address, $record);
        }
        $this->addRTSActvity($contact_id, $category, $record);
        break;

      case 'deceased':
        $lastDeceased = $this->findLastRTS($contact_id, $record, REPETITION_FRAME_DECEASED, 'deceased');
        if ($lastDeceased) {
          // there is another 'deceased' event in the last two years

          // should still increase RTS counter (see GP-1593)
          $this->increaseRTSCounter($primary_address, $record);

          // set the deceased date
          civicrm_api3('Contact', 'create', array(
              'id'            => $contact_id,
            // 'is_deleted'  => 1, // Marco said (27.03.2017): don't delete right away
              'deceased_date' => $this->getDate($record),
              'is_deceased'   => 1));

        } else {
          $this->increaseRTSCounter($primary_address, $record);
        }
        $this->addRTSActvity($contact_id, $category, $record);
        break;

      default:
        $this->logger->abort("Unknown type '{$category}!", $record);
    }

    $this->logger->logImport($record, true, $config->translate('DD Contact'));
  }

  /**
   * get the contact's primary address ID
   */
  protected function getPrimaryAddress($contact_id, $record) {
    $config      = CRM_Streetimport_Config::singleton();
    $rts_counter = $config->getGPCustomFieldKey('rts_counter');
    $addresses = civicrm_api3('Address', 'get', array(
      'is_primary'   => 1,
      'contact_id'   => $contact_id,
      'return'       => "{$rts_counter},contact_id,id",
      'option.limit' => 1));
    if ($addresses['count'] == 1) {
      return reset($addresses['values']);
    } else {
      $this->logger->logError("Primary address for contact [{$contact_id}] not found. Couldn't update RTS counter.", $record);
      return NULL;
    }
  }

  /**
   * increase the RTS counter at the contact's primary address
   */
  protected function increaseRTSCounter($primary, $record) {
    $config      = CRM_Streetimport_Config::singleton();
    $rts_counter = $config->getGPCustomFieldKey('rts_counter');
    if ($primary) {
      $new_count = CRM_Utils_Array::value($rts_counter, $primary, 0) + 1;
      civicrm_api3('Address', 'create', array(
        'id'         => $primary['id'],
        $rts_counter => $new_count));
      $this->logger->logDebug("Increased RTS counter for contact [{$primary['contact_id']}] to {$new_count}.", $record);
    }
  }

  /**
   * Add a new RTS activity
   */
  protected function addRTSActvity($contact_id, $category, $record) {
    civicrm_api3('Activity', 'create', array(
      'activity_type_id'    => CRM_Streetimport_GP_Config::getResponseActivityType(),
      'target_id'           => $contact_id,
      'subject'             => $this->getRTSSubject($category),
      'activity_date_time'  => date('YmdHis'),
      'campaign_id'         => $this->getCampaignID($record),
      'status_id'           => 2, // completed
      ));
  }

  /**
   * Find the last RTS activity
   *
   * @return array last RTS activity of the given TYPE or NULL
   */
  protected function findLastRTS($contact_id, $record, $search_frame = NULL, $category = NULL) {
    $activity_type_id = CRM_Streetimport_GP_Config::getResponseActivityType();

    $SUBJECT_CLAUSE = 'AND (TRUE OR activity.subject = %2)'; // probably need to have the %2 token..
    $subject = '';
    if ($category) {
      $SUBJECT_CLAUSE = 'AND activity.subject = %2';
      $subject = $this->getRTSSubject($category);
    }

    $SEARCH_FRAME_CLAUSE = '';
    if ($search_frame) {
      $SEARCH_FRAME_CLAUSE = "AND activity.activity_date_time >= " . date("YmdHis", strtotime("now - {$search_frame}"));
    }

    $last_rts_id = CRM_Core_DAO::singleValueQuery("
    SELECT activity.id
    FROM civicrm_activity activity
    LEFT JOIN civicrm_activity_contact ac ON ac.activity_id = activity.id 
    WHERE activity.activity_type_id = %1
      {$SUBJECT_CLAUSE}
      AND ac.contact_id = %3
      {$SEARCH_FRAME_CLAUSE}
    ORDER BY activity.activity_date_time DESC
    LIMIT 1;", array(
         1 => array($activity_type_id, 'Integer'),
         2 => array($subject,          'String'),
         3 => array($contact_id,       'Integer')));

    if ($last_rts_id) {
      $this->logger->logDebug("Found RTS ({$category}): [{$last_rts_id}]", $record);
      return civicrm_api3('Activity', 'getsingle', array('id' => $last_rts_id));
    } else {
      $this->logger->logDebug("No RTS ({$category}) found.", $record);
      return NULL;
    }
  }

  /**
   * get category
   */
  protected function getCategory() {
    return $this->file_name_data['category'];
  }

  /**
   * Check if there has been a change since
   */
  protected function addressChangeRecordedSince($contact_id, $minimum_date, $record) {
    // check if logging is enabled
    $logging = new CRM_Logging_Schema();
    if (!$logging->isEnabled()) {
      $this->logger->logDebug("Logging not enabled, cannot determine whether records have changed.", $record);
      return FALSE;
    }

    // query the logging DB
    $dsn = defined('CIVICRM_LOGGING_DSN') ? DB::parseDSN(CIVICRM_LOGGING_DSN) : DB::parseDSN(CIVICRM_DSN);
    $relevant_attributes = array('id','is_primary','street_address','supplemental_address_1','supplemental_address_2','city','postal_code','country_id','log_date');
    $attribute_list = implode(',', $relevant_attributes);
    $current_status = array();
    $query = CRM_Core_DAO::executeQuery("SELECT {$attribute_list} FROM {$dsn['database']}.log_civicrm_address WHERE contact_id={$contact_id}");
    while ($query->fetch()) {
      // generate record
      $record = array();
      $record_id = $query->id;
      foreach ($relevant_attributes as $attribute) {
        $record[$attribute] = $query->$attribute;
      }

      // process record
      if (!isset($current_status[$record_id])) {
        // this is a new address
        $current_status[$record_id] = $record;

      } else {
        // compare with the old record
        $old_record = $current_status[$record_id];
        $changed = FALSE;
        foreach ($relevant_attributes as $attribute) {
          if ($attribute == 'log_date') continue; // that doesn't matter
          if ($old_record[$attribute] != $record[$attribute]) {
            $this->logger->logDebug("Address attribute '{$attribute}' changed (on {$record['log_date']})", $record);
            $changed = TRUE;
            break;
          }
        }

        // this is the new current
        $current_status[$record_id] = $record;

        if ($changed) {
          // there is a change, check if it's in the time frame we're looking for
          if (strtotime($record['log_date']) >= strtotime($minimum_date)) {
            $this->logger->logDebug("Address change relevant (date range)", $record);
            return TRUE;
          }
        }
      }
    }

    return FALSE;
  }

  /**
   * get the correct subject for the activity
   */
  protected function getRTSSubject($category) {
    switch (strtolower($category)) {
      case 'unused':
        return 'Abgabestelle unbenutzt';
      case 'incomplete':
        return 'Anschrift ungenügend';
      case 'badcode':
        return 'falsche PLZ';
      case 'rejected':
        return 'nicht angenommen';
      case 'notretrieved':
        return 'nicht behoben';
      case 'rejected':
        return 'nicht angenommen';
      case 'other':
        return 'sonstiges';
      case 'unknown':
        return 'unbekannt';
      case 'moved':
        return 'verzogen';
      case 'deceased':
        return 'verstorben';
      default:
        return 'sonstiges';
      }
  }

  /**
   * Will try to parse the given name and extract the parameters outlined in TM_PATTERN
   *
   * @return NULL if not matched, data else
   */
  protected function parseRetourFile($sourceID) {
    if (preg_match(self::$FILENAME_PATTERN, basename($sourceID), $matches)) {
      return $matches;
    } else {
      return NULL;
    }
  }

  /**
   * get the reference
   */
  protected function getReference($record) {
    return CRM_Utils_Array::value('scanned_code', $record);
  }

  /**
   * Extract the campaign ID from the Kundennummer
   */
  protected function getCampaignID($record) {
    $reference = $this->getReference($record);
    if (preg_match(self::$REFERENCE_PATTERN_NEW, $reference, $matches)) {
      $campaign_id = ltrim($matches['campaign_id'], '0');
      return (int) $campaign_id;

    } elseif (preg_match(self::$REFERENCE_PATTERN_1296, $reference, $matches)) {
      $campaign_id = ltrim($matches['campaign_id'], '0');
      return (int) $campaign_id;

    } elseif (preg_match(self::$REFERENCE_PATTERN_OLD, $reference, $matches)) {
      // look up campaign
      $campaign = civicrm_api3('Campaign', 'get', array(
        'external_identifier' => "AKTION-{$matches['campaign_id']}",
        'return'              => 'id'));
      if (!empty($campaign['id'])) {
        return (int) $campaign['id'];
      } else {
        $this->logger->logError("Couldn't find campaign 'AKTION-{$matches['campaign_id']}'.", $record);
        return NULL;
      }
    } else {
      $this->logger->logError("Couldn't parse reference '{$reference}'.", $record);
      return NULL;
    }
  }

  /**
   * Extract the contact ID from the Kundennummer
   */
  protected function getContactID($record) {
    $reference = $this->getReference($record);
    if (preg_match(self::$REFERENCE_PATTERN_NEW, $reference, $matches)) {
      // use identity tracker
      $contact_id = ltrim($matches['contact_id'], '0');
      return $this->resolveContactID($contact_id, $record);

    } elseif (preg_match(self::$REFERENCE_PATTERN_1296, $reference, $matches)) {
      // use identity tracker
      $contact_id = ltrim($matches['contact_id'], '0');
      return $this->resolveContactID($contact_id, $record);

    } elseif (preg_match(self::$REFERENCE_PATTERN_OLD, $reference, $matches)) {
      // use identity tracker
      $contact_id = ltrim($matches['contact_id'], '0');
      return $this->resolveContactID("IMB-{$contact_id}", $record, 'external');

    } else {
      $this->logger->logError("Couldn't parse reference '{$reference}'.", $record);
      return NULL;
    }
  }
}
