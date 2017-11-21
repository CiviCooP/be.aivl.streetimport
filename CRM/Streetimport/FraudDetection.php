<?php
/**
 * Class for possible fraud detection
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 24 Oct 2017
 * @license AGPL-3.0
 */
class CRM_Streetimport_FraudDetection {

  private $_fraudWarningActivityTypeId = NULL;
  private $_fraudWarningAssigneeId = NULL;
  private $_ibanReferenceTypeId = NULL;

  /**
   * CRM_Streetimport_FraudDetection constructor.
   */
  function __construct() {
    $this->_fraudWarningActivityTypeId = CRM_Streetimport_Config::singleton()->getFraudWarningActivityType();
    $this->_fraudWarningAssigneeId = CRM_Streetimport_Config::singleton()->getAdminContactID();
    try {
      $this->_ibanReferenceTypeId = civicrm_api3('OptionValue', 'getvalue', array(
        'option_group_id' => 'civicrm_banking.reference_types',
        'name' => 'IBAN',
        'return' => 'id',
      ));
    }
    catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find a CiviBanking bank account reference type with name IBAN in '
        .__METHOD__.', contact your system administrator.');
    }
  }

  /**
   * Method to check if the iban was already used for another contact than the parameter one
   *
   * @param $iban
   * @param $contactId
   * @return array
   * @throws Exception when iban or contact_id empty
   */
  function checkIbanAlreadyUsedForOtherContact($iban, $contactId) {
    $contacts = array();
    if (empty($iban || empty($contactId))) {
      throw new Exception('You can not check if an iban is already used with empty parameter iban and/or contact_id in '
        .__METHOD__.', contact your system administrator');
    }
    $query = "SELECT b.contact_id, c.display_name 
      FROM civicrm_bank_account_reference a
      JOIN civicrm_bank_account b ON a.ba_id = b.id
      JOIN civicrm_contact c ON b.contact_id = c.id
      WHERE a.reference_type_id = %1 AND a.reference LIKE %2 AND b.contact_id != %3";
    $dao = CRM_Core_DAO::executeQuery($query, array(
      1 => array($this->_ibanReferenceTypeId, 'Integer'),
      2 => array('%'.$iban.'%', 'String'),
      3 => array($contactId, 'Integer',)
    ));
    while ($dao->fetch()) {
      $contacts[$dao->contact_id] = $dao->display_name.' (ID '.$dao->contact_id.')';
    }
    return $contacts;
  }

  /**
   * Method to create the fraud warning activity
   *
   * @param array $activityData (expected at least target_id, warning_message)
   * @return bool|array
   */
  function createFraudWarning($activityData) {
    if (!isset($activityData['target_id']) || empty($activityData['target_id'])) {
      CRM_Core_Error::debug_log_message('Attempting to create a fraud detection activity without target_id in '
        .__METHOD__.', no activity created.');
      return FALSE;
    }
    if (!isset($activityData['warning_message']) || empty($activityData['warning_message'])) {
      CRM_Core_Error::debug_log_message('Attempting to create a fraud detection activity without warning_message in '
        .__METHOD__.', no activity created');
      return FALSE;
    }
    // build url for recruiter contact if set
    if (isset($activityData['recruiter_id']) && !empty($activityData['recruiter_id'])) {
      $activityData['recruiter_url'] = CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid='.$activityData['recruiter_id'], true);
    }
    // build links for other contacts if set
    if (isset($activityData['other_contacts']) && !empty($activityData['other_contacts'])) {
      $activityData['other_contact_urls'] = array();
      foreach ($activityData['other_contacts'] as $otherContactId => $otherContact) {
        $activityData['other_contact_urls'][$otherContactId] = CRM_Utils_System::url('civicrm/contact/view', 'reset=1&cid='.$otherContactId, true);
      }
    }
    // build link for mandate if set
    if (isset($activityData['contribution_recur_id']) && !empty($activityData['contribution_recur_id'])) {
      $activityData['mandate_url'] = CRM_Utils_System::url('civicrm/contact/view/contributionrecur', '&reset=1&id='.$activityData['contribution_recur_id'].'&cid='.$activityData['target_id'], true);
    } else {
      if (isset($activityData['contribution_id']) && !empty($activityData['contribution_id'])) {
        $activityData['mandate_url'] = CRM_Utils_System::url('civicrm/contact/view/contribution', 'reset=1&id='.$activityData['contribution_id'].'&cid='.$activityData['target_id'].'&action=view', true);
      }
    }
    // build param list for activity
    $activityParams = array(
      'activity_type_id' => $this->_fraudWarningActivityTypeId,
      'assignee_id' => $this->_fraudWarningAssigneeId,
      'target_id' => $activityData['target_id'],
      'subject' => $activityData['warning_message'],
      'activity_date_time' => date('Ymd h:i:s'),
      'details' => CRM_Streetimport_Utils::renderTemplate('', $activityData),
    );
  }

}