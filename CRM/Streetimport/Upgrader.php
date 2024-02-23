<?php

/**
 * Collection of upgrade steps.
 */
class CRM_Streetimport_Upgrader extends CRM_Extension_Upgrader_Base
{
  /**
   * Upgrade 1001 - remove recruitment type from custom fields and tables (issue #29) with api
   * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
   * @date 30 Jun 2015
   */
  public function upgrade_1001()
  {
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

  /**
   * Upgrade 1002 - check task 97: https://civicoop.plan.io/issues/97
   */
  public function upgrade_1002() {
    $this->ctx->log->info('Applying update 1002 (fix contact sub type Leverancier)');

    // STEP 1: change contact sub type for all contacts with Leverancier to Wervingsorganisatie
    $this->updateContactSubTypes();

    // STEP 2: update relationship type settings
    $updateRelQry = 'UPDATE civicrm_relationship_type SET contact_sub_type_a = %1
      WHERE contact_sub_type_a = %2';
    $config = CRM_Streetimport_Config::singleton();
    $updateRelParams = array(
      1 => array($config->getRecruitingOrganizationContactSubType('name'), 'String'),
      2 => array('supplier', 'String')
    );
    CRM_Core_DAO::executeQuery($updateRelQry, $updateRelParams);

    // STEP 3: remove contact sub type
    CRM_Core_DAO::executeQuery('DELETE FROM civicrm_contact_type WHERE name = %1',
      array(1 => array('supplier', 'String')));
    return TRUE;
  }

  /**
   * Method to update contact sub types for recruiting organizations with upgrade 1002
   */
  private function updateContactSubTypes() {
    $config = CRM_Streetimport_Config::singleton();
    $query = 'SELECT id, contact_sub_type FROM civicrm_contact WHERE contact_sub_type LIKE %1';
    $contacts = CRM_Core_DAO::executeQuery($query, array(1 => array('%supplier%', 'String')));
    while ($contacts->fetch()) {
      $contactSubTypes = explode(CRM_Core_DAO::VALUE_SEPARATOR, $contacts->contact_sub_type);
      foreach ($contactSubTypes as $key => $value) {
        if ($value == "supplier") {
          unset($contactSubTypes[$key]);
        }
      }
      $contactSubTypes[] = $config->getRecruitingOrganizationContactSubType('name');
      $update = 'UPDATE civicrm_contact SET contact_sub_type = %1 WHERE id = %2';
      $updateParams = array(
        1 => array(CRM_Core_DAO::VALUE_SEPARATOR . implode(CRM_Core_DAO::VALUE_SEPARATOR, $contactSubTypes)
          . CRM_Core_DAO::VALUE_SEPARATOR, 'String'),
        2 => array($contacts->id, 'Integer')
      );
      CRM_Core_DAO::executeQuery($update, $updateParams);
    }
  }
}
