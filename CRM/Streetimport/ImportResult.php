<?php

define(BE_AIVL_STREETIMPORT_DEBUG, 0);
define(BE_AIVL_STREETIMPORT_INFO,  1);
define(BE_AIVL_STREETIMPORT_WARN,  2);
define(BE_AIVL_STREETIMPORT_ERROR, 3);
define(BE_AIVL_STREETIMPORT_FATAL, 4);
define(BE_AIVL_STREETIMPORT_OFF,   10);

define(LOGGING_THRESHOLD,  BE_AIVL_STREETIMPORT_DEBUG );
define(CONSOLE_THRESHOLD,  BE_AIVL_STREETIMPORT_INFO  );


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

  // stats
  protected $import_success  = array();
  protected $import_fail     = array();
  protected $max_error_level = BE_AIVL_STREETIMPORT_DEBUG;

  // config, for convenience
  protected $config          = NULL;

  // logging thresholds (will be overwritten by config)
  protected $logging_threshold = BE_AIVL_STREETIMPORT_DEBUG;
  protected $console_threshold = BE_AIVL_STREETIMPORT_INFO;
  protected $file_threshold    = BE_AIVL_STREETIMPORT_INFO;

  // simple constructor
  function __construct($context) {
    $this->$config = CRM_Streetimport_Config::singleton();

    // TODO: open log file

    // TODO: get/override log thresholds from config

  }

  /**
   * log the import of an individual record
   *
   * @param $record   the whole record
   * @param $success  true if successfully processed 
   * @param $type     optional string representing the type of record
   * @param $message  optional additional message
   */
  public function logImport($record, $success, $type = '', $message = '') {
    $id = $this->getIDforRecord($record);
    if ($success) {
      if (!isset($this->import_success[$id])) $this->import_success[$id] = NULL;
      $this->logMessage(
        $this->config->translate("Successfully imported record") . " [$type]: $message",
        $record,
        BE_AIVL_STREETIMPORT_DEBUG);
    } else {
      if (!isset($this->import_fail[$id])) $this->import_fail[$id] = NULL;
      if (isset($this->import_success[$id])) unset($this->import_success[$id]);
      $this->logMessage(
        $this->config->translate("Failed to import record") . " [$type]: $message",
        $record,
        BE_AIVL_STREETIMPORT_WARN);
    }
  }

  /**
   * Log a message or error
   */
  public function logMessage($message, $record, $log_level = BE_AIVL_STREETIMPORT_INFO) {
    if ($log_level > $this->logging_threshold) {
      $this->log_entries[] = array(
        'timestamp' => date('Y-m-d h:i:s'),
        'id'        => $this->getIDforRecord($record),
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
      error_log("$log_level: $message");
    }

    // log to file
    if ($log_level >= $this->file_threshold) {
      // TODO: log to file
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
  public function logError($message, $record, $title = NULL) {
    $this->logMessage($message, $record, BE_AIVL_STREETIMPORT_ERROR);

    if ($title==NULL) $title = substr($message, 0, 64);
    $title = $this->config->translate($title);
    $this->createErrorActivity($message, $record, $title);
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
    $this->createErrorActivity($message, $record, $title);
  }

  /**
   * shortcut for logFatal AND throwing an exception (with the same message)
   * @throws Exception
   */
  public function abort($message, $record) {
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
   * This will create an "Error" activity assigned to the admin
   * @see https://github.com/CiviCooP/be.aivl.streetimport/issues/11
   */
  protected function createErrorActivity($message, $record, $title = "Import Error") {
    try {  // AVOID raising another exception leading to this very handler

      // TOOD: replace this ugly workaround:
      $handler = new CRM_Streetimport_StreetRecruitmentRecordHandler($this);

      // create the activity
      $activity_info = array(
        'message' => $this->config->translate($message),
        'title'   => $this->config->translate($title),
        'record'  => $record,
        'id'      => $this->getIDforRecord($record));
      $handler->createActivity(array(
                            'activity_type_id'   => $this->config->getImportErrorActivityType(),
                            'subject'            => $this->config->translate($title),
                            'status_id'          => $this->config->getImportErrorActivityStatusId(),
                            'activity_date_time' => date('YmdHis'),
                            // 'target_contact_id'  => (int) $this->config->getAdminContactID(),
                            'source_contact_id'  => (int) $this->config->getAdminContactID(),
                            'assignee_contact_id'=> (int) $this->config->getAdminContactID(),
                            'details'            => $handler->renderTemplate('activities/ImportError.tpl', $activity_info),
                            ));
      
    } catch (Exception $e) {
      error_log($this->config->translate("Error while creating an activity to report another error").": " . $e->getMessage());
    }
  }

  /**
   * generate a descriptive ID for the given record
   */
  public function getIDforRecord(&$record) {
    return $record['source'] . ':' . $record['id'];
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
}