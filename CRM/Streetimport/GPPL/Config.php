<?php
/*-------------------------------------------------------------+
| Greenpeace Poland StreetImporter Record Handlers             |
| Copyright (C) 2018 Greenpeace CEE                            |
| Author: P. Figel (pfigel@greenpeace.org)                     |
|         M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * Class following Singleton pattern for specific extension configuration
 */
class CRM_Streetimport_GPPL_Config extends CRM_Streetimport_Config {
  private $_activeMembershipStatuses;
  private $_cancelledMembershipStatus;
  private $_pausedMembershipStatus;

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
   * get the default set of handlers
   *
   * @return an array of handler instances
   */
  public function getHandlers($logger) {
    return array(
      new CRM_Streetimport_GPPL_Handler_BankAuthorizationHandler($logger),
    );
  }

  public function getActiveMembershipStatuses() {
    if (is_null($this->_activeMembershipStatuses)) {
      $result = civicrm_api3('MembershipStatus', 'get', array(
        'is_current_member' => 1,
        'return'            => 'id'));
      $this->_activeMembershipStatuses = array();
      foreach ($result['values'] as $status) {
        $this->_activeMembershipStatuses[] = $status['id'];
      }
    }
    return $this->_activeMembershipStatuses;
  }

  public function getCancelledMembershipStatus() {
    if (is_null($this->_cancelledMembershipStatus)) {
      $this->_cancelledMembershipStatus = civicrm_api3('MembershipStatus', 'getvalue', [
        'return' => 'id',
        'name' => 'Cancelled',
      ]);
    }
    return $this->_cancelledMembershipStatus;
  }

  public function getPausedMembershipStatus() {
    if (is_null($this->_pausedMembershipStatus)) {
      $this->_pausedMembershipStatus = civicrm_api3('MembershipStatus', 'getvalue', [
        'return' => 'id',
        'name' => 'Paused',
      ]);
    }
    return $this->_pausedMembershipStatus;
  }

  /**
   * calculate the next valid cycle day
   *
   * @param $start_date
   * @param $now
   *
   * @return int
   * @throws \Exception
   */
  public function getNextCycleDay($start_date, $now) {
    // TODO: use SEPA function

    // find the right start date
    $creditor = CRM_Sepa_Logic_Settings::defaultCreditor();
    $buffer_days = (int) CRM_Sepa_Logic_Settings::getSetting("pp_buffer_days") + (int) CRM_Sepa_Logic_Settings::getSetting("batching.FRST.notice", $creditor->id);
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
   *
   * @return string
   */
  public function getCycleDays() {
    return CRM_Sepa_Logic_Settings::getListSetting("cycledays", range(1, 28), $this->getCreditorID());
  }

  /**
   * get the SEPA creditor ID to be used for all mandates
   *
   * @return int
   */
  public function getCreditorID() {
    $default_creditor = CRM_Sepa_Logic_Settings::defaultCreditor();
    if (!empty($default_creditor->id)) {
      return $default_creditor->id;
    }
    else {
      return 1;
    }
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
