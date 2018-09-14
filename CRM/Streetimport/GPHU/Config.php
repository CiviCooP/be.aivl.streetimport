<?php
/*-------------------------------------------------------------+
| Greenpeace Hungary StreetImporter Record Handlers             |
| Copyright (C) 2018 Greenpeace CEE                            |
| Author: P. Figel (pfigel@greenpeace.org)                     |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * Class following Singleton pattern for specific extension configuration
 */
class CRM_Streetimport_GPHU_Config extends CRM_Streetimport_Config {
  public function __construct() {
    CRM_Streetimport_Config::__construct();
  }

  /**
   * get a list (id => name) of the relevant employees
   *
   * @return array
   * @throws \CiviCRM_API3_Exception
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
   * get the default set of handlers
   *
   * @return an array of handler instances
   */
  public function getHandlers($logger) {
    return array(
      new CRM_Streetimport_GPHU_Handler_EngagingNetworksHandler($logger),
    );
  }

  /**
   * Should processing of the whole file stop if no handler was found for a
   * line?
   *
   * @return bool
   */
  public function stopProcessingIfNoHanderFound() {
    return TRUE;
  }

}
