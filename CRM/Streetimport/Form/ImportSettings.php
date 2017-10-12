<?php
/*-------------------------------------------------------------+
| StreetImporter Record Handlers                               |
| Copyright (C) 2017 SYSTOPIA / CiviCooP                       |
| Author: Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>    |
|         B. Endres (SYSTOPIA) <endres@systopia.de>            |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

require_once 'CRM/Core/Form.php';

/**
 * Form controller class
 * Used to show and save import settings for be.aivl.streetimport
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Streetimport_Form_ImportSettings extends CRM_Core_Form {

  /** a list of processed settings */
  protected $settings_list = array();

  /**
   * Overridden parent method to build the form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->settings_list = array('admin_id', 'fundraiser_id', 'phone_phone_type_id', 'mobile_phone_type_id','location_type_id','other_location_type_id','default_country_id','default_financial_type_id','female_gender_id', 'male_gender_id','unknown_gender_id','import_encoding','date_format','import_location', 'processing_location', 'processed_location', 'failed_location', 'newsletter_group_id', 'accepted_yes_values');
    $config = CRM_Streetimport_Config::singleton();

    // contacts
    $employeeList = $config->getEmployeeList();
    $this->add('select', 'admin_id', $config->translate('Admin'), $employeeList, TRUE);
    $this->add('select', 'fundraiser_id', $config->translate('Fundraiser'), $employeeList, TRUE);

    // phone types
    $phoneTypeList = CRM_Streetimport_Utils::getOptionGroupList('phone_type');
    $this->add('select', 'phone_phone_type_id', $config->translate('Landline Phone Type'), $phoneTypeList, TRUE);
    $this->add('select', 'mobile_phone_type_id', $config->translate('Mobile Phone Type'), $phoneTypeList, TRUE);

    // address types
    $locationTypeList = $this->getLocationTypeList();
    $this->add('select', 'location_type_id', $config->translate('Main Address Type'), $locationTypeList, TRUE);
    $this->add('select', 'other_location_type_id', $config->translate('Secondary Address Type'), $locationTypeList, TRUE);

    // default country
    $countryList = $this->getCountryList();
    $this->add('select', 'default_country_id', $config->translate('Default Country'), $countryList, TRUE);

    // default newsletter group
    $groupList = $this->getGroupList();
    $this->add('select', 'newsletter_group_id', $config->translate('Newsletter Group'), $groupList, TRUE);

    // default financial types
    $financialTypeList = $this->getFinancialTypeList();
    $this->add('select', 'default_financial_type_id', $config->translate('Default Financial Type'), $financialTypeList, TRUE);

    // gender settings
    $genderList = CRM_Streetimport_Utils::getOptionGroupList('gender');
    $this->add('select', 'female_gender_id', $config->translate('Female Gender'), $genderList, TRUE);
    $this->add('select', 'male_gender_id', $config->translate('Male Gender'), $genderList, TRUE);
    $this->add('select', 'unknown_gender_id', $config->translate('Other Gender'), $genderList, TRUE);

    // import file settings
    $encodingList = $this->getEncodingList();
    $dateFormatList = CRM_Streetimport_Utils::getDateFormatList();
    $this->add('select', 'import_encoding', $config->translate('Default Encoding'), $encodingList, TRUE);
    $this->add('select', 'date_format', $config->translate('Default Date Format'), $dateFormatList, TRUE);
    $this->add('text', 'import_location', $config->translate('Import File Location'), array('size' => 50), TRUE);
    $this->add('text', 'processing_location', $config->translate('Processing Location'), array('size' => 50), TRUE);
    $this->add('text', 'processed_location', $config->translate('Processed File Location'), array('size' => 50), TRUE);
    $this->add('text', 'failed_location', $config->translate('Failed File Location'), array('size' => 50), TRUE);
    $this->add('text', 'accepted_yes_values', $config->translate('List of strings meaning "Yes" (comma separated)'), array('size' => 50), TRUE);

    // add domain settings
    $more_settings = $config->buildQuickFormSettings($this);
    $this->settings_list = array_merge($this->settings_list, $more_settings);
    $this->assign('domain_template', $config->getDomainSettingTemplate($this));

    // finally: add the buttons
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
    $config = CRM_Streetimport_Config::singleton();
    $defaults = array();
    foreach ($this->settings_list as $settingName) {
      $defaults[$settingName] = $config->getSetting($settingName);
    }
    return $defaults;
  }

  /**
   * Overridden parent method to deal with processing after succesfull submit
   *
   * @access public
   */
  public function postProcess() {
    $config = CRM_Streetimport_Config::singleton();

    // store the settings
    foreach ($this->_submitValues as $key => $value) {
      if (in_array($key, $this->settings_list)) {
        $config->setSetting($key, $value);
      }
    }
    $config->storeSettings();

    $userContext = CRM_Core_Session::USER_CONTEXT;
    if (empty($userContext) || $userContext == 'userContext') {
      $session = CRM_Core_Session::singleton();
      $session->pushUserContext(CRM_Utils_System::url('civicrm', '', true));
    }
    CRM_Core_Session::setStatus($config->translate('Settings saved'), 'Saved', 'success');
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
   * @return array|bool $errors or TRUE
   * @access public
   * @static
   */
  public static function validateImportSettings($fields) {
    $config = CRM_Streetimport_Config::singleton();
    if (!isset($fields['admin_id']) || empty($fields['admin_id'])) {
      $errors['admin_id'] = $config->translate('This field can not be empty, you have to select a contact!');
    }
    if (!isset($fields['fundraiser_id']) || empty($fields['fundraiser_id'])) {
      $errors['fundraiser_id'] = $config->translate('This field can not be empty, you have to select a contact!');
    }
    if (!isset($fields['phone_phone_type_id']) || empty($fields['phone_phone_type_id'])) {
      $errors['phone_phone_type_id'] = $config->translate('This field can not be empty, you have to select a phone type!');
    }
    if (!isset($fields['mobile_phone_type_id']) || empty($fields['mobile_phone_type_id'])) {
      $errors['mobile_phone_type_id'] = $config->translate('This field can not be empty, you have to select a phone type!');
    }
    if (!isset($fields['location_type_id']) || empty($fields['location_type_id'])) {
      $errors['location_type_id'] = $config->translate('This field can not be empty, you have to select a location type!');
    }
    if (!isset($fields['female_gender_id']) || empty($fields['female_gender_id'])) {
      $errors['female_gender_id'] = $config->translate('This field can not be empty, you have to select a gender!');
    }
    if (!isset($fields['male_gender_id']) || empty($fields['male_gender_id'])) {
      $errors['male_gender_id'] = $config->translate('This field can not be empty, you have to select a gender!');
    }
    if (!isset($fields['unknown_gender_id']) || empty($fields['unknown_gender_id'])) {
      $errors['unknown_gender_id'] = $config->translate('This field can not be empty, you have to select a gender!');
    }
    if (!isset($fields['other_location_type_id']) || empty($fields['other_location_type_id'])) {
      $errors['other_location_type_id'] = $config->translate('This field can not be empty, you have to select a location type!');
    } else {
      if ($fields['other_location_type_id'] == $fields['location_type_id']) {
        $errors['other_location_type_id'] = $config->translate('Other location type can not be the same as the main one');
      }
    }

    // validate folders
    $folderElements = array('import_location', 'processing_location', 'processed_location', 'failed_location');
    foreach ($folderElements as $folderElement) {
      if (!is_writable($fields[$folderElement])) {
        $errors[$folderElement] = $config->translate('This folder does not exists or you do not have sufficient permissions to write to the folder');
      }
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
   * get a list of all relevant file encodings
   */
  protected function getEncodingList() {
    $encodings = array();
    $mb_list = mb_list_encodings();
    foreach ($mb_list as $mb_encoding) {
      $encodings[$mb_encoding] = $mb_encoding;
    }
    return $encodings;
  }
}
