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
  protected $recruitingOrganizationsGroupId = null;
  protected $frequenceUnitOptionGroup = null;
  protected $areasOfInterestOptionGroup = null;
  protected $translatedStrings = array();

  /**
   * Constructor method
   *
   * @param string $context
   */
  function __construct($context) {
    $settings = civicrm_api3('Setting', 'Getsingle', array());
    $this->resourcesPath = $settings['extensionsDir'].'/be.aivl.streetimport/resources/';
    $this->aivlLegalName = 'Amnesty International Vlaanderen vzw';
    $this->streetRecruitmentImportType = 1;
    $this->welcomeCallImportType = 2;
    $this->acceptedYesValues = array('J', 'j', 'Ja', 'ja', 'true', 'waar', 'yes', 'Yes', 1);

    $this->setContactSubTypes();
    $this->setRelationshipTypes();
    $this->setActivityTypes();
    $this->setOptionGroups();
    $this->setCustomData();
    $this->setImportSettings();
    //if ($context == 'install') {
    $this->setDefaultEmployeeTypes();
    //}
    $this->setGroups();
    $this->setTranslationFile();
  }

  /**
   * Method to get option group for areas of interest
   *
   * @param string $key
   * @return mixed
   * @access public
   */
  public function getAreasOfIntereestOptionGroup($key = 'id') {
    return $this->areasOfInterestOptionGroup[$key];
  }

  /**
   * Method to get option group for frequency unit
   *
   * @param string $key
   * @return mixed
   * @access public
   */
  public function getFrequencyUnitOptionGroup($key = 'id') {
    return $this->frequenceUnitOptionGroup[$key];
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
   * Method to get the street recruitment custom group
   *
   * @param string $key
   * @return mixed
   * @access public
   */
  public function getStreetRecruitmentCustomGroup($key = 'id') {
    return $this->streetRecruitmentCustomGroup[$key];
  }

  /**
   * Method to get the street recruitment custom fields
   *
   * @return mixed
   * @access public
   */
  public function getStreetRecruitmentCustomFields() {
    return $this->streetRecruitmentCustomFields;
  }

  /**
   * Method to get the welcome call custom fields
   *
   * @return mixed
   * @access public
   */
  public function getWelcomeCallCustomFields() {
    return $this->welcomeCallCustomFields;
  }

  /**
   * Method to get the welcome call group
   *
   * @param string $key
   * @return mixed
   * @access public
   */
  public function getWelcomeCallCustomGroup($key = 'id') {
    return $this->welcomeCallCustomGroup[$key];
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
   * @param string $string
   * @return string
   * @access public
   */
  public function translate($string) {
    if (isset($this->translatedStrings[$string])) {
      return $this->translatedStrings[$string];
    } else {
      return ts($string);
    }
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
  public function getImportErrorActivityType($key= 'value' ) {
    return $this->importErrorActivityType[$key];
  }

  /**
   * Method to retrieve follow up call activity type data
   *
   * @param string $key
   * @return mixed
   * @access public
   */
  public function getFollowUpCallActivityType($key= 'value' ) {
    return $this->followUpCallActivityType[$key];
  }

  /**
   * Method to retrieve welcome call activity type data
   *
   * @param string $key
   * @return mixed
   * @access public
   */
  public function getWelcomeCallActivityType($key= 'value' ) {
    return $this->welcomeCallActivityType[$key];
  }

  /**
   * Method to retrieve street recruitment activity type data
   *
   * @param string $key
   * @return mixed
   * @access public
   */
  public function getStreetRecruitmentActivityType($key= 'value' ) {
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
  public function getSupplierContactSubType($key= 'name' ) {
    return $this->supplierContactSubType[$key];
  }

  /**
   * Method to retrieve recruiter contact sub type data
   *
   * @param string $key
   * @return mixed
   * @access public
   */
  public function getRecruiterContactSubType($key= 'name' ) {
    return $this->recruiterContactSubType[$key];
  }

  /**
   * Method to retrieve the newsletter group id
   *
   * @return int
   * @access public
   */
  public function getNewsletterGroupID() {
    $importSettings = $this->getImportSettings();
    return $importSettings['newsletter_group_id']['value'];
  }

  /**
   * Method to retrieve the membership type ID
   *
   * @return integer
   * @access public
   */
  public function getMembershipTypeID() {
    $importSettings = $this->getImportSettings();
    return $importSettings['membership_type_id']['value'];
  }

  /**
   * Method to retrieve import file location
   *
   * @return string
   * @access public
   */
  public function getImportFileLocation() {
    $importSettings = $this->getImportSettings();
    return $importSettings['import_location']['value'];
  }

  /**
   * Method to retrieve location for processed file
   *
   * @return string
   * @access public
   */
  public function getProcessedFileLocation() {
    $importSettings = $this->getImportSettings();
    return $importSettings['processed_location']['value'];
  }

  /**
   * Method to get the offset days
   *
   * @return int
   * @access public
   */
  public function getOffsetDays() {
    $importSettings = $this->getImportSettings();
    return $importSettings['offset_days']['value'];
  }

  /**
   * Method to get the default location type
   *
   * @return int
   * @access public
   */
  public function getLocationTypeId() {
    $importSettings = $this->getImportSettings();
    return $importSettings['location_type_id']['value'];
  }

  /**
   * Method to get the extra location type
   *
   * @return int
   * @access public
   */
  public function getOtherLocationTypeId() {
    $importSettings = $this->getImportSettings();
    return $importSettings['other_location_type_id']['value'];
  }

  /**
   * Method to get the default country id
   *
   * @return int
   * @access public
   */
  public function getDefaultCountryId() {
    $importSettings = $this->getImportSettings();
    return $importSettings['default_country_id']['value'];
  }

  /**
   * Method to get the default financial_type id
   *
   * @return int
   * @access public
   */
  public function getDefaultFinancialTypeId() {
    $importSettings = $this->getImportSettings();
    return $importSettings['default_financial_type_id']['value'];
  }

  /**
   * Method to get the gender id for males
   *
   * @return mixed
   * @access public
   */
  public function getMaleGenderId() {
    $importSettings = $this->getImportSettings();
    return $importSettings['male_gender_id']['value'];
  }

  /**
   * Method to get the gender id for females
   *
   * @return mixed
   * @access public
   */
  public function getFemaleGenderId() {
    $importSettings = $this->getImportSettings();
    return $importSettings['female_gender_id']['value'];
  }

  /**
   * Method to get the gender id for unknown
   *
   * @return mixed
   * @access public
   */
  public function getUnknownGenderId() {
    $importSettings = $this->getImportSettings();
    return $importSettings['unknown_gender_id']['value'];
  }

  /**
   * Method to get prefixes for household
   *
   * @return array
   * @access public
   */
  public function getHouseholdPrefixIds() {
    $importSettings = $this->getImportSettings();
    return $importSettings['household_prefix_id']['value'];
  }

  /**
   * Method to get relationship types for employee
   *
   * @return array
   * @access public
   */
  public function getEmployeeRelationshipTypeIds() {
    $importSettings = $this->getImportSettings();
    return $importSettings['employee_type_id']['value'];
  }

  /**
   * Method to get the phone type id of phone
   *
   * @return int
   * @access public
   */
  public function getPhonePhoneTypeId() {
    $importSettings = $this->getImportSettings();
    return $importSettings['phone_phone_type_id']['value'];
  }

  /**
   * Method to get the phone type id of mobile
   *
   * @return int
   * @access public
   */
  public function getMobilePhoneTypeId() {
    $importSettings = $this->getImportSettings();
    return $importSettings['mobile_phone_type_id']['value'];
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
    return $importSettings['fundraiser_id']['value'];
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
    return $importSettings['admin_id']['value'];
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
      // find field by name or ID
      foreach ($this->externalDonorIdCustomFields as $field_id => $customField) {
        if ($customField['name'] == $key || $field_id==$key) {
          return $customField;
        } 
      }
      // no such field
      return NULL;
    }
  }

  /**
   * Singleton method
   *
   * @param string $context to determine if triggered from install hook
   * @return CRM_Streetimport_Config
   * @access public
   * @static
   */
  public static function singleton($context = null) {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Streetimport_Config($context);
    }
    return self::$_singleton;
  }

  /**
   * Method to save the import settings
   *
   * @param array $params
   * @throws Exception when json file could not be opened
   * @access public
   */
  public function saveImportSettings($params) {
    if (!empty($params)) {
      foreach ($params as $key => $value) {
        if (isset($this->importSettings[$key])) {
          $this->importSettings[$key]['value'] = $value;
        }
      }
      $fileName = $this->resourcesPath . 'import_settings.json';
      try {
        $fh = fopen($fileName, 'w');
        fwrite($fh, json_encode($this->importSettings));
        fclose($fh);
      } catch (Exception $ex) {
        throw new Exception('Could not open import_settings.json, contact your system administrator. Error reported: ' . $ex->getMessage());
      }
    }
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

  /**
   * Method to create option groups
   *
   * @throws Exception when resource file not found
   * @access protected
   */
  protected function setOptiongroups() {
    $jsonFile = $this->resourcesPath.'option_groups.json';
    if (!file_exists($jsonFile)) {
      throw new Exception('Could not load option_groups configuration file for extension,
      contact your system administrator!');
    }
    $optionGroupsJson = file_get_contents($jsonFile);
    $optionGroups = json_decode($optionGroupsJson, true);
    foreach ($optionGroups as $name => $title) {
      $propertyName = $name.'OptionGroup';
      $optionGroup = CRM_Streetimport_Utils::getOptionGroupWithName($name);
      if (empty($optionGroup)) {
        $optionGroup = CRM_Streetimport_Utils::createOptionGroup(array('name' => $name, 'title' => $title));
      }
      $this->$propertyName = $optionGroup;
    }
  }

  /**
   * Method to create or get groups
   *
   * @throws Exception when resource file could not be loaded
   */
  protected function setGroups() {
    $jsonFile = $this->resourcesPath . 'groups.json';
    if (!file_exists($jsonFile)) {
      throw new Exception('Could not load groups configuration file for extension,
      contact your system administrator!');
    }
    $groupJson = file_get_contents($jsonFile);
    $groups = json_decode($groupJson, true);
    foreach ($groups as $params) {
      $group = CRM_Streetimport_Utils::getGroupWithName($params['name']);
      if (!$group) {
        CRM_Streetimport_Utils::createGroup($params);
      }
      if ($params['name'] == "recruiting_organizations") {
        $this->recruitingOrganizationsGroupId = $group['id'];
      }
    }
  }

  /**
   * Method to set the custom data groups and fields
   *
   * @throws Exception when config json could not be loaded
   * @access protected
   */
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
          if ($customFieldName = 'recruiting_organization_id') {
            $customFieldData['filter'] = 'action=lookup&group='.$this->recruitingOrganizationsGroupId;
          }
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
        $optionGroup = CRM_Streetimport_Utils::getOptionGroupWithName($value);
        if (empty($optionGroup)) {
          $optionGroup = CRM_Streetimport_Utils::createOptionGroup(array('name' => $value));
        }
        $customFieldParams['option_group_id'] = $optionGroup['id'];
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

  /**
   * Protected function to load translation json based on local language
   *
   * @access protected
   */
  protected function setTranslationFile() {
    $config = CRM_Core_Config::singleton();
    $jsonFile = $this->resourcesPath.$config->lcMessages.'_translate.json';
    if (file_exists($jsonFile)) {
      $translateJson = file_get_contents($jsonFile);
      $this->translatedStrings = json_decode($translateJson, true);

    } else {
      $this->translatedStrings = array();
    }
  }

  /**
   * Method to set all relationship types as employee types at start up to
   * avoid not being able to set the admin and fundraiser id
   *
   * @link https://github.com/CiviCooP/be.aivl.streetimport/issues/36
   */
  protected function setDefaultEmployeeTypes() {
    $relationshipTypes = array();
    $relationshipTypeParams = array(
      'is_active' => 1,
      'return' => 'id',
      'options' => array('limit' => 999));
    $apiTypes = civicrm_api3('RelationshipType', 'Get', $relationshipTypeParams);
    foreach ($apiTypes['values'] as $apiType) {
      $relationshipTypes[] = $apiType['id'];
    }
    $this->importSettings['employee_type_id']['value'] = $relationshipTypes;
    $this->saveImportSettings($this->importSettings);
  }
}