<?php

/**
 * Collection of upgrade steps.
 */
class CRM_Streetimport_Upgrader extends CRM_Streetimport_Upgrader_Base {
  /**
   * Upgrade 1001 - remove recruitment type from custom fields and tables (issue #29) with api
   * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
   * @date 30 Jun 2015
   */
  public function upgrade_1001() {
    $this->ctx->log->info('Applying update 1001 (remove recruitment type from custom groups)');
    $config = CRM_Streetimport_Config::singleton();
    $customGroupParams = array($config->getStreetRecruitmentCustomGroup(), $config->getWelcomeCallCustomGroup());
    foreach ($customGroupParams as $customGroupId) {
      $customFields = civicrm_api3('CustomField', 'Get', array('custom_group_id' => $customGroupId));
      foreach ($customFields['values'] as $customField) {
        if ($customField['name'] == 'new_recruit_type' || $customField['name'] == 'wc_recruit_type') {
          civicrm_api3('CustomField', 'Delete', array('id' => $customField['id']));
        }
      }
    }
    return TRUE;
  }

}
