<?php
/*-------------------------------------------------------------+
| GP StreetImporter Record Handlers                            |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * Class following Singleton pattern for specific extension configuration
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 30 April 2015
 * @license AGPL-3.0
 */
class CRM_Streetimport_GP_Config extends CRM_Streetimport_Config {

  /** custom field cache */
  protected $gp_custom_fields = array();
  protected $gp_groups = array();

  /**
   * Constructor method
   *
   * @param string $context
   */
  function __construct() {
    CRM_Streetimport_Config::__construct();
  }


  /**
   * get a list (id => name) of the relevant employees
   */
  public function getEmployeeList() {
    // get user list
    $employees = parent::getEmployeeList();

    // currently, everybody with an external ID starting with 'USER-' is a user
    //  so we want to add those
    $result = civicrm_api3('Contact', 'get', array(
      'return'              => 'display_name,id',
      'external_identifier' => array('LIKE' => "USER-%"),
      'options'             => array('limit' => 0),
    ));
    foreach ($result['values'] as $contact_id => $contact) {
      $employees[$contact['id']] = $contact['display_name'];
    }

    return $employees;
  }


  /**
   * get the default set of handlers
   *
   * @return an array of handler instances
   */
  public function getHandlers($logger) {
    return array(
      new CRM_Streetimport_GP_Handler_TEDITelephoneRecordHandler($logger),
      new CRM_Streetimport_GP_Handler_TEDIContactRecordHandler($logger),
    );
  }

  /**
   * Get a group ID
   */
  public function getGPGroupID($group_name) {
    $group = $this->getGPGroup($group_name);
    return $group['id'];
  }

  /**
   * Get group data based on name (title field)
   */
  public function getGPGroup($group_name) {
    if (!isset($this->gp_groups[$group_name]) || !is_array($this->gp_groups[$group_name])) {
      // load custom field data
      try {
        $this->gp_groups[$group_name] = civicrm_api3('Group', 'getsingle', array('title' => $group_name));
      } catch (Exception $e) {
      $this->gp_groups[$group_name] = array(
        'is_error' => 1,
        'error_msg' => $e->getMessage());
      }
    }

    return $this->gp_groups[$group_name];
  }

  /**
   * Look up custom fields and return full field data
   */
  public function getGPCustomField($field_name) {
    if (!isset($this->gp_custom_fields[$field_name]) || !is_array($this->gp_custom_fields[$field_name])) {
      // load custom field data
      try {
        $this->gp_custom_fields[$field_name] = civicrm_api3('CustomField', 'getsingle', array('name' => $field_name));
      } catch (Exception $e) {
      $this->gp_custom_fields[$field_name] = array(
        'is_error' => 1,
        'error_msg' => $e->getMessage());
      }
    }

    return $this->gp_custom_fields[$field_name];
  }

  /**
   * Look up custom fields and return full field data
   */
  public function getGPCustomFieldKey($field_name) {
    $custom_field = $this->getGPCustomField($field_name);
    return "custom_{$custom_field['id']}";
  }
}
