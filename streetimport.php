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
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function streetimport_civicrm_install() {
  _streetimport_civix_civicrm_install();
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
