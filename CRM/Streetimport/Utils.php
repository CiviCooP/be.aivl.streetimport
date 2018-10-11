<?php
/**
 * Class with extension specific util functions
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 30 April 2015
 * @license AGPL-3.0
 */

class CRM_Streetimport_Utils {

  /**
   * Method to get custom field with name and custom_group_id
   *
   * @param string $customFieldName
   * @param int $customGroupId
   * @return array|bool
   * @access public
   * @static
   */
  public static function getCustomFieldWithNameCustomGroupId($customFieldName, $customGroupId) {
    try {
      $customField = civicrm_api3('CustomField', 'Getsingle', array('name' => $customFieldName, 'custom_group_id' => $customGroupId));
      return $customField;
    } catch (CiviCRM_API3_Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Method to get custom group with name
   *
   * @param string $customGroupName
   * @return array|bool
   * @access public
   * @static
   */
  public static function getCustomGroupWithName($customGroupName) {
    try {
      $customGroup = civicrm_api3('CustomGroup', 'Getsingle', array('name' => $customGroupName));
      return $customGroup;
    } catch (CiviCRM_API3_Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Function to get activity type with name
   *
   * @param string $activityTypeName
   * @return array|bool
   * @access public
   * @static
   */
  public static function getActivityTypeWithName($activityTypeName) {
    $optionGroup = self::getOptionGroupWithName('activity_type');
    $activityTypeOptionGroupId = $optionGroup['id'];
    $params = array(
      'option_group_id' => $activityTypeOptionGroupId,
      'name' => $activityTypeName);
    try {
      $activityType = civicrm_api3('OptionValue', 'Getsingle', $params);
      return $activityType;
    } catch (CiviCRM_API3_Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Function to get activity status id with name
   *
   * @param string $activityStatusName
   * @return int|bool
   * @access public
   * @static
   */
  public static function getActivityStatusIdWithName($activityStatusName) {
    $optionGroup = self::getOptionGroupWithName('activity_status');
    $activityStatusOptionGroupId = $optionGroup['id'];
    $params = array(
      'option_group_id' => $activityStatusOptionGroupId,
      'name' => $activityStatusName);
    try {
      $activityStatus = civicrm_api3('OptionValue', 'Getsingle', $params);
      return $activityStatus['value'];
    } catch (CiviCRM_API3_Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Function to get contact sub type with name
   *
   * @param string $contactSubTypeName
   * @return array|bool
   * @access public
   * @static
   */
  public static function getContactSubTypeWithName($contactSubTypeName) {
    try {
      $contactSubType = civicrm_api3('ContactType', 'Getsingle', array('name' => $contactSubTypeName));
      return $contactSubType;
    } catch (CiviCRM_API3_Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Function to get relationship type with name_a_b
   *
   * @param string $nameAB
   * @return array|bool
   * @access public
   * @static
   */
  public static function getRelationshipTypeWithName($nameAB) {
    try {
      $relationshipType = civicrm_api3('RelationshipType', 'Getsingle', array('name_a_b' => $nameAB));
      return $relationshipType;
    } catch (CiviCRM_API3_Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Function to get the group with a name
   *
   * @param string $groupName
   * @return array|bool
   * @access public
   * @static
   */
  public static function getGroupWithName($groupName) {
    try {
      $group = civicrm_api3('Group', 'Getsingle', array('name' => $groupName));
      return $group;
    } catch (CiviCRM_API3_Exception $ex) {
      return FALSE;
    }
  }

  /**
   * Function to get the option group id
   *
   * @param string $optionGroupName
   * @return int $optionGroupId
   * @access public
   * @static
   */
  public static function getOptionGroupWithName($optionGroupName) {
    $params = array(
      'name' => $optionGroupName,
      'is_active' => 1);
    try {
      $optionGroup = civicrm_api3('OptionGroup', 'Getsingle', $params);
      return $optionGroup;
    } catch (CiviCRM_API3_Exception $ex) {
      return array();
    }
  }

  /**
   * Method to create option group if not exists yet
   *
   * @param $params
   * @return array
   * @throws Exception when error from API
   * @access public
   */
  public static function createOptionGroup($params) {
    $optionGroupData = array();
    if (self::getOptionGroupWithName($params['name']) == FALSE) {
      $params['is_active'] = 1;
      $params['is_reserved'] = 1;
      if (!isset($params['title'])) {
        $params['title'] = ucfirst($params['name']);
      }
      try {
        $optionGroup = civicrm_api3('OptionGroup', 'Create', $params);
        $optionGroupData = $optionGroup['values'];
      } catch (CiviCRM_API3_Exception $ex) {
        $config = CRM_Streetimport_Config::singleton();
        throw new Exception($config->translate('Could not create option_group type with name').' '
          .$params['name'].', '.$config->translate('error from API OptionGroup Create').': ' . $ex->getMessage());
      }
    }
    return $optionGroupData;
  }

  /**
   * Function to create group if not exists yet
   *
   * @param array $params
   * @return array $groupData
   * @throws Exception when error in API Group Create
   * @access public
   * @static
   */
  public static function createGroup($params) {
    $groupData = array();
    if (self::getGroupWithName($params['name']) == FALSE) {
      if (!isset($params['is_active'])) {
        $params['is_active'] = 1;
        if (empty($params['title']) || !isset($params['title'])) {
          $params['title'] = self::buildLabelFromName($params['name']);
        }
      }
      try {
        $group = civicrm_api3('Group', 'Create', $params);

        /*
         * correct group name directly in database because creating with API causes
         * id to be added at the end of name which kind of defeats the idea of
         * having the same name in each install
         * Core bug https://issues.civicrm.org/jira/browse/CRM-14062, resolved in 4.4.4
         */
        if (CRM_Core_BAO_Domain::version() < 4.5) {
          $query = 'UPDATE civicrm_group SET name = %1 WHERE id = %2';
          $queryParams = array(
            1 => array($params['name'], 'String'),
            2 => array($group['id'], 'Integer'));
          CRM_Core_DAO::executeQuery($query, $queryParams);
        }

        $groupData = $group['values'];
      } catch (CiviCRM_API3_Exception $ex) {
        $config = CRM_Streetimport_Config::singleton();
        throw new Exception($config->translate('Could not create group with name').' '.$params['name']
          .', '.$config->translate('error from API Group Create').': ' . $ex->getMessage());
      }
    }
    return $groupData;
  }

  /**
   * Function to create activity type
   *
   * @param array $params
   * @return array
   * @throws Exception when params invalid
   * @throws Exception when error from API create
   * @access public
   * @static
   */
  public static function createActivityType($params) {
    $activityTypeData = array();
    $optionGroup = self::getOptionGroupWithName('activity_type');
    $params['option_group_id'] = $optionGroup['id'];
    if (!isset($params['name']) || empty($params['name'])) {
      $config = CRM_Streetimport_Config::singleton();
      throw new Exception($config->translate('When trying to create an Activity Type name is a mandatory parameter and can not be empty'));
    }

    if (empty($params['label']) || !isset($params['label'])) {
      $params['label'] = self::buildLabelFromName($params['name']);
    }
    if (!isset($params['is_active'])) {
      $params['is_active'] = 1;
    }
    if (self::getActivityTypeWithName($params['name']) == FALSE) {
      try {
        $activityType = civicrm_api3('OptionValue', 'Create', $params);
        $activityTypeData = $activityType['values'][$activityType['id']];
      } catch (CiviCRM_API3_Exception $ex) {
        $config = CRM_Streetimport_Config::singleton();
        throw new Exception($config->translate('Could not create activity type with name').' '.$params['name']
          .', '.$config->translate('error from API OptionValue Create').': '.$ex->getMessage());
      }
    }
    return $activityTypeData;
  }

  /**
   * Method to create contact sub type
   *
   * @param $params
   * @return array
   * @throws Exception when params['name'] is empty or not there
   * @throws Exception when error from API ContactType Create
   * @access public
   * @static
   */
  public static function createContactSubType($params) {
    $contactSubType = array();
    if (!isset($params['name']) || empty($params['name'])) {
      $config = CRM_Streetimport_Config::singleton();
      throw new Exception($config->translate('When trying to create a Contact Sub Type name is a mandatory parameter and can not be empty'));
    }
    if (!isset($params['label']) || empty($params['label'])) {
      $params['label'] = self::buildLabelFromName($params['name']);
    }
    if (self::getContactSubTypeWithName($params['name']) == FALSE) {
      try {
        $contactSubType = civicrm_api3('ContactType', 'Create', $params);
      } catch (CiviCRM_API3_Exception $ex) {
        $config = CRM_Streetimport_Config::singleton();
        throw new Exception($config->translate('Could not create contact sub type with name').' '.$params['name']
          .', '.$config->translate('error from API ContactType Create').': '.$ex->getMessage());
      }
    }
    return $contactSubType['values'][$contactSubType['id']];
  }

  /**
   * Method to create relationship type
   *
   * @param $params
   * @return array
   * @throws Exception when params invalid
   * @throws Exception when error from API ContactType Create
   * @access public
   * @static
   */
  public static function createRelationshipType($params) {
    $relationshipType = array();
    if (!isset($params['name_a_b']) || empty($params['name_a_b']) || !isset($params['name_b_a']) || empty($params['name_b_a'])) {
      $config = CRM_Streetimport_Config::singleton();
      throw new Exception($config->translate('When trying to create a Relationship Type name_a_b and name_b_a are mandatory parameter and can not be empty'));
    }
    if (!isset($params['label_a_b']) || empty($params['label_a_b'])) {
      $params['label_a_b'] = self::buildLabelFromName($params['name_a_b']);
    }
    if (!isset($params['label_b_a']) || empty($params['label_b_a'])) {
      $params['label_b_a'] = self::buildLabelFromName($params['name_b_a']);
    }
    if (self::getRelationshipTypeWithName($params['name_a_b']) == FALSE) {
      try {
        $relationshipType = civicrm_api3('RelationshipType', 'Create', $params);
      } catch (CiviCRM_API3_Exception $ex) {
        $config = CRM_Streetimport_Config::singleton();
        throw new Exception($config->translate('Could not create relationship type with name').' '.$params['name_a_b']
          .', '.$config->translate('error from API RelationshipType Create').': '.$ex->getMessage());
      }
    }
    return $relationshipType['values'][$relationshipType['id']];
  }

  /**
   * Method to create custom group
   *
   * @param $params
   * @return array
   * @throws Exception when params invalid
   * @throws Exception when error from API CustomGroup Create
   * @access public
   * @static
   */
  public static function createCustomGroup($params) {
    $customGroup = array();
    if (!isset($params['name']) || empty($params['name']) || !isset($params['extends']) || empty($params['extends'])) {
      $config = CRM_Streetimport_Config::singleton();
      throw new Exception($config->translate('When trying to create a Custom Group name and extends are mandatory parameters and can not be empty'));
    }
    if (!isset($params['title']) || empty($params['title'])) {
      $params['title'] = self::buildLabelFromName($params['name']);
    }
    if (self::getCustomGroupWithName($params['name']) == FALSE) {
      try {
        $customGroup = civicrm_api3('CustomGroup', 'Create', $params);
      } catch (CiviCRM_API3_Exception $ex) {
        $config = CRM_Streetimport_Config::singleton();
        throw new Exception($config->translate('Could not create custom group with name').' '.$params['name']
          .' '.$config->translate('to extend').' '.$params['extends'].', '.$config->translate('error from API CustomGroup Create')
          .': '.$ex->getMessage().", parameters : ".implode(";", $params));
      }
    }
    return $customGroup['values'][$customGroup['id']];
  }

  /**
   * Method to create custom field
   *
   * @param $params
   * @return array
   * @throws Exception when params invalid
   * @throws Exception when error from API CustomField Create
   * @access public
   * @static
   */
  public static function createCustomField($params) {
    $customField = array();
    if (!isset($params['name']) || empty($params['name']) || !isset($params['custom_group_id']) || empty($params['custom_group_id'])) {
      $config = CRM_Streetimport_Config::singleton();
      throw new Exception($config->translate('When trying to create a Custom Field name and custom_group_id are mandatory parameters and can not be empty'));
    }
    if (!isset($params['label']) || empty($params['label'])) {
      $params['label'] = self::buildLabelFromName($params['name'], $params['custom_group_id']);
    }
    if (self::getCustomFieldWithNameCustomGroupId($params['name'], $params['custom_group_id']) == FALSE) {
      try {
        $customField = civicrm_api3('CustomField', 'Create', $params);
      } catch (CiviCRM_API3_Exception $ex) {
        $config = CRM_Streetimport_Config::singleton();
        throw new Exception($config->translate('Could not create custom field with name').' '.$params['name']
          .' '.$config->translate('in custom group').' '.$params['custom_group_id']
          .', '.$config->translate('error from API CustomField Create').': '.$ex->getMessage());
      }
    }
    return $customField['values'][$customField['id']];
  }

  /**
   * Public function to generate label from name
   *
   * @param $name
   * @return string
   * @access public
   * @static
   */
  public static function buildLabelFromName($name) {
    $nameParts = explode('_', strtolower($name));
    foreach ($nameParts as $key => $value) {
      $nameParts[$key] = ucfirst($value);
    }
    return implode(' ', $nameParts);
  }

  /**
   * Method creates a new, unique navID for the CiviCRM menu
   * It will consider the IDs from the database,
   * as well as the 'volatile' ones already injected into the menu
   *
   * @param array $menu
   * @return int
   * @access public
   * @static
   */
  public static function createUniqueNavID($menu) {
    $maxStoredNavId = CRM_Core_DAO::singleValueQuery("SELECT max(id) FROM civicrm_navigation");
    $maxCurrentNavId = self::getMaxNavID($menu);
    return max($maxStoredNavId, $maxCurrentNavId) + 1;
  }

  /**
   * Method crawls the menu tree to find the (currently) biggest navID
   *
   * @param array $menu
   * @return int
   * @access public
   * @static
   */
  public static function getMaxNavID($menu)   {
    $maxId = 1;
    foreach ($menu as $entry) {
      $maxId = max($maxId, CRM_Utils_Array::value('navID', $entry['attributes'], 0));
      if (!empty($entry['child'])) {
        $maxIdChildren = self::getMaxNavID($entry['child']);
        $maxId = max($maxId, $maxIdChildren);
      }
    }
    return $maxId;
  }

  /**
   * Method to add the given menu item to the CiviCRM navigation menu if it does not exist yet.
   *
   * @param array $parentParams the params array into whose 'child' attribute the new item will be added.
   * @param array $menuEntryAttributes the attributes array to be added to the navigation menu
   * @access public
   * @static
   */
  public static function addNavigationMenuEntry(&$parentParams, $menuEntryAttributes) {
    // see if it is already in the menu...
    $menuItemSearch = array('url' => $menuEntryAttributes['url']);
    $menuItems = array();
    CRM_Core_BAO_Navigation::retrieve($menuItemSearch, $menuItems);

    if (empty($menuItems)) {
      // it's not already contained, so we want to add it to the menu

      // insert at the bottom
      $parentParams['child'][] = array(
        'attributes' => $menuEntryAttributes);
    }
  }

  /**
   * Function to get contact name
   *
   * @param int $contactId
   * @return string $contactName
   * @access public
   * @static
   */
  public static function getContactName($contactId) {
    $params = array(
      'id' => $contactId,
      'return' => 'display_name');
    try {
      $contactName = civicrm_api3('Contact', 'Getvalue', $params);
    } catch (CiviCRM_API3_Exception $ex) {
      $contactName = '';
    }
    return $contactName;
  }

  /**
   * Function to get the contact id for a donor without error logging.
   * However, exceptions are thrown if needed.
   * Can be safely used in error logging.
   *
   * @param int donorId
   * @param int recruitingOrganizationId
   */
  public static function getContactIdFromDonorId($donorId, $recruitingOrganizationId) {
    $config = CRM_Streetimport_Config::singleton();
    $tableName = $config->getExternalDonorIdCustomGroup('table_name');
    $donorCustomField = $config->getExternalDonorIdCustomFields('external_donor_id');
    $orgCustomField = $config->getExternalDonorIdCustomFields('recruiting_organization_id');
    if (empty($donorCustomField)) {
      throw new Exception($config->translate("CustomField external_donor_id not found. Please reinstall."));
    }
    if (empty($orgCustomField)) {
      throw new Exception($config->translate("CustomField recruiting_organization_id not found. Please reinstall."));
    }
    $query = 'SELECT entity_id FROM '.$tableName.' WHERE '.$donorCustomField['column_name'].' = %1 AND '.$orgCustomField['column_name'].' = %2';
    $params = array(
      1 => array($donorId, 'Positive'),
      2 => array($recruitingOrganizationId, 'Positive'));

    $dao = CRM_Core_DAO::executeQuery($query, $params);
    if ($dao->N > 1) {
      throw new Exception($config->translate('More than one contact found for donor ID').': '.$donorId);
    }

    if ($dao->fetch()) {
      return $dao->entity_id;
    }
    else {
      return NULL;
	}
  }

  /**
   * Function get country data with an iso code
   *
   * @param $isoCode
   * @return array
   */
  public static function getCountryByIso($isoCode) {
    $country = array();
    if (empty($isoCode)) {
      return $country;
    }
    $query = 'SELECT * FROM civicrm_country WHERE iso_code = %1';
    $params = array(1 => array($isoCode, 'String'));
    $dao = CRM_Core_DAO::executeQuery($query, $params);
    if ($dao->fetch()) {
      $country['country_id'] = $dao->id;
      $country['name'] = $dao->name;
      $country['iso_code'] = $dao->iso_code;
    }
    return $country;
  }

  /**
   * Method to determine gender with prefix
   *
   * @param $prefix
   * @return int
   */
  public static function determineGenderWithPrefix($prefix) {
    $config = CRM_Streetimport_Config::singleton();
    $prefix = strtolower($prefix);
    switch ($prefix) {
      case 'meneer':
        return $config->getMaleGenderId();
      break;
      case 'mevrouw':
        return $config->getFemaleGenderId();
      break;
      default:
        return $config->getUnknownGenderId();
      break;
    }
  }

  /**
   * Method to get list of active option values for select lists
   *
   * @param string $optionGroupName
   * @return array
   * @throws Exception when no option group found
   * @access public
   * @static
   */
  public static function getOptionGroupList($optionGroupName) {
    $valueList = array();
    $optionGroupParams = array(
      'name' => $optionGroupName,
      'return' => 'id');
    try {
      $optionGroupId = civicrm_api3('OptionGroup', 'Getvalue', $optionGroupParams);
      $optionValueParams = array(
        'option_group_id' => $optionGroupId,
        'is_active' => 1,
        'options' => array('limit' => 99999));
      $optionValues = civicrm_api3('OptionValue', 'Get', $optionValueParams);
      foreach ($optionValues['values'] as $optionValue) {
        $valueList[$optionValue['value']] = $optionValue['label'];
      }
      $valueList[0] = ts('- select -');
      asort($valueList);
    } catch (CiviCRM_API3_Exception $ex) {
      $config = CRM_Streetimport_Config::singleton();
      throw new Exception($config->translate('Could not find an option group with name').' '.$optionGroupName
        .' ,'.$config->translate('contact your system administrator').' .'
        .$config->translate('Error from API OptionGroup Getvalue').': '.$ex->getMessage());
    }
    return $valueList;
  }

  /**
   * Method to set list of date formats for import files
   *
   * @access public
   * @static
   * @return array
   */
  public static function getDateFormatList() {
    return array('dd-mm-jjjj', 'dd/mm/jjjj', 'dd-mm-jj', 'dd/mm/jj', 'jjjj-mm-dd', 'jjjj/mm/dd', 'jj-mm-dd','jj/mm/dd',
      'mm-dd-jjjj', 'mm/dd/jjjj', 'mm-dd-jj', 'mm/dd/jj');
  }

  /**
   * Method to format the CSV import date if new DateTime has thrown error
   *
   * @param $inDate
   * @return string
   */
  public static function formatCsvDate($inDate) {
    if (empty($inDate)) {
      return $inDate;
    }
    $config = CRM_Streetimport_Config::singleton();
    $inDay = null;
    $inMonth = null;
    $inYear = null;
    switch ($config->getCsvDateFormat()) {
      case 0:
        $dateParts = explode("-", $inDate);
        $inDay = $dateParts[0];
        $inMonth = $dateParts[1];
        $inYear = $dateParts[2];
        break;
      case 1:
        $dateParts = explode("/", $inDate);
        if (isset($dateParts[1]) && isset($dateParts[2])) {
          $inDay = $dateParts[0];
          $inMonth = $dateParts[1];
          $inYear = $dateParts[2];
        }
        break;
      case 2:
        $dateParts = explode("-", $inDate);
        $inDay = $dateParts[0];
        $inMonth = $dateParts[1];
        $inYear = $dateParts[2];
        break;
      case 3:
        $dateParts = explode("/", $inDate);
        $inDay = $dateParts[0];
        $inMonth = $dateParts[1];
        $inYear = $dateParts[2];
        break;
      case 4:
        $dateParts = explode("-", $inDate);
        $inDay = $dateParts[2];
        $inMonth = $dateParts[1];
        $inYear = $dateParts[0];
        break;
      case 5:
        $dateParts = explode("/", $inDate);
        $inDay = $dateParts[2];
        $inMonth = $dateParts[1];
        $inYear = $dateParts[0];
        break;
      case 6:
        $dateParts = explode("-", $inDate);
        $inDay = $dateParts[2];
        $inMonth = $dateParts[1];
        $inYear = $dateParts[0];
        break;
      case 7:
        $dateParts = explode("/", $inDate);
        $inDay = $dateParts[2];
        $inMonth = $dateParts[1];
        $inYear = $dateParts[0];
        break;
      case 8:
        $dateParts = explode("-", $inDate);
        $inDay = $dateParts[1];
        $inMonth = $dateParts[0];
        $inYear = $dateParts[2];
        break;
      case 9:
        $dateParts = explode("/", $inDate);
        $inDay = $dateParts[1];
        $inMonth = $dateParts[0];
        $inYear = $dateParts[2];
        break;
      case 10:
        $dateParts = explode("-", $inDate);
        $inDay = $dateParts[1];
        $inMonth = $dateParts[0];
        $inYear = $dateParts[2];
        break;
      case 11:
        $dateParts = explode("/", $inDate);
        $inDay = $dateParts[1];
        $inMonth = $dateParts[0];
        $inYear = $dateParts[2];
        break;
      default:
        return $inDate;
      break;
    }
    return $inDay.'-'.$inMonth.'-'.$inYear;
  }

  static function csvToArray($file, $hasHeaders = true, $delimiter =';'){ // TODO: delimter should be defined in extension config
    $pointer = fopen ($file , 'r');
    if($hasHeaders){
      $headers = fgetcsv($pointer, 0, $delimiter);
    }
    while ($record = fgetcsv($pointer, 0, $delimiter)){
      $records[] = $hasHeaders ? array_combine($headers, $record) : $record;
    }
    return $records;
  }

  /**
   * render a given template with the given variables
   */
  public static function renderTemplate($template_path, $vars) {
    $config = CRM_Streetimport_Config::singleton();
    $smarty = CRM_Core_Smarty::singleton();

    // first backup original variables, since smarty instance is a singleton
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

    // adjust template path
    $result =  $smarty->fetch($config->getDomain() . '/' . $template_path);

    // reset smarty variables
    foreach ($backupFrame as $key => $value) {
      $key = str_replace(' ', '_', $key);
      $smarty->assign($key, $value);
    }

    return $result;
  }


  /**
   * Create an activity with the given data
   *
   * @return activity BAO object
   */
  public function createActivity($data, $record, $assigned_contact_ids=NULL) {
    $activity = CRM_Activity_BAO_Activity::create($data);
    if (empty($activity->id)) {
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

    // set custom fields (if any)
    $custom_data = array();
    foreach ($data as $key => $value) {
      if (substr($key, 0, 7) == 'custom_') {
        $custom_data[$key] = $value;
      }
    }
    if ($activity->id && !empty($custom_data)) {
      $custom_data['id'] = $activity->id;
      civicrm_api3('Activity', 'create', $custom_data);
    }

    return $activity;
  }

}
