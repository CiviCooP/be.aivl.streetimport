<?php

/**
 * Form controller class
 * Used to add or delete the prefix rules
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Streetimport_Form_PrefixRule extends CRM_Core_Form {
  public function buildQuickForm() {
    CRM_Utils_System::setTitle('New AIVL Import Settings Prefix Rule');

    // add form elements
    $this->add('text', 'import_prefix', ts('Prefix in Import file'), true);
    $this->add('select', 'civicrm_prefix', ts('CiviCRM Prefix'), $this->getPrefixOptions(), TRUE);
    $this->addRadio('gender', ts('Gender?'), $this->getGenderOptions(), NULL, NULL , TRUE);
    $this->addButtons(array(
      array('type' => 'next', 'name' => ts('Save'), 'isDefault' => TRUE,),
      array('type' => 'cancel', 'name' => ts('Cancel')),
    ));

    // export form elements
    $this->assign('elementNames', $this->getRenderableElementNames());
    parent::buildQuickForm();
  }
  public function preProcess() {
    $session = CRM_Core_Session::singleton();
    $session->pushUserContext(CRM_Utils_System::url('civicrm/admin/setting/aivl_import_settings', 'reset=1', true));
    parent::preProcess();
  }

  /**
   * Method to get the civicrm prefix options
   *
   * @return array
   */
  private function getPrefixOptions() {
    $result = array();
    try {
      $prefixes = civicrm_api3('OptionValue', 'get', array(
        'option_group_id' => 'individual_prefix',
        'is_active' => 1,
        'options' => array('limit' => 0)));
      foreach ($prefixes['values'] as $prefix) {
        $result[$prefix['value']] = $prefix['label'];
      }
    } catch (CiviCRM_API3_Exception $ex) {}
    return $result;
  }

  /**
   * Method to get the gender options
   *
   * @return array
   */
  private function getGenderOptions() {
    $result = array();
    try {
      $genders = civicrm_api3('OptionValue', 'get', array(
        'option_group_id' => 'gender',
        'is_active' => 1,
        'options' => array('limit' => 0)));
      foreach ($genders['values'] as $gender) {
        $result[$gender['value']] = $gender['label'];
      }
    } catch (CiviCRM_API3_Exception $ex) {}
    return $result;
  }

  public function postProcess() {
    $values = $this->exportValues();
    $prefixRule = new CRM_Streetimport_PrefixRule();
    $prefixRule->add($values);
    CRM_Core_Session::setStatus(ts('New Prefix Rule Saved'), 'Prefix Rule Saved', 'success');
    parent::postProcess();
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
   * Overridden parent method to add validation rules
   */
  function addRules() {
    $this->addFormRule(array('CRM_Streetimport_Form_PrefixRule', 'validateImportPrefix'));
  }

  /**
   * Method to validate import prefix (only one rule for each import value allowed)
   *
   * @param $fields
   * @return array|bool
   * @static
   */
  public static function validateImportPrefix($fields) {
    $config = CRM_Streetimport_Config::singleton();
    if (isset($fields['import_prefix'])) {
      $prefixRule = new CRM_Streetimport_PrefixRule();
      if ($prefixRule->importPrefixExists($fields['import_prefix']) == TRUE) {
        $errors['import_prefix'] = $config->translate('Only one prefix rule allowed for an import prefix, this one already has one!');
        return $errors;
      }
      return TRUE;
    }

  }
}
