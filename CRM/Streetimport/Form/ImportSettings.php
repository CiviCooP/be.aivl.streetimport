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

  function buildQuickForm() {
    $this->getImportSettings();
    $employeeList = $this->getEmployeeList();
    foreach ($this->importSettings as $settingName => $settingValues) {
      switch($settingName) {
        case 'admin_id':
          $this->add('select', $settingName, $settingValues['label'], $employeeList);
          break;
        case 'fundraiser_id':
          $this->add('select', $settingName, $settingValues['label'], $employeeList);
          break;
        default:
          $this->add('text', $settingName, $settingValues['label']);
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

  function postProcess() {
    $this->saveImportSettings($this->_submitValues);
    CRM_Core_Session::setStatus(ts('AIVL Import Settings saved'), 'Saved', 'success');
    $session = CRM_Core_Session::singleton();
    CRM_Utils_System::redirect($session->readUserContext());
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
  static function validateImportSettings($fields) {
    if (!isset($fields['admin_id']) || empty($fields['admin_id'])) {
      $errors['admin_id'] = 'This field can not be empty, you have to select a contact!';
    }
    if (!isset($fields['fundraiser_id']) || empty($fields['fundraiser_id'])) {
      $errors['fundraiser_id'] = 'This field can not be empty, you have to select a contact!';
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
  function getRenderableElementNames() {
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
   * Method to get the import settings from the config
   *
   * @access protected
   */
  protected function getImportSettings() {
    $extensionConfig = CRM_Streetimport_Config::singleton();
    $this->importSettings = $extensionConfig->getImportSettings();
  }
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
