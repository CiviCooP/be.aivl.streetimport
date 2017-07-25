<?php

define('BE_AIVL_STREETIMPORT_DEBUG', 0);
define('BE_AIVL_STREETIMPORT_INFO',  1);
define('BE_AIVL_STREETIMPORT_WARN',  2);
define('BE_AIVL_STREETIMPORT_ERROR', 3);
define('BE_AIVL_STREETIMPORT_FATAL', 4);
define('BE_AIVL_STREETIMPORT_OFF',   10);

define('LOGGING_THRESHOLD',  BE_AIVL_STREETIMPORT_DEBUG );
define('CONSOLE_THRESHOLD',  BE_AIVL_STREETIMPORT_INFO  );


/**
 * This class will collect import data, such as
 * - log messages
 * - error messages
 * - statistics
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
class CRM_Streetimport_ImportResult {

  // logs
  protected $log_entries     = array();
  protected $log_file        = NULL;
  protected $log_file_path   = NULL;

  // stats
  protected $import_success  = array();
  protected $import_fail     = array();
  protected $max_error_level = BE_AIVL_STREETIMPORT_DEBUG;

  // config, for convenience
  protected $config          = NULL;

  // logging thresholds (will be overwritten by config)
  protected $logging_threshold = BE_AIVL_STREETIMPORT_DEBUG;
  protected $console_threshold = BE_AIVL_STREETIMPORT_INFO;
  protected $file_threshold    = BE_AIVL_STREETIMPORT_DEBUG;

  // simple constructor
  function __construct() {
    $this->config = CRM_Streetimport_Config::singleton();

    // TODO: get/override log thresholds from config?
  }

  /**
   * log the import of an individual record
   *
   * @param $record   the whole record
   * @param $success  true if successfully processed
   * @param $type     optional string representing the type of record
   * @param $message  optional additional message
   */
  public function logImport($record, $success, $type = 'UNKNOWN', $message = '') {
    $record_id = $this->getIDforRecord($record);
    if ($success) {
      $this->import_success[$record_id] = NULL;
      $this->logMessage(
        $this->config->translate("Successfully imported record of type")." ". $type,
        $record,
        BE_AIVL_STREETIMPORT_DEBUG);
    } else {
      $this->import_fail[$record_id] = NULL;
      if (isset($this->import_success[$record_id])) {
        unserialize($this->import_success[$record_id]);
      }

      $this->logMessage(
        $this->config->translate("Failed to import record") . " [$type]: $message",
        $record,
        BE_AIVL_STREETIMPORT_WARN);
    }
  }

  /**
   * Log a message or error
   */
  public function logMessage($message, $record = NULL, $log_level = BE_AIVL_STREETIMPORT_INFO) {
    $record_id = $this->getIDforRecord($record);
    $log_level_string = $this->resolveLogLevel($log_level);

    if ($log_level > $this->logging_threshold) {
      $this->log_entries[] = array(
        'timestamp' => date('Y-m-d H:i:s'),
        'id'        => $record_id,
        'log_level' => $log_level,
        'message'   => $message,
        );
    }

    // keep track of the 'worst' message so far
    if ($log_level > $this->max_error_level) {
      $this->max_error_level = $log_level;
    }

    // log to console
    if ($log_level >= $this->console_threshold) {
      error_log(sprintf("%s [%s]: %s", $log_level_string, $record_id, $message));
    }

    // log to file
    if ($this->log_file && $log_level >= $this->file_threshold) {
      fputs($this->log_file, date('Y-m-d H:i:s'));
      fputs($this->log_file, ' ');
      fputs($this->log_file, $log_level_string);
      fputs($this->log_file, ' [');
      fputs($this->log_file, $record_id);
      fputs($this->log_file, ']: ');
      fputs($this->log_file, $message);
      fputs($this->log_file, "\n");
    }
  }

  /**
   * shortcut for logMessage($message, BE_AIVL_STREETIMPORT_DEBUG)
   */
  public function logDebug($message, $record) {
    $this->logMessage($message, $record, BE_AIVL_STREETIMPORT_DEBUG);
  }

  /**
   * shortcut for logMessage($message, BE_AIVL_STREETIMPORT_WARN)
   */
  public function logWarning($message, $record) {
    $this->logMessage($message, $record, BE_AIVL_STREETIMPORT_WARN);
  }

  /**
   * shortcut for logMessage($message, BE_AIVL_STREETIMPORT_ERROR)
   * Will also create an activity
   */
  public function logError($message, $record, $title = NULL, $errorType = 'Info') {
    $this->logMessage($message, $record, BE_AIVL_STREETIMPORT_ERROR);

    if ($title==NULL) $title = substr($message, 0, 64);
    $title = $this->config->translate($title);
    $this->createErrorActivity($message, $record, $title, $errorType);
  }

  /**
   * shortcut for logMessage($message, BE_AIVL_STREETIMPORT_ERROR)
   * Will also create an activity
   * @param abort  if true, an exception will be raised, stopping the execution
   */
  public function logFatal($message, $record, $title = NULL) {
    $this->logMessage($message, $record, BE_AIVL_STREETIMPORT_FATAL);

    if ($title==NULL) $title = substr($message, 0, 64);
    $title = $this->config->translate($title);
    $this->createErrorActivity($message, $record, $title, 'Error');
  }

  /**
   * shortcut for logFatal AND throwing an exception (with the same message)
   * @throws Exception
   */
  public function abort($message, $record = NULL) {
    $this->logFatal($message, $record);
    // TODO: use a specific exception type? add more Information?
    throw new Exception($message);
  }

  /**
   * get all entries with at least the given log level
   *
   * @param $only_messages  if true, the result will only contain the messages,
   *                          otherwise the full entries
   * @return array of all matching entries
   */
  public function getEntriesWithLevel($log_level, $only_messages = false) {
    $entries = array();
    foreach ($this->log_entries as $log_entry) {
      if ($log_entry['log_level'] >= $log_level) {
        if ($only_messages) {
          $entries[] = $log_entry['message'];
        } else {
          $entries[] = $log_entry;
        }
      }
    }
    return $entries;
  }

  /**
   * create a API v3 return array
   */
  public function toAPIResult() {
    $counts = count($this->import_success) . " of " . (count($this->import_success)+count($this->import_fail)) . " records imported.";
    if ($this->max_error_level >= BE_AIVL_STREETIMPORT_FATAL) {
      $fatal_messages = $this->getEntriesWithLevel(BE_AIVL_STREETIMPORT_FATAL, true);
      $message = $this->config->translate("FATAL ERROR(S)").": ". implode(', ', $fatal_messages) . ". $counts";
      return civicrm_api3_create_error($message);
    } else {
      return civicrm_api3_create_success($counts);
    }
  }

  /**
   * This will create an "Error" activity assigned to the admin in the config
   *
   * @param $message
   * @param $record
   * @param $title
   * @param $errorType
   * @see https://github.com/CiviCooP/be.aivl.streetimport/issues/11
   */
  protected function createErrorActivity($message, $record, $title = "Import Error", $errorType) {
    try {  // AVOID raising another exception leading to this very handler

      // create the activity
      $activity_info = array(
        'message' => $this->config->translate($message),
        'title'   => $this->config->translate($title),
        'record'  => $record,
        'id'      => $this->getIDforRecord($record));

      $activityParams = array(
        'activity_type_id'   => $this->config->getImportErrorActivityType(),
        'subject'            => $this->config->translate($title),
        'status_id'          => $this->config->getImportErrorActivityStatusId(),
        'activity_date_time' => date('YmdHis'),
        'source_contact_id'  => (int) $this->config->getAdminContactID(),
        'assignee_contact_id'=> (int) $this->config->getAdminContactID(),
        'details'            => CRM_Streetimport_Utils::renderTemplate('activities/ImportError.tpl', $activity_info),
      );


    // TODO: 'hook' needed
    //   try {
    //     $donorContactId = CRM_Streetimport_Utils::getContactIdFromDonorId($record['DonorID'], $record['Recruiting organization ID']);
    //     if (!empty($donorContactId)) {
    //       $activityParams['target_contact_id'] = $donorContactId;
    //     }
    //   } catch (Exception $e) {
		  // // ignore the error;
    //   }
      $errorActivity = CRM_Streetimport_Utils::createActivity($activityParams, $record);
      if ($errorActivity == NULL) {
        error_log($this->config->translate("Error while creating an activity to report another error"));
      }
      // TODO: set custom fields in activity
      // $this->setCustomErrorType($errorActivity->id, $errorType);
    } catch (Exception $e) {
      error_log($this->config->translate("Exception while creating an activity to report another error").": " . $e->getMessage());
    }
  }

  /**
   * Method to set error type for error activity (see issue 76)
   * @param $activityId
   * @param $errorType
   */
  private function setCustomErrorType($activityId, $errorType) {
    $config = CRM_Streetimport_Config::singleton();
    $importErrorCustomGroup = $config->getImportErrorCustomGroup();
    $importErrorCustomFields = $config->getImportErrorCustomFields();
    foreach ($importErrorCustomFields as $customFieldId => $customField) {
      if ($customField['name'] == "error_type") {
        $errorTypeColumnName = $customField['column_name'];
      }
    }
    if (!isset($errorTypeColumnName)) {
      CRM_Core_Error::fatal('Could not find a custom field for error_type');
    } else {
      $errorExists = CRM_Core_DAO::singleValueQuery('SELECT COUNT(*) FROM ' . $importErrorCustomGroup['table_name']
        . ' WHERE entity_id = %1', array(1 => array($activityId, 'Integer')));
      if ($errorExists > 0) {
        $query = "UPDATE " . $importErrorCustomGroup['table_name'] . " SET " . $errorTypeColumnName . " = %1
          WHERE entity_id = %2";
      } else {
        $query = "INSERT INTO " . $importErrorCustomGroup['table_name'] . " SET " . $errorTypeColumnName
          . " = %1, entity_id = %2";
      }
      $params = array(
        1 => array($config->translate($errorType), 'String'),
        2 => array($activityId, 'Integer')
      );
      CRM_Core_DAO::executeQuery($query, $params);
    }
  }

  /**
   * generate a descriptive ID for the given record
   */
  public function getIDforRecord($record) {
    if (!empty($record['source']) && !empty($record['line_nr'])) {
      return $record['source'] . ', on line ' . $record['line_nr'];
    } elseif (!empty($record['source']) && !empty($record['id'])) {
      return $record['source'] . ', on line ' . $record['id'];
    } else {
      return 'NO_REF';
    }
  }

  /**
   * Resolve log level
   */
  public function resolveLogLevel($level) {
    switch ($level) {
      case BE_AIVL_STREETIMPORT_DEBUG:
        return $this->config->translate('DEBUG');
      case BE_AIVL_STREETIMPORT_INFO:
        return $this->config->translate('INFO');
      case BE_AIVL_STREETIMPORT_WARN:
        return $this->config->translate('WARN');
      case BE_AIVL_STREETIMPORT_ERROR:
        return $this->config->translate('ERROR');
      case BE_AIVL_STREETIMPORT_FATAL:
        return $this->config->translate('FATAL');
      default:
        return $this->config->translate('UNKNOWN');
    }
  }

  /**
   * Creates a new log file 'next to' the input file,
   * replacing the file extension with "-YYYYmmddHHiiss.log"
   **/
  public function setLogFile($file) {
    // if another file exists, close that first...
    if ($this->log_file) {
      fclose($this->log_file);
      $this->log_file      = NULL;
      $this->log_file_path = NULL;
    }

    if ($file) {
      // open a log file
      $this->log_file      = fopen($file, 'w');
      $this->log_file_path = $file;
      if (!$this->log_file) {
        $this->logFatal("Cannot open log file '$file'.", NULL);
        $this->log_file_path = NULL;
      }
    }
  }

  /**
   * returns the currently set log file path
   */
  public function getLogFile() {
    return $this->log_file_path;
  }
}
