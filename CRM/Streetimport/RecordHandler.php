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
        $config = CRM_Streetimport_Config::singleton();
        // no handlers found.
        $dataSource->logger->logImport('#' . ($counter + 1), false, '', $config->translate('No handlers found'));
      }
    }
  }
  
  /**
   * Check, if $data[$key] is set and true wrt the configuration
   */
  protected function isTrue($data, $key) {
    $config = CRM_Streetimport_Config::singleton();
    $accepted_yes_values = $config->getAcceptedYesValues();
    return !empty($data[$key]) && in_array($data[$key], $accepted_yes_values);
  }




  /*************************************************
   *      service functions for all handlers       *
   *************************************************/

  /**
   * look up contact
   *
   * @param int $contact_id
   * @param bool $cached  if true, the contact will be kept on cache
   * @return mixed
   */

  protected function getContact($contact_id, $cached = true) {
    if (empty($contact_id) || ((int)  $contact_id)==0) {
      $config = CRM_Streetimport_Config::singleton();
      $this->logger->logWarning($config->translate("Invalid ID for contact lookup").": ".$contact_id);
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

    } catch (CiviCRM_API3_Exception $ex) {
      $config = CRM_Streetimport_Config::singleton();
      $this->logger->logWarning($config->translate("Contact lookup failed").": ".$contact_id);
    }
    
    return NULL;
  }


  /** 
   * Create a new contact with the give data
   *
   * @return array with contact entity
   */
  protected function createContact($contact_data) {
    $config= CRM_Streetimport_Config::singleton();
    // verify data
    if (empty($contact_data['contact_type'])) {
      $this->logger->logError($config->translate("Contact missing contact_type"), $config->translate("Create Contact Error"));
      return NULL;
    }
    if ($contact_data['contact_type'] == 'Organization') {
      if (empty($contact_data['organization_name'])) {
        $this->logger->logError($config->translate("Contact missing organization_name"), $config->translate("Create Contact Error"));
        return NULL;
      }      
    } elseif ($contact_data['contact_type'] == 'Household') {
      if (empty($contact_data['household_name'])) {
        $this->logger->logError($config->translate("Contact missing household_name"), $config->translate("Create Contact Error"));
        return NULL;
      }
    } else {
      if (empty($contact_data['first_name']) && empty($contact_data['last_name'])) {
        $this->logger->logError($config->translate("Contact missing first/last_name"), $config->translate("Create Contact Error"));
        return NULL;
      }
    }

    // format birth date (issue #39)
    if (isset($contact_data['birth_date'])) {
      $contact_data['birth_date'] = $this->formatBirthDate($contact_data['birth_date']);
    }

    // create via API
    try {
      $result  = civicrm_api3('Contact', 'create', $contact_data);
      $contact = $result['values'][$result['id']];
      $this->logger->logDebug($config->translate("Contact created").": ".$contact['id']);
      return $contact;
    } catch (CiviCRM_API3_Exception $ex) {
      $this->logger->logError($ex->getMessage(), $config->translate("Create Contact Error"));
      return NULL;
    }
  }

  /** 
   * Create an activity with the given data
   *
   * @return actvity BAO object
   */
  public function createActivity($data, $assigned_contact_ids=NULL) {
    $config= CRM_Streetimport_Config::singleton();

    // TODO: $data sanitation

    // remark: using BAOs, the API here is somewhat messy
    $activity = CRM_Activity_BAO_Activity::create($data);
    if (empty($activity->id)) {
      $this->logger->logError($config->translate("Couldn't create activity"), $config->translate("Create Activity Error"));
      return NULL;
    }

    // create assignments
    if (!empty($assigned_contact_ids) && is_array($assigned_contact_ids)) {
      foreach ($assigned_contact_ids as $contact_id) {
        $assignment_parameters = array(
          'activity_id'    => $activity->id,
          'contact_id'     => $contact_id,
          'record_type_id' => 1  // ASSIGNEE
        );
        CRM_Activity_BAO_ActivityContact::create($assignment_parameters);        
      }
    }

    $this->logger->logDebug($config->translate("Activity created").": ".$activity->id.": ".$data['subject']);
    return $activity;
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
    $config = CRM_Streetimport_Config::singleton();
    // create via API
    try {
      $email = civicrm_api3('Email', 'create', $data);
      $this->logger->logDebug($config->translate("Email created")." ".$data['email']." ".$config->translate("for contact")." ".$data['contact_id']);
      return $email;
    } catch (CiviCRM_API3_Exception $ex) {
      $this->logger->logError($ex->getMessage(), $config->translate("Create Email Error"));
      return NULL;
    }
  }

  /** 
   * Create an address entity with the given data
   *
   * @return array with address entity
   */
  protected function createAddress($data) {
    $config = CRM_Streetimport_Config::singleton();
    // verify data
    $required_address_attributes = array("city", "street_name", "contact_id");
    foreach ($required_address_attributes as $attribute) {
      if (empty($data[$attribute])) {
        $this->logger->logError($config->translate("Address missing")." ".$attribute, $config->translate("Create Address Error"));
        return NULL;
      }
    }

    // create via API
    try {
      $address = civicrm_api3('Address', 'create', $data);
      $this->logger->logDebug($config->translate("Address created")." ".$address['id']." ".$config->translate("for contact").$data['contact_id']);
      return $address;
    } catch (CiviCRM_API3_Exception $ex) {
      $this->logger->logError($ex->getMessage(), $config->translate("Create Address Error"));
      return NULL;
    }
  }

  /** 
   * Create an phone entity with the given data
   *
   * @return array with phone entity
   */
  protected function createPhone($data) {
    $config = CRM_Streetimport_Config::singleton();
    // verify data
    if (empty($data['phone'])) {
      return NULL;
    }

    // create via API
    try {
      $phone = civicrm_api3('Phone', 'create', $data);
      $this->logger->logDebug($config->translate("Phone created")." ".$data['phone']." ".$config->translate("for contact").$data['contact_id']);
      return $phone;
    } catch (CiviCRM_API3_Exception $ex) {
      $this->logger->logError($ex->getMessage(), $config->translate("Create Phone Error"));
      return NULL;
    }
  }

  /**
   * Method to add contact to given group ID
   *
   * @param int $contactId
   * @param int $groupId
   * @return mixed
   * @access protected
   */
  protected function addContactToGroup($contactId, $groupId) {
    if (empty($contactId) || empty($groupId)) {
      $config = CRM_Streetimport_Config::singleton();
      $this->logger->logError($config->translate('Empty contact_id or group_id, could not add contact to group'));
      return NULL;
    }
    $params = array(
      'contact_id' => $contactId,
      'group_id' => $groupId);
    try {
      $result = civicrm_api3('GroupContact', 'Create', $params);
      return $result;
    } catch (CiviCRM_API3_Exception $ex) {
      $config = CRM_Streetimport_Config::singleton();
      $this->logger->logError($config->translate('Error from API GroupContact Create').': '.$ex->getMessage());
      return NULL;
    }
  }

  /**
   * Method to create membership with given data
   *
   * @param array $membershipData
   * @return mixed
   * @access protected
   */
  protected function createMembership($membershipData) {
    $mandatoryParams = array('contact_id', 'membership_type_id', 'membership_source');
    foreach ($mandatoryParams as $mandatory) {
      if (!isset($membershipData[$mandatory])) {
        $config = CRM_Streetimport_Config::singleton();
        $this->logger->logError($config->translate('Membership not created, mandatory param missing').': '.$mandatory);
        return NULL;
      }
    }
    try {
      $result = civicrm_api3('Membership', 'create', $membershipData);
      return $result;
    } catch (CiviCRM_API3_Exception $ex) {
      $config = CRM_Streetimport_Config::singleton();
      $this->logger->logError($config->translate('Membership not created, error from API Membership Create').': '.$ex->getMessage());
      return NULL;
    }
  }

  /**
   * create a relationship with given data
   */
  protected function createRelationship($relationshipData) {

    $mandatoryParams = array('contact_id_a', 'contact_id_b', 'relationship_type_id');
    foreach ($mandatoryParams as $mandatory) {
      if (empty($relationshipData[$mandatory])) {
        $config = CRM_Streetimport_Config::singleton();
        $this->logger->logError($config->translate('Relationship not created, mandatory param missing').': '.$mandatory);
        return NULL;
      }
    }
    if (!isset($relationshipData['start_date'])) {
      $relationshipData['start_date'] = date('YmdHis');
    }
    try {
      $result = civicrm_api3('Relationship', 'Create', $relationshipData);
      return $result;
    } catch (CiviCRM_API3_Exception $ex) {
      $config = CRM_Streetimport_Config::singleton();
      $this->logger->logError($config->translate('Relationship not created, error from API Relationship Create').': '.$ex->getMessage());
      return NULL;
    }
  }

  /**
   * uses SMARTY to render a template
   *
   * @return string 
   */
  public function renderTemplate($template_path, $vars) {
    $smarty = CRM_Core_Smarty::singleton();

    // first backup orgininal variables, since smarty instance is a singleton
    $oldVars = $smarty->get_template_vars();
    $backupFrame = array();
    foreach ($vars as $key => $value) {
      $key = str_replace(' ', '_', $key);
      $backupFrame[$key] = isset($oldVars[$key]) ? $oldVars[$key] : NULL;
    }

    // then assign new variables
    foreach ($vars as $key => $value) {
      $key = str_replace(' ', '_', $key);
      $smarty->assign($key, $value);
    }

    // create result
    $result =  $smarty->fetch($template_path);

    // reset smarty variables
    foreach ($backupFrame as $key => $value) {
      $key = str_replace(' ', '_', $key);
      $smarty->assign($key, $value);
    }

    return $result;
  }

  /**
   * Method to format the birth date
   *
   * @param mixed $birthDate
   * @return string
   * $access protected
   */
  protected function formatBirthDate($birthDate) {
    $correctDate = new DateTime(CRM_Streetimport_Utils::formatCsvDate($birthDate));
    return $correctDate->format('d-m-Y');
  }
}