<?php
/**
 * Abstract class to handle the individual records
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
abstract class CRM_Streetimport_RecordHandler {

  static $_default_handers = NULL;

  public function __construct() {
  }

  /** 
   * Check if the given handler implementation can process the record
   *
   * @param $record  an array of key=>value pairs
   * @return true or false
   */
  protected abstract function canProcessRecord($record);

  /** 
   * process the given record
   *
   * @param $record  an array of key=>value pairs
   * @return true
   * @throws exception if failed
   */
  protected abstract function processRecord($record);

  /**
   * get the default set of handlers
   *
   * @return an array of handler instances
   */
  public static function getDefaultHandlers() {
    if (self::$_default_handers==NULL) {
      self::$_default_handers = array(
        new CRM_Streetimport_StreetRecruitmentRecordHandler(),
        new CRM_Streetimport_WelcomeCallRecordHandler(),
      );
    }
    return self::$_default_handers;
  }

  /** 
   * process all records of the given data source
   *
   * @param $dataSource  a CRM_Streetimport_DataSource object
   * @param $handlers    an array of CRM_Streetimport_RecordHandler objects,
   *                       will default to a stanard handler set (getDefaultHandlers)
   */
  public static function processDataSource($dataSource, $handlers = NULL) {
    if ($handlers==NULL) {
      $handlers = CRM_Streetimport_RecordHandler::getDefaultHandlers();
    }

    $dataSource->reset();
    while ($dataSource->hasNext()) {
      $record = $dataSource->next();
      $record_processed = FALSE;
      foreach ($handlers as $handler) {
        if ($handler->canProcessRecord($record)) {
          $handler->processRecord($record)
          $record_processed = TRUE;

          // TODO: if we want to allow multiple processing, this needs to be commented out:
          break;
        }
      }

      if (!$record_processed) {
        // no handlers found. 
        // TODO: what to do?
      }
    }
  }
  




  /*************************************************
   *      service functions for all handlers       *
   *************************************************/

  /**
   * common service function for all handlers:
   *  create an activity with the given parameters
   */
  protected function createActivity($params) {
    // TODO: implement
  }
}