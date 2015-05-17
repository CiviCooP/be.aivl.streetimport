<?php
/**
 * Class following Singleton pattern for specific extension configuration
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 30 April 2015
 * @license AGPL-3.0
 */
class CRM_Streetimport_Config {
  private static $_singleton;
  private $resourcesPath = null;

  protected $recruiterContactSubType = array();
  protected $supplierContactSubType = array();
  protected $recruiterRelationshipType = array();
  protected $streetRecruitmentActivityType = array();
  protected $welcomeCallActivityType = array();
  protected $followUpCallActivityType= array();
  protected $importErrorActivityType = array();

  /**
   * Constructor method
   */
  function __construct() {
    $settings = civicrm_api3('Setting', 'Getsingle', array());
    $this->resourcesPath = $settings['extensionsDir'].'/be.aivl.streetimport/resources/';

    $this->setContactSubTypes();
    $this->setRelationshipTypes();
    $this->setActivityTypes();
  }

  /**
   * Method to retrieve import error activity type data
   *
   * @param string $key
   * @return mixed
   * @access public
   */
  public function getImportErrorActivityType($key= 'id' ) {
    return $this->importErrorActivityType[$key];
  }

  /**
   * Method to retrieve follow up call activity type data
   *
   * @param string $key
   * @return mixed
   * @access public
   */
  public function getFollowUpCallActivityType($key= 'id' ) {
    return $this->followUpCallActivityType[$key];
  }

  /**
   * Method to retrieve welcome call activity type data
   *
   * @param string $key
   * @return mixed
   * @access public
   */
  public function getWelcomeCallActivityType($key= 'id' ) {
    return $this->welcomeCallActivityType[$key];
  }

  /**
   * Method to retrieve street recruitment activity type data
   *
   * @param string $key
   * @return mixed
   * @access public
   */
  public function getStreetRecruitmentActivityType($key= 'id' ) {
    return $this->streetRecruitmentActivityType[$key];
  }

  /**
   * Method to retrieve recruiter relatioinship type data
   *
   * @param string $key
   * @return mixed
   * @access public
   */
  public function getRecruiterRelationshipType($key= 'id' ) {
    return $this->recruiterRelationshipType[$key];
  }


  /**
   * Method to retrieve supplier contact sub type data
   *
   * @param string $key
   * @return mixed
   * @access public
   */
  public function getSupplierContactSubType($key= 'id' ) {
    return $this->supplierContactSubType[$key];
  }

  /**
   * Method to retrieve recruiter contact sub type data
   *
   * @param string $key
   * @return mixed
   * @access public
   */
  public function getRecruiterContactSubType($key= 'id' ) {
    return $this->recruiterContactSubType[$key];
  }

  /**
   * Singleton method
   *
   * @return CRM_Streetimport_Config
   * @access public
   * @static
   */
  public static function singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Streetimport_Config();
    }
    return self::$_singleton;
  }

  /**
   * Method to create or get activity types
   *
   * @throws Exception when resource file could not be loaded
   */
  protected function setActivityTypes() {
    $jsonFile = $this->resourcesPath.'activity_types.json';
    if (!file_exists($jsonFile)) {
      throw new Exception('Could not load activity types configuration file for extension, contact your system administrator!');
    }
    $activityTypesJson = file_get_contents($jsonFile);
    $activityTypes = json_decode($activityTypesJson, true);
    foreach ($activityTypes as $activityTypeName => $activityTypeLabel) {
      $propertyName = $activityTypeName.'ActivityType';
      $activityType = CRM_Streetimport_Utils::getActivityTypeWithName($activityTypeName);
      if (!$activityType) {
        $params = array(
          'name' => $activityTypeName,
          'label' => $activityTypeLabel,
          'is_active' => 1,
          'is_reserved' => 1);
        $activityType = CRM_Streetimport_Utils::createActivityType($params);
      }
      $this->$propertyName = $activityType;
    }
  }

  /**
   * Method to create or get relationship types
   *
   * @throws Exception when resource file could not be loaded
   */
  protected function setRelationshipTypes() {
    $jsonFile = $this->resourcesPath.'relationship_types.json';
    if (!file_exists($jsonFile)) {
      throw new Exception('Could not load relationship types configuration file for extension, contact your system administrator!');
    }
    $relationshipTypesJson = file_get_contents($jsonFile);
    $relationshipTypes = json_decode($relationshipTypesJson, true);
    foreach ($relationshipTypes as $relationName => $params) {
      $propertyName = $relationName.'RelationshipType';
      $relationshipType = CRM_Streetimport_Utils::getRelationshipTypeWithName($params['name_a_b']);
      if (!$relationshipType) {
        $relationshipType = CRM_Streetimport_Utils::createRelationshipType($params);
      }
      $this->$propertyName = $relationshipType;
    }
  }

  /**
   * Method to create or get contact sub types
   *
   * @throws Exception when resource file could not be loaded
   */
  protected function setContactSubTypes() {
    $jsonFile = $this->resourcesPath.'contact_sub_types.json';
    if (!file_exists($jsonFile)) {
      throw new Exception('Could not load contact sub types configuration file for extension, contact your system administrator!');
    }
    $contactTypesJson = file_get_contents($jsonFile);
    $contactSubTypes = json_decode($contactTypesJson, true);
    foreach ($contactSubTypes as $params) {
      $propertyName = $params['name'].'ContactSubType';
      $contactSubType = CRM_Streetimport_Utils::getContactSubTypeWithName($params['name']);
      if (!$contactSubType) {
        $contactSubType = CRM_Streetimport_Utils::createContactSubType($params);
      }
      $this->$propertyName = $contactSubType;
    }
  }
}