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
 */
class CRM_Streetimport_GP_Config extends CRM_Streetimport_Config {

  /** custom field cache */
  protected $gp_custom_fields = array();
  protected $gp_groups = array();
  protected $active_membership_statuses = NULL;

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
      new CRM_Streetimport_GP_Handler_DDRecordHandler($logger),
      new CRM_Streetimport_GP_Handler_PostRetourRecordHandler($logger),
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

  /**
   * get a list of statuses considered active
   */
  public function getActiveMembershipStatuses() {
    if ($this->active_membership_statuses === NULL) {
      $result = civicrm_api3('MembershipStatus', 'get', array(
        'is_current_member' => 1,
        'return'            => 'id'));
      $this->active_membership_statuses = array();
      foreach ($result['values'] as $status) {
        $this->active_membership_statuses[] = $status['id'];
      }
    }

    return $this->active_membership_statuses;
  }

  /**
   * get the default status for a cancelled memership
   */
  public function getMembershipCancelledStatus() {
    // TODO: look up?
    return 6; // Cancelled
  }

  /**
   * get the activity type id of the 'Response' activity
   */
  public function getResponseActivityType() {
    return CRM_Core_OptionGroup::getValue('activity_type', 'Response', 'name');
  }

  /**
   * calculate the next valid cycle day
   */
  public function getNextCycleDay($start_date, $now) {
    // TODO: use SEPA function

    // find the right start date
    $buffer_days = 2; // TODO: more?
    $now                 = strtotime($now);
    $start_date          = strtotime($start_date);
    $earliest_start_date = strtotime("+{$buffer_days} day", $now);
    if ($start_date < $earliest_start_date) {
      $start_date = $earliest_start_date;
    }

    // now: find the next valid start day
    $cycle_days = $this->getCycleDays();
    $safety_counter = 32;
    while (!in_array(date('j', $start_date), $cycle_days)) {
      $start_date = strtotime('+ 1 day', $start_date);
      $safety_counter -= 1;
      if ($safety_counter == 0) {
        throw new Exception("There's something wrong with the getNextCycleDay method.");
      }
    }
    return (int) date('j', $start_date);
  }

  /**
   * Get the list of allowed cycle days
   */
  public function getCycleDays() {
    // TODO: restrict?
    return CRM_Sepa_Logic_Settings::getListSetting("cycledays", range(1, 28), $this->getCreditorID());
  }

  /**
   * get the SEPA creditor ID to be used for all mandates
   */
  public function getCreditorID() {
    // TODO: use default creditor?
    $default_creditor = CRM_Sepa_Logic_Settings::defaultCreditor();
    if (!empty($default_creditor->id)) {
      return $default_creditor->id;
    } else {
      return 1;
    }
  }

  /**
   * returns the list of attributes that are required for
   * a valid address
   */
  public function getRequiredAddressAttributes() {
    return array('postal_code', 'street_address');
  }

  /**
   * returns the list of attributes that are required for
   * a valid address
   */
  public function getAllAddressAttributes() {
    return array('postal_code', 'street_address', 'city', 'country_id');
  }

  /**
   * Check if the "Little BIC Extension" is available
   */
  public function isLittleBicExtensionAccessible() {
    return CRM_Sepa_Logic_Settings::isLittleBicExtensionAccessible();
  }

  /**
   * Should processing of the whole file stop if no handler
   * was found for a line?
   */
  public function stopProcessingIfNoHanderFound() {
    return TRUE;
  }

}
