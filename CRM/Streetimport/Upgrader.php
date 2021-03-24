<?php

/**
 * Collection of upgrade steps.
 */
class CRM_Streetimport_Upgrader extends CRM_Streetimport_Upgrader_Base {

  /**
   * Create custom data after install
   *
   * @throws
   */
  public function install() {
    if (!CRM_Streetimport_Utils::isFcdExtensionInstalled()) {
      throw new Exception(ts('This extensions has a dependency on the extension formercommunicationdata, which is not installed. Please first install the formercommunicationdata extension and then try installing this extension again'));
    }
  }

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
   * Upgrade 1003 - check task 199097: https://civicoop.plan.io/issues/1990
   * - add activity type for fraud warning
   */
  public function upgrade_1003() {
    $this->ctx->log->info('Applying update 1003 (add fraud warning)');
    // add activity type warning fraude if not exists yet
    CRM_Streetimport_Config::singleton()->setActivityTypes();
    return TRUE;
  }

  /**
   * Upgrade 1005 - check task 4195: https://civicoop.plan.io/issues/4195
   * - check if extension former communication data is installed
   *
   * @throws
   */
  public function upgrade_1005() {
    $this->ctx->log->info('Applying update 1005');
    if (!CRM_Streetimport_Utils::isFcdExtensionInstalled()) {
      throw new Exception(ts('This extensions has a dependency on the extension formercommunicationdata, which is not installed. Please first install the formercommunicationdata extension and then try upgrading this extension again'));
    }
    return TRUE;
  }

  /**
   * Upgrade 1010 - check if there are any recruiters with email addresses that belong to other contacts
   * and if so, delete the email
   *
   * @link https://issues.civicoop.org/issues/7882
   * @return bool
   */
  public function upgrade_1010() {
    $this->ctx->log->info('Applying update 1010');
    $this->fixWerverEmails();
    return TRUE;
  }

  /**
   * check if there are any recruiters with email addresses that belong to other contacts
   * and if so, delete the email
   *
   * @link https://issues.civicoop.org/issues/7882
   */
  private function fixWerverEmails() {
    // find all recruiters that have an email address
    $query = "SELECT a.id, a.contact_id, a.email
        FROM civicrm_email AS a JOIN civicrm_contact AS b on a.contact_id = b.id
        WHERE b.contact_sub_type = %1";
    $dao = CRM_Core_DAO::executeQuery($query, [1 => ["recruiter", "String"]]);
    while ($dao->fetch()) {
      // count how many times the same email address occurs on other contacts
      $countQry = "SELECT COUNT(*) FROM civicrm_email WHERE email = %1 AND contact_id <> %2";
      $count = CRM_Core_DAO::singleValueQuery($countQry, [
        1 => [$dao->email, "String"],
        2 => [$dao->contact_id, "Integer"],
      ]);
      // if email occurs on other contacts, delete from recruiter
      if ($count > 0) {
        $delete = "DELETE FROM civicrm_email WHERE id = %1";
        CRM_Core_DAO::executeQuery($delete, [1 => [$dao->id, "Integer"]]);
      }
   }
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
