<?php
/**
 * Abstract class to handle the individual records
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
abstract class CRM_Streetimport_RecordHandler {

  /**
   * stores the result/logging object
   */ 
  protected $logger = NULL;

  /** for cached contact lookup **/
  static protected $contact_cache = array();



  public function __construct($logger) {
    $this->logger = $logger;
  }

  /** 
   * Check if the given handler implementation can process the record
   *
   * @param $record  an array of key=>value pairs
   * @return true or false
   */
  public abstract function canProcessRecord($record);

  /** 
   * process the given record
   *
   * @param $record  an array of key=>value pairs
   * @return true
   * @throws exception if failed
   */
  public abstract function processRecord($record);

  /**
   * get the default set of handlers
   *
   * @return an array of handler instances
   */
  public static function getDefaultHandlers($logger) {
    return array(
      new CRM_Streetimport_StreetRecruitmentRecordHandler($logger),
      new CRM_Streetimport_WelcomeCallRecordHandler($logger),
    );
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
      $handlers = CRM_Streetimport_RecordHandler::getDefaultHandlers($dataSource->logger);
    }

    $dataSource->reset();
    $counter = 0;
    while ($dataSource->hasNext()) {
      $record = $dataSource->next();
      $counter += 1;
      $record_processed = FALSE;
      foreach ($handlers as $handler) {
        if ($handler->canProcessRecord($record)) {
          $handler->processRecord($record);
          $record_processed = TRUE;

          // TODO: if we want to allow multiple processing, this needs to be commented out:
          break;
        }
      }

      if (!$record_processed) {
        // no handlers found. 
        $this->logger->logImport('#' . ($counter + 1), false, '', 'No handers found.');
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
    $this->logger->logError("createActivity not implemented!");
  }

  /**
   * look up contact
   *
   * @param $cached  if true, the contact will be keept on cache
   * @return array with contact entity
   */
  protected function getContact($contact_id, $cached = true) {
    if (empty($record['Recruiting organization ID']) || ((int)  $record['Recruiting organization ID'])==0) {
      $this->logger->logWarn("Invalid ID for contact lookup: '{$contact_id}'");
      return NULL;
    }

    if ($cached && isset(self::$contact_cache[$contact_id])) {
      return self::$contact_cache[$contact_id];
    }

    try {
      $contact = civicrm_api3('Contact', 'getsingle', array('id' => (int) $contact_id));

      if ($cached) {
        self::$contact_cache[$contact_id] = $contact;
      }

      return $contact;

    } catch (CiviCRM_API3_Exception $e) {
      $this->logger->logWarn("Contact lookup failed: '{$contact_id}'");
    }
    
    return NULL;
  }


  /** 
   * Create a new contact with the give data
   *
   * @return array with contact entity
   */
  protected function createContact($contact_data, $cached = true) {
     // TODO: implement
    $this->logger->logError("createActivity not implemented!");   
  }
}