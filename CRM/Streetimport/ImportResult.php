<?php

define(DEBUG, 0);
define(INFO,  1);
define(WARN,  2);
define(ERROR, 3);
define(FATAL, 4);
define(OFF,   10);

define(LOGGING_THRESHOLD,        DEBUG );
define(ERROR_CONSOLE_THRESHOLD,  DEBUG );


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
  protected $max_error_level = DEBUG;

  /**
   * log the import of an individual record
   *
   * @param $id       unique id string for the record (e.g. line number)
   * @param $success  true if successfully processed 
   * @param $type     optional string representing the type of record
   * @param $message  optional additional message
   */
  public function logImport($id, $success, $type = '', $message = '') {
    if ($success) {
      $this->logMessage("Successfully imported record '$id': $message.", DEBUG, $type);
      if (!isset($this->import_success[$id])) $this->import_success[$id] = NULL;
    } else {
      $this->logMessage("Failed to import record '$id': $message.", WARN, $type);
      if (!isset($this->import_fail[$id])) $this->import_fail[$id] = NULL;
      if (isset($this->import_success[$id])) unset($this->import_success[$id]);
    }
  }

  /**
   * Log a message or error
   */
  public function logMessage($message, $error_level = INFO, $type='') {
    if ($error_level > LOGGING_THRESHOLD) {
      $this->log_entries[] = array(
        'timestamp' => date('Y-m-d h:i:s'),
        'log_level' => $error_level,
        'type'      => $type,
        'message'   => $message,
        );      
    }

    if ($error_level > $this->max_error_level) {
      $this->max_error_level = $error_level;
    }

    if ($error_level >= ERROR_CONSOLE_THRESHOLD) {
      error_log("$error_level: $message");
    }
  }

  /**
   * shortcut for logMessage($message, DEBUG)
   */
  public function logDebug($message) {
    $this->logMessage($message, DEBUG);
  }

  /**
   * shortcut for logMessage($message, WARN)
   */
  public function logWarning($message) {
    $this->logMessage($message, WARN);
  }

  /**
   * shortcut for logMessage($message, ERROR)
   */
  public function logError($message) {
    $this->logMessage($message, ERROR);
  }

  /**
   * shortcut for logMessage($message, ERROR)
   * @param abort  if true, an exception will be raised, stopping the execution
   */
  public function logFatal($message) {
    $this->logMessage($message, FATAL);
  }

  /**
   * shortcut for logFatal AND throwing an exception (with the same message)
   * @throws Exception
   */
  public function abort($message) {
    $this->logFatal($message);
    // TODO: use a specific exception type?
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
    if ($this->max_error_level >= FATAL) {
      $fatal_messages = $this->getEntriesWithLevel(FATAL, true);
      $message = "FATAL ERROR(S): " . implode(', ', $fatal_messages) . ". $counts";
      return civicrm_api3_create_error($message);
    } else {
      return civicrm_api3_create_success($counts);
    }
  }
}