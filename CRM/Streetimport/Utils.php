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
    $activityTypeOptionGroupId = self::getOptionGroupIdWithName('activity_type');
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
    $activityStatusOptionGroupId = self::getOptionGroupIdWithName('activity_status');
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
   * Function to get the option group id
   *
   * @param string $optionGroupName
   * @return int $optionGroupId
   * @throws Exception when option group not found
   * @access public
   * @static
   */
  public static function getOptionGroupIdWithName($optionGroupName) {
    $params = array(
      'name' => $optionGroupName,
      'return' => 'id');
    try {
      $optionGroupId = civicrm_api3('OptionGroup', 'Getvalue', $params);
      return $optionGroupId;
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find a valid option group for name '.$optionGroupName.', error from
        API OptionGroup Getvalue: ' . $ex->getMessage());
    }
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
    $params['option_group_id'] = self::getOptionGroupIdWithName('activity_type');
    if (!isset($params['name']) || empty($params['name'])) {
      throw new Exception('When trying to create an Activity Type name is a mandatory parameter and can not be empty');
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
        throw new Exception('Could not create activity type with name ' . $params['name']
          . ', error from API OptionValue Create: ' . $ex->getMessage());
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
      throw new Exception('When trying to create a Contact Sub Type name is a mandatory parameter and can not be empty');
    }
    if (!isset($params['label']) || empty($params['label'])) {
      $params['label'] = self::buildLabelFromName($params['name']);
    }
    if (self::getContactSubTypeWithName($params['name']) == FALSE) {
      try {
        $contactSubType = civicrm_api3('ContactType', 'Create', $params);
      } catch (CiviCRM_API3_Exception $ex) {
        throw new Exception('Could not create contact sub type with name '.$params['name']
          .', error from API ContactType Create: '.$ex->getMessage());
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
      throw new Exception('When trying to create a Relationship Type name_a_b and name_b_a are mandatory parameter and can not be empty');
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
        throw new Exception('Could not create relationship type with name '.$params['name_a_b']
          .', error from API RelationshipType Create: '.$ex->getMessage());
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
      throw new Exception('When trying to create a Custom Group name and extends are mandatory parameters and can not be empty');
    }
    if (!isset($params['title']) || empty($params['title'])) {
      $params['title'] = self::buildLabelFromName($params['name']);
    }
    if (self::getCustomGroupWithName($params['name']) == FALSE) {
      try {
        $customGroup = civicrm_api3('CustomGroup', 'Create', $params);
      } catch (CiviCRM_API3_Exception $ex) {
        throw new Exception('Could not create custom group with name '.$params['name']
          .' to extends '.$params['extends'].', error from API CustomGroup Create: '.$ex->getMessage());
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
      throw new Exception('When trying to create a Custom Field name and custom_group_id are mandatory parameters and can not be empty');
    }
    if (!isset($params['label']) || empty($params['label'])) {
      $params['label'] = self::buildLabelFromName($params['name'], $params['custom_group_id']);
    }
    if (self::getCustomFieldWithNameCustomGroupId($params['name'], $params['custom_group_id']) == FALSE) {
      try {
        $customField = civicrm_api3('CustomField', 'Create', $params);
      } catch (CiviCRM_API3_Exception $ex) {
        throw new Exception('Could not create custom field with name '.$params['name']
          .' in custom group '.$params['custom_group_id'].', error from API CustomField Create: '.$ex->getMessage());
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
      $maxId = max($maxId, $entry['attributes']['navID']);
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
}