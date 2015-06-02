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
   * look up contact
   *
   * @param $cached  if true, the contact will be keept on cache
   * @return array with contact entity
   */
  protected function getContact($contact_id, $cached = true) {
    if (empty($contact_id) || ((int)  $contact_id)==0) {
      $this->logger->logWarn("Invalid ID for contact lookup: '{$contact_id}'");
      return NULL;
    }

    $contact_id = (int) $contact_id;
    if ($cached && isset(self::$contact_cache[$contact_id])) {
      return self::$contact_cache[$contact_id];
    }

    try {
      $contact = civicrm_api3('Contact', 'getsingle', array('id' => $contact_id));

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
  protected function createContact($contact_data) {
    // verify data
    if (empty($contact_data['contact_type'])) {
      $this->logger->logError("Contact missing contact_type");
      return NULL;
    }
    if ($contact_data['contact_type'] == 'Organization') {
      if (empty($contact_data['organization_name'])) {
        $this->logger->logError("Contact missing organization_name");
        return NULL;
      }      
    } else {
      if (empty($contact_data['first_name']) && empty($contact_data['last_name'])) {
        $this->logger->logError("Contact missing first/last_name");
        return NULL;
      }
    }

    // create via API
    try {
      return civicrm_api3('Contact', 'create', $contact_data);
    } catch (CiviCRM_API3_Exception $e) {
      $this->logger->logError($ex->getMessage());
      return NULL;
    }
  }

  /** 
   * Create an activity with the given data
   *
   * @return array with activity entity
   */
  protected function createActivity($data) {
    // TODO: implement
    $this->logger->logError("createActivity not implemented!");
  }

  /** 
   * Create an email entity with the given data
   *
   * @return array with email entity
   */
  protected function createEmail($data) {
    // verify data
    if (empty($data['email'])) {
      return NULL;
    }

    // create via API
    try {
      return civicrm_api3('Email', 'create', $data);
    } catch (CiviCRM_API3_Exception $e) {
      $this->logger->logError($ex->getMessage());
      return NULL;
    }
  }

  /** 
   * Create an address entity with the given data
   *
   * @return array with address entity
   */
  protected function createAddress($data) {
    // TODO: implement
    $this->logger->logError("createActivity not implemented!");
  }

  /** 
   * Create an phone entity with the given data
   *
   * @return array with phone entity
   */
  protected function createPhone($data) {
    // verify data
    if (empty($data['phone'])) {
      return NULL;
    }

    // create via API
    try {
      return civicrm_api3('Phone', 'create', $data);
    } catch (CiviCRM_API3_Exception $e) {
      $this->logger->logError($ex->getMessage());
      return NULL;
    }
  }
}