<?php

require_once 'streetimport.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function streetimport_civicrm_config(&$config) {
  _streetimport_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @param $files array(string)
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function streetimport_civicrm_xmlMenu(&$files) {
  _streetimport_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function streetimport_civicrm_install() {
  /*
   * only install if CiviSepa, CiviBanking and Little Bic Extension are installed
   */
  $installedExtensionsResult = civicrm_api3('Extension', 'Get', array());
  foreach($installedExtensionsResult['values'] as $value){
      if ($value['status'] == 'installed') {
          $installedExtensions[] = $value['key'];
      }
  }
  $requiredExtensions = array(
      'org.project60.sepa' => 'SEPA direct debit (org.project60.sepa)',
      'org.project60.banking' => 'CiviBanking (org.project60.banking)',
      'org.project60.bic' => 'Little Bic Extension (org.project60.bic)'
  );
  $missingExtensions = array_diff(array_keys($requiredExtensions), $installedExtensions);
  if (count($missingExtensions) == 1) {
    $missingExtensionsText = $missingExtensions[0];
    CRM_Core_Error::fatal("The Street Recruitment Import extension requires the following extension: '$missingExtensionsText' but it is not currently installed. Please install it before continuing.");
  }
  elseif (count($missingExtensions) > 1) {
    $missingExtensionsText = implode("', '", $missingExtensions);
    CRM_Core_Error::fatal("The Street Recruitment Import extension requires the following extensions: '$missingExtensionsText' but they are not currently installed. Please install them before continuing.");
  }
  _streetimport_civix_civicrm_install();
  CRM_Streetimport_Config::singleton('install');
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function streetimport_civicrm_uninstall() {
  _streetimport_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function streetimport_civicrm_enable() {
  _streetimport_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function streetimport_civicrm_disable() {
  _streetimport_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed
 *   Based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function streetimport_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _streetimport_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function streetimport_civicrm_managed(&$entities) {
  _streetimport_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function streetimport_civicrm_caseTypes(&$caseTypes) {
  _streetimport_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function streetimport_civicrm_angularModules(&$angularModules) {
_streetimport_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function streetimport_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _streetimport_civix_civicrm_alterSettingsFolders($metaDataFolders);
}
/**
 * Implements hook_civicrm_navigationMenu
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 */
function streetimport_civicrm_navigationMenu(&$params) {

  //add menu entry for Import settings to Administer>CiviContribute menu
  $importSettingsUrl = 'civicrm/admin/setting/aivl_import_settings';
  // now, by default we want to add it to the CiviContribute Administer menu -> find it
  $administerMenuId = 0;
  $administerCiviContributeMenuId = 0;
  foreach ($params as $key => $value) {
    if ($value['attributes']['name'] == 'Administer') {
      $administerMenuId = $key;
      foreach ($params[$administerMenuId]['child'] as $childKey => $childValue) {
        if ($childValue['attributes']['name'] == 'CiviContribute') {
          $administerCiviContributeMenuId = $childKey;
          break;
        }
      }
      break;
    }
  }
  if (empty($administerMenuId)) {
    error_log('be.aivl.streetimport: Cannot find parent menu Administer/CiviContribute for '.$importSettingsUrl);
  } else {
    $importSettingsMenu = array (
      'label' => ts('AIVL Import Settings',array('domain' => 'be.aivl.streetimport')),
      'name' => 'AIVL Import Settings',
      'url' => $importSettingsUrl,
      'permission' => 'administer CiviCRM',
      'operator' => NULL,
      'parentID' => $administerCiviContributeMenuId,
      'navID' => CRM_Streetimport_Utils::createUniqueNavID($params[$administerMenuId]['child']),
      'active' => 1
    );
    CRM_Streetimport_Utils::addNavigationMenuEntry($params[$administerMenuId]['child'][$administerCiviContributeMenuId], $importSettingsMenu);
  }
}

function streetimport_civicrm_buildForm($formName, &$form) {
  if($formName =='CRM_Activity_Form_Activity'){
    //check if the activity type is 'streetRecruitment' or 'welcomeCall'
    foreach(civicrm_api3('OptionValue', 'get', array(
      'option_group_id' => 'activity_type',
      'name' => array('IN' => array("welcomeCall", "streetRecruitment")),
      'return' => 'value'
    ))['values'] as $value){
      $actvityTypeIds[]=$value['value'];
    }
    if(in_array($form->_activityTypeId, $actvityTypeIds) && $form->urlPath === array('civicrm', 'activity')){
      _streetimport_civicrm_addCreateMandateButton($form);
    }
  }
}

function _streetimport_civicrm_addCreateMandateButton(&$form){
    $activityType = civicrm_api3('OptionValue', 'getsingle', array('option_group_id' => 'activity_type', 'value' => $form->_activityTypeId));
    if ($activityType['name'] == 'streetRecruitment') {
        $fieldPrefix = 'new_';
    } elseif ($activityType['name'] == 'welcomeCall') {
        $fieldPrefix = 'wc_';
    }

    // only show if there is not already a mandate in existence with this rererence
    $cfr = civicrm_api3('CustomField', 'getsingle', array('name' => $fieldPrefix.'sdd_mandate'));
    $ar = civicrm_api3('Activity', 'getsingle', array('id' => $form->_activityId));

    $form->_activityId;
    $smr = civicrm_api3('SepaMandate', 'get', array(
      'reference' => $ar['custom_'.$cfr['id']],
    ));

    if(!$smr['count']){
      CRM_Core_Region::instance('page-body')->add(array(
        'template' => 'CRM/Streetimport/Extras/ActivityFormCreateMandateButton.tpl',
      ));
    }
}
