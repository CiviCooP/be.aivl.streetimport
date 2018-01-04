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

  /** STATIC cached contact lookup **/
  static protected $contact_cache = array();

  /** for cached entity lookup **/
  private $entity_cache = array();

  /** for cached tagging lookup **/
  private $tagname_to_tagid = array();

  /** for cached membership lookup **/
  private $membership_types = NULL;

  /** cache for resolveContactID function */
  private $_identity_tracker_cache = array();


  public function __construct($logger) {
    $this->logger = $logger;
  }

  /**
   * Check if the given handler implementation can process the record
   *
   * @param $record array   of key=>value pairs
   * @param $source string  source identifier, e.g. file name
   *
   * @return true or false
   */
  public abstract function canProcessRecord($record, $sourceURI);

  /**
   * process the given record
   *
   * @param $record array   of key=>value pairs
   * @param $source string  source identifier, e.g. file name
   *
   * @return true
   * @throws exception if failed
   */
  public abstract function processRecord($record, $sourceURI);

  /**
   * This event is triggered BEFORE the processing of a datasource starts
   *
   * @param $sourceURI string  source identifier, e.g. file name
   */
  public function startProcessing($sourceURI) {
    // NOTHING TO DO, OVERRIDE IF REQUIRED
  }

  /**
   * This event is triggered AFTER the last record of a datasource has been processed
   *
   * @param $sourceURI string  source identifier, e.g. file name
   */
  public function finishProcessing($sourceURI) {
    // NOTHING TO DO, OVERRIDE IF REQUIRED
  }


  /**
   * process all records of the given data source
   *
   * @param $dataSource  a CRM_Streetimport_DataSource object
   * @param $handlers    an array of CRM_Streetimport_RecordHandler objects,
   *                       will default to a stanard handler set (getDefaultHandlers)
   */
  public static function processDataSource($dataSource, $handlers = NULL) {
    $config = CRM_Streetimport_Config::singleton();
    $allowProcessingByMultipleHandlers = $config->allowProcessingByMultipleHandlers();
    $stopProcessingIfNoHanderFound     = $config->stopProcessingIfNoHanderFound();

    if ($handlers==NULL) {
      $handlers = $config->getHandlers($dataSource->logger);
    }

    $dataSource->reset();
    // exit;
    $counter = 0;
    $sourceURI = $dataSource->getURI();

    // send start event
    foreach ($handlers as $handler) {
      $handler->startProcessing($sourceURI);
    }

    while ($dataSource->hasNext()) {
      $record = $dataSource->next();
      // var_dump($record);
      $counter += 1;
      $record_processed = FALSE;
      foreach ($handlers as $handler) {
        if ($handler->canProcessRecord($record, $sourceURI)) {
          $handler->processRecord($record, $sourceURI);
          $record_processed = TRUE;

          if (!$allowProcessingByMultipleHandlers) {
            break;
          }
        }
      }

      if (!$record_processed) {
        // no handlers found -> BAIL! (whole file will not execute any further)
        if ($stopProcessingIfNoHanderFound) {
          return $dataSource->logger->abort($config->translate('No handlers found'), $record);
        } else {
          $dataSource->logger->logImport($record, false, '', $config->translate('No handlers found'));
        }
      }
    }

    // send finish event
    foreach ($handlers as $handler) {
      $handler->finishProcessing($sourceURI);
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
  protected function getContact($contact_id, $record, $cached = true) {
    if (empty($contact_id) || ((int)  $contact_id)==0) {
      $config = CRM_Streetimport_Config::singleton();
      $this->logger->logWarning($config->translate("Invalid ID for contact lookup").": ".$contact_id, $record);
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
      $this->logger->logWarning($config->translate("Contact lookup failed").": ".$contact_id, $record);
    }

    return NULL;
  }

  /**
   * Resolves a CiviCRM contact ID ($type = 'internal') or
   *  an external_identifier ($type = 'external') using the
   *  "identity tracker extension" (if installed).
   * The lookup results are cached
   */
  protected function resolveContactID($contact_id, $record, $type = 'internal') {
    if (empty($contact_id)) {
      return NULL;
    }

    // check cache
    if (isset($this->_identity_tracker_cache[$type][$contact_id])) {
      return $this->_identity_tracker_cache[$type][$contact_id];
    }

    $current_contact_id = '';

    if (function_exists('identitytracker_civicrm_install')) {
      // identitytracker is enabled
      $contacts = civicrm_api3('Contact', 'findbyidentity', array(
        'identifier_type' => $type,
        'identifier'      => $contact_id));
      if ($contacts['count'] == 1) {
        $current_contact_id = $contacts['id'];
      }

    } else {
      // identitytracker is NOT enabled
      switch ($type) {
        case 'internal':
          $current_contact_id = $contact_id;
          break;

        case 'external':
          // look up contact
          $search = civicrm_api3('Contact', 'get', array(
            'external_identifier' => $contact_id,
            'return'              => 'id'));
          if (!empty($search['id'])) {
            $current_contact_id = $contact_id;
          }
          break;

        default:
          $this->logger->logError("Unknown reference type '{$type}'.", $record);
          break;
      }
    }

    // store the result
    $this->_identity_tracker_cache[$type][$contact_id] = $current_contact_id;
    return $current_contact_id;
  }

  /**
   * Load a full CiviCRM entitiy by ID
   *
   # @throws API exceptions if entity not found or not unique
   */
  protected function loadEntity($entity_type, $entity_id) {
    if (empty($entity_id)) return NULL;

    $cache_key = $entity_type . (int) $entity_id;
    if (isset($this->entity_cache[$cache_key])) {
      return $this->entity_cache[$cache_key];
    }

    // cache miss: load entity
    $this->entity_cache[$cache_key] = civicrm_api3($entity_type, 'getsingle', array('id' => $entity_id));
    return $this->entity_cache[$cache_key];
  }


  /**
   * Create a new contact with the give data
   *
   * @return array with contact entity
   */
  protected function createContact($contact_data, $record) {
    $config= CRM_Streetimport_Config::singleton();
    // verify data
    if (empty($contact_data['contact_type'])) {
      $this->logger->logError($config->translate("Contact missing contact_type"), $record, $config->translate("Create Contact Error"), "Error");
      return NULL;
    }
    if ($contact_data['contact_type'] == 'Organization') {
      if (empty($contact_data['organization_name'])) {
        $this->logger->logError($config->translate("Contact missing organization_name"), $record, $config->translate("Create Contact Error"), "Error", 'Error');
        return NULL;
      }
    } elseif ($contact_data['contact_type'] == 'Household') {
      if (empty($contact_data['household_name'])) {
        $this->logger->logError($config->translate("Contact missing household_name"), $record, $config->translate("Create Contact Error"), "Error");
        return NULL;
      }
    } else {
      $firstName = trim($contact_data['first_name']);
      $lastName = trim($contact_data['last_name']);
      if (empty($firstName) && empty($lastName)) {
        $this->logger->logError($config->translate("Donor missing first_name and last_name").": ".$record['DonorID'],
          $record, $config->translate("Create Contact Error"), "Error");
        return NULL;
      }
      if (empty($firstName)) {
        $this->logger->logError($config->translate("Donor missing first_name, contact created without first name")
          .": donor ".$record['DonorID'], $record, $config->translate("Missing Data For Donor"), "Info");
      }
      if (empty($lastName)) {
        $this->logger->logError($config->translate("Donor missing last_name, contact created without last name")
          .": donor ".$record['DonorID'], $record, $config->translate("Missing Data For Donor"), "Info");
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
      $this->addContactToGroup($contact['id'], $config->getDedupeContactsGroupID(), $record);
      $this->logger->logDebug($config->translate("Contact created").": ".$contact['id'], $record);
      return $contact;
    } catch (CiviCRM_API3_Exception $ex) {
      $this->logger->logError($ex->getMessage(), $record, $config->translate("Create Contact Error"), "Error");
      return NULL;
    }
  }

  /**
   * Create an activity with the given data
   *
   * @return activity BAO object
   */
  public function createActivity($data, $record, $assigned_contact_ids=NULL) {
    $config = CRM_Streetimport_Config::singleton();

    // create activity
    $activity = CRM_Streetimport_Utils::createActivity($data, $record, $assigned_contact_ids);

    if ($activity==NULL) {
      $this->logger->logError($config->translate("Couldn't create activity"), $record, $config->translate("Create Activity Error"), "Error");
      return NULL;
    } else {
      $this->logger->logDebug($config->translate("Activity created").": ".$activity->id.": ".$data['subject'], $record);
      return $activity;
    }
  }

  /**
   * Create an email entity with the given data
   *
   * @return array with email entity
   */
  protected function createEmail($data, $record) {
    // verify data
    if (empty($data['email'])) {
      return NULL;
    }
    $config = CRM_Streetimport_Config::singleton();
    // create via API
    try {
      $email = civicrm_api3('Email', 'create', $data);
      $this->logger->logDebug($config->translate("Email created")." ".$data['email']." ".$config->translate("for contact")." ".$data['contact_id'], $record);
      return $email;
    } catch (CiviCRM_API3_Exception $ex) {
      $this->logger->logError($ex->getMessage(), $record, $config->translate("Create Email Error"));
      return NULL;
    }
  }

  /**
   * Create an address entity with the given data
   *
   * @return array with address entity
   */
  protected function createAddress($data, $record) {
    $config = CRM_Streetimport_Config::singleton();
    // verify data
    $required_address_attributes = array("city", "street_name", "contact_id");
    foreach ($required_address_attributes as $attribute) {
      if (empty($data[$attribute])) {
        $this->logger->logError($config->translate("Address missing")." ".$attribute, $record, $config->translate("Create Address Error"));
        return NULL;
      }
    }

    // create via API
    try {
      $address = civicrm_api3('Address', 'create', $data);
      $this->logger->logDebug($config->translate("Address created")." ".$address['id']." ".$config->translate("for contact").$data['contact_id'], $record);
      return $address;
    } catch (CiviCRM_API3_Exception $ex) {
      $this->logger->logError($ex->getMessage(), $record, $config->translate("Create Address Error"));
      return NULL;
    }
  }

  /**
   * Create an phone entity with the given data
   *
   * @return array with phone entity
   */
  protected function createPhone($data, $record) {
    $config = CRM_Streetimport_Config::singleton();
    // verify data
    if (empty($data['phone'])) {
      return NULL;
    }

    // create via API
    try {
      $phone = civicrm_api3('Phone', 'create', $data);
      $this->logger->logDebug($config->translate("Phone created")." ".$data['phone']." ".$config->translate("for contact")." ".$data['contact_id'], $record);
      return $phone;
    } catch (CiviCRM_API3_Exception $ex) {
      $this->logger->logError($ex->getMessage(), $record, $config->translate("Create Phone Error"));
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
  protected function addContactToGroup($contactId, $groupId, $record) {
    if (empty($contactId) || empty($groupId)) {
      $config = CRM_Streetimport_Config::singleton();
      $this->logger->logError($config->translate('Empty contact_id or group_id, could not add contact to group'), $record);
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
      $this->logger->logError($config->translate('Error from API GroupContact Create').': '.$ex->getMessage(), $record);
      return NULL;
    }
  }

  /**
   * Method to remove a contact from  a given group ID
   *
   * @param int $contactId
   * @param int $groupId
   * @return mixed
   * @access protected
   */
  protected function removeContactFromGroup($contactId, $groupId, $record) {
    if (empty($contactId) || empty($groupId)) {
      $config = CRM_Streetimport_Config::singleton();
      $this->logger->logError($config->translate('Empty contact_id or group_id, could not remove contact from group'), $record);
      return NULL;
    }
    try {
      return civicrm_api3('GroupContact', 'Create', array(
        'contact_id' => $contactId,
        'group_id'   => $groupId,
        'status'     => 'Removed'));
    } catch (CiviCRM_API3_Exception $ex) {
      $config = CRM_Streetimport_Config::singleton();
      $this->logger->logError($config->translate('Error from API GroupContact Create').': '.$ex->getMessage(), $record);
      return NULL;
    }
  }

  /**
   * Tag a contact. If the tag doesn't exist, it will be created
   */
  protected function tagContact($contact_id, $tag_name, $record) {
    if (empty($contact_id)) return NULL;

    $config = CRM_Streetimport_Config::singleton();
    if (!isset($this->tagname_to_tagid[$tag_name])) {
      // look up tag
      $tag = civicrm_api3('Tag', 'get', array('name' => $tag_name));
      if (empty($tag['id'])) {
        // tag doesn't exist yet, create it
        $tag = civicrm_api3('Tag', 'create', array(
          'name' => $tag_name,
          'description' => $config->translate('Created by StreetImport')));
      }
      $this->tagname_to_tagid[$tag_name] = $tag['id'];
    }

    civicrm_api3('EntityTag', 'create', array(
      'entitiy_table' => 'civicrm_contact',
      'entity_id'     => (int) $contact_id,
      'tag_id'        => $this->tagname_to_tagid[$tag_name],
      ));
    $this->logger->logDebug("Contact [{$contact_id}] tagged as '{$tag_name}'", $record);
  }

  /**
   * Method to create membership with given data
   *
   * @param array $membershipData
   * @param int $recruiterId
   * @return mixed
   * @access protected
   */
  protected function createMembership($membershipData, $recruiterId, $record) {
    $mandatoryParams = array('contact_id', 'membership_type_id', 'membership_source');
    foreach ($mandatoryParams as $mandatory) {
      if (!isset($membershipData[$mandatory])) {
        $config = CRM_Streetimport_Config::singleton();
        $this->logger->logError($config->translate('Membership not created, mandatory param missing').': '.$mandatory, $record);
        return NULL;
      }
    }
    try {
      $result = civicrm_api3('Membership', 'create', $membershipData);

      // issue #48 - change source contact of membership signup activity
      $this->updateMembershipActivity($result['values'][$result['id']]['contact_id'], $result['id'], $recruiterId);

      return $result;
    } catch (CiviCRM_API3_Exception $ex) {
      $config = CRM_Streetimport_Config::singleton();
      $this->logger->logError($config->translate('Membership not created, error from API Membership Create').': '.$ex->getMessage(), $record);
      return NULL;
    }
  }

  /**
   * create a relationship with given data
   *
   * @param $relationshipData
   * @param $record
   * @return array|null
   */
  protected function createRelationship($relationshipData, $record) {

    $mandatoryParams = array('contact_id_a', 'contact_id_b', 'relationship_type_id');
    foreach ($mandatoryParams as $mandatory) {
      if (empty($relationshipData[$mandatory])) {
        $config = CRM_Streetimport_Config::singleton();
        $this->logger->logError($config->translate('Relationship not created, mandatory param missing').': '.$mandatory, $record);
        return NULL;
      }
    }
    if (!isset($relationshipData['start_date'])) {
      $relationshipData['start_date'] = date('YmdHis');
    }
    $validParams = $this->validateRelationshipData($relationshipData, $record);
    if ($validParams) {
      try {
        $result = civicrm_api3('Relationship', 'Create', $relationshipData);
        return $result;
      } catch (CiviCRM_API3_Exception $ex) {
        $config = CRM_Streetimport_Config::singleton();
        $this->logger->logError($config->translate('Relationship not created, error from API Relationship Create') . ': ' . $ex->getMessage(), $record);
        return NULL;
      }
    } else {
      return NULL;
    }
  }

  /**
   * Method to validate relationship data before creating one so we can do specific error reporting
   *
   * @param $relationshipData
   * @param $record
   * @return bool
   */
  protected function validateRelationshipData($relationshipData, $record) {
    // check if relationship type id exists
    $config = CRM_Streetimport_Config::singleton();
    try {
      $relationShipType = civicrm_api3('RelationshipType', 'Getsingle',
        array('id' => $relationshipData['relationship_type_id']));
    } catch (CiviCRM_API3_Exception $ex) {
      $this->logger->logError($config->translate('Relationship not created between contact')
        .' '.$relationshipData['contact_id_a'].$config->translate('and contact').' '.$relationshipData['contact_id_b']
        .', '.$config->translate('could not find relation_ship_type_id').' '
        .$relationshipData['relationship_type_id'].'. '.$config->translate('error from API')
        .' Relationship Create: '.$ex->getMessage(), $record, $config->translate('Relationship not created'));
      return FALSE;
    }
    // check if relationship type is set for contact (sub) types
    $contactAParams = array('id' => $relationshipData['contact_id_a'], 'return' => 'contact_sub_type');
    $contactASubTypes = civicrm_api3('Contact', 'Getvalue', $contactAParams);
    if (empty($contactASubTypes) || !in_array($relationShipType['contact_sub_type_a'], $contactASubTypes)) {
      $this->logger->logError($config->translate('Relationship not created between contact')
        .' '.$relationshipData['contact_id_a'].$config->translate('and contact').' '.$relationshipData['contact_id_b']
        .', '.$config->translate('contact sub type of contact').' '.$relationshipData['contact_id_a']
        .' '.$config->translate('conflicts with relationship type set up'), $record,
        $config->translate('Relationship not created'));
      return FALSE;
    }
    $contactBParams = array('id' => $relationshipData['contact_id_b'], 'return' => 'contact_sub_type');
    $contactBSubTypes = civicrm_api3('Contact', 'Getvalue', $contactBParams);
    if (empty($contactBSubTypes) || !in_array($relationShipType['contact_sub_type_b'], $contactBSubTypes)) {
      $this->logger->logError($config->translate('Relationship not created between contact')
        .$relationshipData['contact_id_a'].$config->translate('and contact').$relationshipData['contact_id_b']
        .', '.$config->translate('contact sub type of contact').' '.$relationshipData['contact_id_b']
        .' '.$config->translate('conflicts with relationship type set up'), $record,
        $config->translate('Relationship not created'));
      return FALSE;
    }
    return TRUE;
  }

  /**
   * uses SMARTY to render a template (compatibility function)
   *
   * @return string
   */
  public function renderTemplate($template_path, $vars) {
    return CRM_Streetimport_Utils::renderTemplate($template_path, $vars);
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

  /**
   * Method to update the source contact of the membership signup activity
   * (issue #48 on GitHub)
   *
   * @param int $contactId
   * @param int $membershipId
   * @param int $recruiterId
   */
  protected function updateMembershipActivity($contactId, $membershipId, $recruiterId) {
    // find activity with contactId as target, membershipId as source_record_id and activity type for membership signup
    $activityTypeId = CRM_Streetimport_Utils::getActivityTypeWithName('Membership Signup');
    $activityParams = array(
      'activity_type_id' => $activityTypeId['value'],
      'is_current_revision' => 1,
      'is_deleted' => 0,
      'source_record_id' => $membershipId,
      'target_contact_id' => $contactId);
    try {
      $foundActivities = civicrm_api3('Activity', 'Get', $activityParams);
      foreach ($foundActivities['values'] as $activity) {
        $query = 'UPDATE civicrm_activity_contact SET contact_id = %1 WHERE activity_id = %2 AND record_type_id = %3';
        $params = array(
          1 => array($recruiterId, 'Integer'),
          2 => array($activity['id'], 'Integer'),
          3 => array(2, 'Integer'));
        CRM_Core_DAO::executeQuery($query, $params);
      }
    } catch (CiviCRM_API3_Exception $ex) {}
  }

  /**
   * Create note entity with the given contact
   *
   * @return note data
   */
  protected function createNote($contact_id, $subject, $text, $record) {
    $config = CRM_Streetimport_Config::singleton();
    // verify data
    if (empty($contact_id)) {
      return NULL;
    }

    if (empty($subject)) {
      $subject = $config->translate('Note');
    }

    // create via API
    try {
      $note = civicrm_api3('Note', 'create', array(
        'entity_id'    => $contact_id,
        'entity_table' => 'civicrm_contact',
        'subject'      => $subject,
        'note'         => $text
        ));
      $this->logger->logDebug($config->translate("Note created")." ".$config->translate("for contact")." ".$contact_id, $record);
      return $note;
    } catch (CiviCRM_API3_Exception $ex) {
      $this->logger->logError($ex->getMessage(), $record, $config->translate("Create Note Error"));
      return NULL;
    }
  }

  /**
   * get all membership types (cached)
   */
  protected function getMembershipTypes() {
    if ($this->membership_types === NULL) {
      $types_query = civicrm_api3('MembershipType', 'get', array('option.limit' => 0));
      $this->membership_types = $types_query['values'];
    }
    return $this->membership_types;
  }
}
