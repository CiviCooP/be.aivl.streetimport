<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 * Used to show and save import settings for be.aivl.streetimport
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Streetimport_Form_ImportSettings extends CRM_Core_Form {

  protected $importSettings = array();

  /**
   * Overridden parent method to build the form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->getImportSettings();
    $employeeList = $this->getEmployeeList();
    $groupList = $this->getGroupList();
    $membershipTypeList = $this->getMembershipTypeList();
    $phoneTypeList = CRM_Streetimport_Utils::getOptionGroupList('phone_type');
    $locationTypeList = $this->getLocationTypeList();
    $countryList = $this->getCountryList();
    $financialTypeList = $this->getFinancialTypeList();
    $prefixList = CRM_Streetimport_Utils::getOptionGroupList('individual_prefix');
    $genderList = CRM_Streetimport_Utils::getOptionGroupList('gender');

    foreach ($this->importSettings as $settingName => $settingValues) {
      switch($settingName) {
        case 'admin_id':
          $this->add('select', $settingName, $settingValues['label'], $employeeList, TRUE);
          break;
        case 'fundraiser_id':
          $this->add('select', $settingName, $settingValues['label'], $employeeList, TRUE);
          break;
        case 'newsletter_group_id':
          $this->add('select', $settingName, $settingValues['label'], $groupList, TRUE);
          break;
        case 'membership_type_id':
          $this->add('select', $settingName, $settingValues['label'], $membershipTypeList, TRUE);
          break;
        case 'phone_phone_type_id':
          $this->add('select', $settingName, $settingValues['label'], $phoneTypeList, TRUE);
          break;
        case 'mobile_phone_type_id':
          $this->add('select', $settingName, $settingValues['label'], $phoneTypeList, TRUE);
          break;
        case 'location_type_id':
          $this->add('select', $settingName, $settingValues['label'], $locationTypeList, TRUE);
          break;
        case 'other_location_type_id':
          $this->add('select', $settingName, $settingValues['label'], $locationTypeList, TRUE);
          break;
        case 'default_country_id':
          $this->add('select', $settingName, $settingValues['label'], $countryList, TRUE);
          break;
        case 'default_financial_type_id':
          $this->add('select', $settingName, $settingValues['label'], $financialTypeList, TRUE);
          break;
        case 'female_gender_id':
          $this->add('select', $settingName, $settingValues['label'], $genderList, TRUE);
          break;
        case 'male_gender_id':
          $this->add('select', $settingName, $settingValues['label'], $genderList, TRUE);
          break;
        case 'unknown_gender_id':
          $this->add('select', $settingName, $settingValues['label'], $genderList, TRUE);
          break;
        case 'household_prefix_id':
          $prefixSelect = $this->addElement('advmultiselect', $settingName, $settingValues['label'], $prefixList,
            array('size' => 5, 'style' => 'width:300px', 'class' => 'advmultselect'),TRUE);
          $prefixSelect->setButtonAttributes('add', array('value' => ts('Household >>')));
          $prefixSelect->setButtonAttributes('remove', array('value' => ts('<< Individual')));
          break;
        default:
          $this->add('text', $settingName, $settingValues['label'], array(), TRUE);
          break;
      }
    }
    $this->addButtons(array(
      array(
        'type' => 'next',
        'name' => ts('Save'),
        'isDefault' => TRUE,
      ),
      array(
        'type' => 'cancel',
        'name' => ts('Cancel')
      ),
    ));

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }

  /**
   * Overridden parent method to set default values
   * @return array
   */
  function setDefaultValues() {
    $defaults = array();
    foreach ($this->importSettings as $settingName => $settingValues) {
      $defaults[$settingName] = $settingValues['value'];
    }
    return $defaults;
  }

  /**
   * Overridden parent method to deal with processing after succesfull submit
   *
   * @access public
   */
  public function postProcess() {
    $this->saveImportSettings($this->_submitValues);
    $userContext = CRM_Core_Session::USER_CONTEXT;
    if (empty($userContext) || $userContext == 'userContext') {
      $session = CRM_Core_Session::singleton();
      $session->pushUserContext(CRM_Utils_System::url('civicrm', '', true));
    }
    CRM_Core_Session::setStatus(ts('AIVL Import Settings saved'), 'Saved', 'success');
  }

  /**
   * Overridden parent method to add validation rules
   */
  function addRules() {
    $this->addFormRule(array('CRM_Streetimport_Form_ImportSettings', 'validateImportSettings'));
  }

  /**
   * Function to validate the import settings
   *
   * @param array $fields
   * @return array $errors or TRUE
   * @access public
   * @static
   */
  public static function validateImportSettings($fields) {
    if (!isset($fields['admin_id']) || empty($fields['admin_id'])) {
      $errors['admin_id'] = 'This field can not be empty, you have to select a contact!';
    }
    if (!isset($fields['fundraiser_id']) || empty($fields['fundraiser_id'])) {
      $errors['fundraiser_id'] = 'This field can not be empty, you have to select a contact!';
    }
    if (!isset($fields['newsletter_group_id']) || empty($fields['newsletter_group_id'])) {
      $errors['newsletter_group_id'] = 'This field can not be empty, you have to select a group!';
    }
    if (!isset($fields['membership_type_id']) || empty($fields['membership_type_id'])) {
      $errors['membership_type_id'] = 'This field can not be empty, you have to select a membership type!';
    }
    if (!isset($fields['phone_phone_type_id']) || empty($fields['phone_phone_type_id'])) {
      $errors['phone_phone_type_id'] = 'This field can not be empty, you have to select a phone type!';
    }
    if (!isset($fields['mobile_phone_type_id']) || empty($fields['mobile_phone_type_id'])) {
      $errors['mobile_phone_type_id'] = 'This field can not be empty, you have to select a phone type!';
    }
    if (!isset($fields['location_type_id']) || empty($fields['location_type_id'])) {
      $errors['location_type_id'] = 'This field can not be empty, you have to select a location type!';
    }
    if (!isset($fields['female_gender_id']) || empty($fields['female_gender_id'])) {
      $errors['female_gender_id'] = 'This field can not be empty, you have to select a gender!';
    }
    if (!isset($fields['male_gender_id']) || empty($fields['male_gender_id'])) {
      $errors['male_gender_id'] = 'This field can not be empty, you have to select a gender!';
    }
    if (!isset($fields['unknown_gender_id']) || empty($fields['unknown_gender_id'])) {
      $errors['unknown_gender_id'] = 'This field can not be empty, you have to select a gender!';
    }
    if (!isset($fields['other_location_type_id']) || empty($fields['other_location_type_id'])) {
      $errors['other_location_type_id'] = 'This field can not be empty, you have to select a location type!';
    } else {
      if ($fields['other_location_type_id'] == $fields['location_type_id']) {
        $errors['other_location_type_id'] = 'Other location type can not be the same as the main one';
      }
    }
    if (!ctype_digit($fields['offset_days'])) {
      $errors['offset_days'] = 'This field can only contain numbers!';
    }
    if (empty($errors)) {
      return TRUE;
    } else {
      return $errors;
    }
  }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames() {
    $elementNames = array();
    foreach ($this->_elements as $element) {
      /** @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
      if (!empty($label)) {
        $elementNames[] = $element->getName();
      }
    }
    return $elementNames;
  }

  /**
   * Method to get list of active groups to select newsletter group from
   *
   * @return array
   * @access protected
   */
  protected function getGroupList() {
    $groupList = array();
    $params = array(
      'is_active' => 1,
      'options' => array('limit' => 0));
    try {
      $activeGroups = civicrm_api3('Group', 'Get', $params);
    } catch (CiviCRM_API3_Exception $ex) {}
    foreach ($activeGroups['values'] as $activeGroup) {
      $groupList[$activeGroup['id']] = $activeGroup['title'];
    }
    $groupList[0] = ts('- select -');
    asort($groupList);
    return $groupList;
  }

  /**
   * Method to get list of active membership types
   *
   * @return array
   * @access protected
   */
  protected function getMembershipTypeList() {
    $membershipTypeList = array();
    $params = array(
      'is_active' => 1,
      'options' => array('limit' => 99999));
    try {
      $activeMembershipTypes = civicrm_api3('MembershipType', 'Get', $params);
    } catch (CiviCRM_API3_Exception $ex) {}
    foreach ($activeMembershipTypes['values'] as $activeMembershipType) {
      $membershipTypeList[$activeMembershipType['id']] = $activeMembershipType['name'];
    }
    $membershipTypeList[0] = ts('- select -');
    asort($membershipTypeList);
    return $membershipTypeList;
  }

  /**
   * Method to get list of active location types
   *
   * @return array
   * @access protected
   */
  protected function getLocationTypeList() {
    $locationTypeList = array();
    $params = array(
      'is_active' => 1,
      'options' => array('limit' => 99999));
    try {
      $activeLocationTypes = civicrm_api3('LocationType', 'Get', $params);
    } catch (CiviCRM_API3_Exception $ex) {}
    foreach ($activeLocationTypes['values'] as $activeLocationType) {
      $locationTypeList[$activeLocationType['id']] = $activeLocationType['display_name'];
    }
    $locationTypeList[0] = ts('- select -');
    asort($locationTypeList);
    return $locationTypeList;
  }

  /**
   * Method to get AIVL employees
   * @return array
   * @throws Exception
   */
  protected function getEmployeeList() {
    $employeeList = array();
    $extensionConfig = CRM_Streetimport_Config::singleton();
    $legalName = $extensionConfig->getAivlLegalName();
    $relationshipTypes = array('Personeelslid', 'Employee of', 'Vrijwilliger');
    $aivlParams = array(
      'legal_name' => $legalName,
      'return' => 'id');
    try {
      $aivlContactId = civicrm_api3('Contact', 'Getvalue', $aivlParams);
      foreach ($relationshipTypes as $relationshipTypeName) {
        $relationshipParams = array(
          'is_active' => 1,
          'contact_id_b' => $aivlContactId,
          'name_a_b' => $relationshipTypeName,
          'options' => array('limit' => 999));
        try {
          $foundRelationships = civicrm_api3('Relationship', 'Get', $relationshipParams);
          foreach ($foundRelationships['values'] as $foundRelation) {
            $employeeList[$foundRelation['contact_id_a']] = CRM_Streetimport_Utils::getContactName($foundRelation['contact_id_a']);
          }
        } catch (CiviCRM_API3_Exception $ex) {}
      }
      array_unique($employeeList);
      $employeeList[0] = ts('- select -');
      asort($employeeList);
    } catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Error retrieving contact with legal name '.$legalName
        .', error from API Contact Getsingle: '.$ex->getMessage());
    }
    return $employeeList;
  }

  /**
   * Method to get the financial type list
   *
   * @return array $financialTypeList
   * @access protected
   */
  protected function getFinancialTypeList() {
    $financialTypeList = array();
    $query = 'SELECT * FROM civicrm_financial_type WHERE is_active = %1';
    $params = array(1 => array(1, 'Integer'));
    $dao = CRM_Core_DAO::executeQuery($query, $params);
    while ($dao->fetch()) {
      $financialTypeList[$dao->id] = $dao->name;
    }
    $financialTypeList[0] = ts('- select -');
    asort($financialTypeList);
    return $financialTypeList;
  }

  /**
   * Method to get the country list
   *
   * @return array $countryList
   * @access protected
   */
  protected function getCountryList() {
    $countryList = array();
    $query = 'SELECT * FROM civicrm_country';
    $dao = CRM_Core_DAO::executeQuery($query);
    while ($dao->fetch()) {
      $countryList[$dao->id] = $dao->name;
    }
    $countryList[0] = ts('- select -');
    asort($countryList);
    return $countryList;
  }

  /**
   * Method to get the import settings from the config
   *
   * @access protected
   */
  protected function getImportSettings() {
    $extensionConfig = CRM_Streetimport_Config::singleton();
    $this->importSettings = $extensionConfig->getImportSettings();
  }

  /**
   * Method to save the import settings
   *
   * @param array $formValues
   */
  protected function saveImportSettings($formValues) {
    $saveValues = array();
    foreach ($formValues as $key => $value) {
      if ($key != 'qfKey' && $key != 'entryURL' && substr($key,0,3) != '_qf') {
        $saveValues[$key] = $value;
      }
    }
    $extensionConfig = CRM_Streetimport_Config::singleton();
    $extensionConfig->saveImportSettings($saveValues);
  }
}
