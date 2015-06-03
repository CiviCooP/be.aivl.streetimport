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

  protected $resourcesPath = null;
  protected $aivlLegalName = null;
  protected $importSettings = array();
  protected $recruiterContactSubType = array();
  protected $supplierContactSubType = array();
  protected $recruiterRelationshipType = array();
  protected $streetRecruitmentActivityType = array();
  protected $welcomeCallActivityType = array();
  protected $followUpCallActivityType= array();
  protected $importErrorActivityType = array();
  protected $streetRecruitmentCustomGroup = array();
  protected $streetRecruitmentCustomFields = array();
  protected $welcomeCallCustomGroup = array();
  protected $welcomeCallCustomFields = array();
  protected $externalDonorIdCustomGroup = array();
  protected $externalDonorIdCustomFields = array();
  protected $streetRecruitmentImportType = null;
  protected $welcomeCallImportType = null;
  protected $acceptedYesValues = array();
  protected $newsLetterGroupId = null;
  protected $membershipTypeId = null;

  /**
   * Constructor method
   */
  function __construct() {
    $settings = civicrm_api3('Setting', 'Getsingle', array());
    $this->resourcesPath = $settings['extensionsDir'].'/be.aivl.streetimport/resources/';
    $this->aivlLegalName = 'Amnesty International Vlaanderen vzw';
    $this->streetRecruitmentImportType = 1;
    $this->welcomeCallImportType = 2;
    $this->acceptedYesValues = array('J', 'j', 'Ja', 'ja', 'true', 'waar', 'yes', 'Yes');

    $this->setContactSubTypes();
    $this->setRelationshipTypes();
    $this->setActivityTypes();
    $this->setCustomData();
    $this->setImportSettings();
    // TODO: implement: $this->setNewsletterGroup();
    // TODO: implement: $this->setMembershipType();
  }

  /**
   * Method to retrieve import settings
   *
   * @return array
   * @access public
   */
  public function getImportSettings() {
    return $this->importSettings;
  }

  /**
   * Method to get the street recruitment import type
   *
   * @return int
   * @access public
   */
  public function getStreetRecruitmentImportType() {
    return $this->streetRecruitmentImportType;
  }

  /**
   * Method to get the welcome call import type
   *
   * @return int
   * @access public
   */
  public function getWelcomeCallImportType() {
    return $this->welcomeCallImportType;
  }

  /**
   * Method to retrieve legal name AIVL
   *
   * @return string
   * @access public
   */
  public function getAivlLegalName() {
    return $this->aivlLegalName;
  }

  /**
   * This method offers translation of strings, such as
   *  - activity subjects
   *  - ...
   *
   * @return string
   * @access public
   */
  public function translate($string) {
    // TODO: @Erik how should this happen?
    return ts($string);
  }

  /**
   * Method to get the default activity status for street recruitment
   *
   * @return mixed
   */
  public function getStreetRecruitmentActivityStatusId() {
    return CRM_Streetimport_Utils::getActivityStatusIdWithName('completed');
  }

  /**
   * Method to get the default activity status for welcome call
   *
   * @return int
   * @access public
   */
  public function getWelcomeCallActivityStatusId() {
    return CRM_Streetimport_Utils::getActivityStatusIdWithName('completed');
  }

  /**
   * Method to get the default activity status for import error
   *
   * @return int
   * @access public
   */
  public function getImportErrorActivityStatusId() {
    return CRM_Streetimport_Utils::getActivityStatusIdWithName('scheduled');
  }

  /**
   * Method to get the default activity status for follow up call
   *
   * @return int
   * @access public
   */
  public function getFollowUpCallActivityStatusId() {
    return CRM_Streetimport_Utils::getActivityStatusIdWithName('scheduled');
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
   * Method to retrieve recruiter relationship type data
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
   * Method to retrieve the newsletter group id
   *
   * @return integer
   * @access public
   */
  public function getNewsletterGroupID() {
    // @TODO: get newsletter group from AIVL (ErikH)

    return $this->newsLetterGroupId;
  }

  /**
   * Method to retrieve the membership type ID
   *
   * @return integer
   * @access public
   */
  public function getMembershipTypeID() {
    // @TODO: get membership type from AIVL (ErikH)
    return $this->membershipTypeId;
  }

  /**
   * Method to retrieve a list of values, 
   * that will be interpreted as TRUE/POSITIVE/YES
   *
   * @return array
   * @access public
   */
  public function getAcceptedYesValues() {
    return $this->acceptedYesValues;
  }

  /**
   * extract the SDD parameters type, frequency_unit, frequency_interval
   *  from the given, localised parameter
   *
   * @param $unit_ln10  the localised unit string
   * @return array with the given values or NULL if failed
   */
  public function extractSDDtype($unit_ln10) {
    $unit_ln10 = strtolower(trim($unit_ln10));
    
    if ($unit_ln10 == 'maand') {
      $sdd_type = array('type' => 'RCUR', 'frequency_unit' => 'month', 'frequency_interval' => 1);
    } elseif ($unit_ln10 == 'kwartaal') {
      $sdd_type = array('type' => 'RCUR', 'frequency_unit' => 'month', 'frequency_interval' => 3);
    } elseif ($unit_ln10 == 'half jaar') {
      $sdd_type = array('type' => 'RCUR', 'frequency_unit' => 'month', 'frequency_interval' => 6);
    } elseif ($unit_ln10 == 'jaar') {
      $sdd_type = array('type' => 'RCUR', 'frequency_unit' => 'year', 'frequency_interval' => 1);
    } elseif ($unit_ln10 == 'eenmalig') {
      $sdd_type = array('type' => 'OOFF', 'frequency_unit' => NULL, 'frequency_interval' => NULL);
    } else {
      $sdd_type = NULL;
    }
    return $sdd_type;
  }
  
  /**
   * Method to retrieve the default fundraiser contact 
   * (assignee of activities)
   *
   * @return integer
   * @access public
   */
  public function getFundraiserContactID() {
    $importSettings = $this->getImportSettings();
    return $importSettings['fundraiser_id'];
  }

  /**
   * Method to retrieve the default admin handler contact
   * (assignee of activities)
   *
   * @return integer
   * @access public
   */
  public function getAdminContactID() {
    $importSettings = $this->getImportSettings();
    return $importSettings['admin_id'];
  }

  /**
   * Method to get the external donor id custom group (whole array or specific element)
   *
   * @param null $key
   * @return mixed
   * @access public
   */
  public function getExternalDonorIdCustomGroup($key = null) {
    if (empty($key)) {
      return $this->externalDonorIdCustomGroup;
    } else {
      return $this->externalDonorIdCustomGroup[$key];
    }
  }

  /**
   * Method to get the custom fields for external donor id (whole array or specific field array)
   *
   * @param null $key
   * @return array
   * @access public
   */
  public function getExternalDonorIdCustomFields($key = null) {
    if (empty($key)) {
      return $this->externalDonorIdCustomFields;
    } else {
      return $this->externalDonorIdCustomFields[$key];
    }
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

  public function saveImportSettings($params) {
    foreach ($params as $key => $value) {
      if (isset($this->importSettings[$key])) {
        $this->importSettings[$key]['value'] = $value;
      }
    }
    $fileName = $this->resourcesPath.'import_settings.json';
    try {
      $fh = fopen($fileName, 'w');
    } catch (Exception $ex) {
      throw new Exception('Could not open import_settings.json, contact your system administrator. Error reported: '.$ex->getMessage());
    }
    fwrite($fh, json_encode($this->importSettings,JSON_PRETTY_PRINT));
    fclose($fh);
  }

  /**
   * Method to create or get activity types
   *
   * @throws Exception when resource file could not be loaded
   */
  protected function setActivityTypes() {
    $jsonFile = $this->resourcesPath.'activity_types.json';
    if (!file_exists($jsonFile)) {
      throw new Exception('Could not load activity types configuration file for extension,
      contact your system administrator!');
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
      throw new Exception('Could not load relationship types configuration file for extension,
      contact your system administrator!');
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
  protected function setContactSubTypes()
  {
    $jsonFile = $this->resourcesPath . 'contact_sub_types.json';
    if (!file_exists($jsonFile)) {
      throw new Exception('Could not load contact sub types configuration file for extension,
      contact your system administrator!');
    }
    $contactTypesJson = file_get_contents($jsonFile);
    $contactSubTypes = json_decode($contactTypesJson, true);
    foreach ($contactSubTypes as $params) {
      $propertyName = $params['name'] . 'ContactSubType';
      $contactSubType = CRM_Streetimport_Utils::getContactSubTypeWithName($params['name']);
      if (!$contactSubType) {
        $contactSubType = CRM_Streetimport_Utils::createContactSubType($params);
      }
      $this->$propertyName = $contactSubType;
    }
  }

  protected function setCustomData() {
    $jsonFile = $this->resourcesPath.'custom_data.json';
    if (!file_exists($jsonFile)) {
      throw new Exception('Could not load custom data configuration file for extension, contact your system administrator!');
    }
    $customDataJson = file_get_contents($jsonFile);
    $customData = json_decode($customDataJson, true);
    foreach ($customData as $customGroupName => $customGroupData) {
      $propertyCustomGroup = $customGroupName.'CustomGroup';
      $customGroup = CRM_Streetimport_Utils::getCustomGroupWithName($customGroupName);
      if (!$customGroup) {
        $customGroupParams = $this->buildCustomGroupParams($customGroupData);
        $customGroup = CRM_Streetimport_Utils::createCustomGroup($customGroupParams);
      }
      $this->$propertyCustomGroup = $customGroup;
      $propertyCustomFields = $customGroupName.'CustomFields';
      $createdCustomFields = array();
      foreach ($customGroupData['fields'] as $customFieldName => $customFieldData) {
        $customField = CRM_Streetimport_Utils::getCustomFieldWithNameCustomGroupId($customFieldName, $customGroup['id']);
        if (!$customField) {
          $customFieldData['custom_group_id'] = $customGroup['id'];
          $customFieldParams = $customFieldData;
          $customField = CRM_Streetimport_Utils::createCustomField($customFieldParams);
        }
        $customFieldId = $customField['id'];
        $createdCustomFields[$customFieldId] = $customField;
      }
      $this->$propertyCustomFields = $createdCustomFields;
    }
  }

  /**
   * Method to build param list for custom group creation
   *
   * @param array $customGroupData
   * @return array $customGroupParams
   * @access protected
   */
  protected function buildCustomGroupParams($customGroupData) {
    $customGroupParams = array();
    foreach ($customGroupData as $name => $value) {
      if ($name != 'fields') {
        $customGroupParams[$name] = $value;
      }
    }
    if ($customGroupParams['extends'] == 'Activity') {
      $extendsActivity = CRM_Streetimport_Utils::getActivityTypeWithName($customGroupData['extends_entity_column_value']);
      $customGroupParams['extends_entity_column_value'] = CRM_Core_DAO::VALUE_SEPARATOR.$extendsActivity['value'];
    }
    return $customGroupParams;
  }

  /**
   * Method to build param list for custom field creation
   *
   * @param array $customFieldData
   * @return array $customFieldParams
   * @access protected
   */
  protected function buildCustomFieldParams($customFieldData) {
    $customFieldParams = array();
    foreach ($customFieldData as $name => $value) {
      if ($name == "option_group") {
        $customFieldParams['option_group_id'] = CRM_Streetimport_Utils::getOptionGroupIdWithName($value);
      } else {
        $customFieldParams[$name] = $value;
      }
    }
    return $customFieldParams;
  }

  /**
   * Method to set the Import Settings property
   *
   * @throws Exception when file not found
   * @access protected
   */
  protected function setImportSettings() {
    $jsonFile = $this->resourcesPath.'import_settings.json';
    if (!file_exists($jsonFile)) {
      throw new Exception('Could not load import_settings configuration file for extension, contact your system administrator!');
    }
    $importSettingsJson = file_get_contents($jsonFile);
    $this->importSettings = json_decode($importSettingsJson, true);
  }
}