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
class CRM_Streetimport_Form_DomainSettings extends CRM_Core_Form {

  /**
   * Overridden parent method to build the form
   *
   * @access public
   */
  public function buildQuickForm() {
    $this->settings_list = array('admin_id', 'fundraiser_id', 'phone_phone_type_id', 'mobile_phone_type_id','location_type_id','other_location_type_id','default_country_id','default_financial_type_id','female_gender_id', 'male_gender_id','unknown_gender_id','import_encoding','date_format','import_location', 'processed_location', 'failed_location', 'newsletter_group_id', 'accepted_yes_values');
    $config = CRM_Streetimport_Config::singleton();

    // DOMAIN
    $domain_list = CRM_Streetimport_Config::getDomains();
    $this->add('select', 'domain', $config->translate('Active Domain'), $domain_list, TRUE);

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

    parent::buildQuickForm();
  }

  /**
   * Overridden parent method to set default values
   * @return array
   */
  function setDefaultValues() {
    $config = CRM_Streetimport_Config::singleton();
    return array(
      'domain' => $config->getDomain()
      );
  }

  /**
   * Overridden parent method to deal with processing after succesfull submit
   *
   * @access public
   */
  public function postProcess() {
    $config = CRM_Streetimport_Config::singleton();

    // update domain
    if (!empty($this->_submitValues['domain'])) {
      $new_domain = $this->_submitValues['domain'];
      if ($new_domain != $config->getDomain()) {
        $config->setDomain($this->_submitValues['domain']);
        CRM_Core_Session::setStatus($config->translate('Domain changed'), 'Saved', 'success');
      }
    }

    $userContext = CRM_Core_Session::USER_CONTEXT;
    if (empty($userContext) || $userContext == 'userContext') {
      $session = CRM_Core_Session::singleton();
      $session->pushUserContext(CRM_Utils_System::url('civicrm', '', true));
    }
  }
}
