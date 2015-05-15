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
   * Function to get activity type with name
   *
   * @param string $activityTypeName
   * @return array
   */
  public static function getActivityTypeWithName($activityTypeName) {
    $activityTypeOptionGroupId = self::getActivityTypeOptionGroupId();
    $params = array(
      'option_group_id' => $activityTypeOptionGroupId,
      'name' => $activityTypeName);
    try {
      $activityType = civicrm_api3('OptionValue', 'Getsingle', $params);
      return $activityType;
    } catch (CiviCRM_API3_Exception $ex) {
      return array();
    }
  }

  /**
   * Function to get the option group id of activity type
   *
   * @return int $activityTypeOptionGroupId
   * @throws Exception when option group not found
   * @access public
   * @static
   */
  public static function getActivityTypeOptionGroupId() {
    $params = array(
      'name' => 'activity_type',
      'return' => 'id');
    try {
      $activityTypeOptionGroupId = civicrm_api3('OptionGroup', 'Getvalue', $params);
      return $activityTypeOptionGroupId;
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find a valid option group for name activity_type, error from
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
   */
  public static function createActivityType($params) {
    $params['option_group_id'] = self::getActivityTypeOptionGroupId();
    if (!isset($params['name']) || empty($params['name'])) {
      throw new Exception('When trying to create an Activity Type name is a mandatory parameter and can not be empty');
    }

    if (empty($params['label']) || !isset($params['label'])) {
      $params['label'] = self::buildLabelFromName($params['name']);
    }
    if (!isset($params['is_active'])) {
      $params['is_active'] = 1;
    }
    try {
      $activityType = civicrm_api3('OptionValue', 'Create', $params);
      return $activityType['values'];
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not create activity type with name '.$params['name']
        .', error from API OptionValue Create: '.$ex->getMessage());
    }
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
}