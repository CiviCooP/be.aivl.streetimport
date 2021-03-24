<?php
/**
 * Class handling the contact processing for streetimport
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 27 Feb 2017
 * @license AGPL-3.0
 */
class CRM_Streetimport_Contact {

  /**
   * CRM_Streetimport_Contact constructor.
   */
  function __construct() {
  }

  /**
   * Method to create contact from import data
   *
   * @param $contactData
   * @param $importRecord
   * @return array|string with contact data or $ex->getMessage() when CiviCRM API Exception
   */
  public function createFromImportData($contactData, $importRecord) {
    if (isset($contactData['birth_date'])) {
      $contactData['birth_date'] = $this->formatBirthDate($contactData['birth_date']);
    }
    // create via API (issue 2823 use xcm if present
    try {
      if (CRM_Streetimport_Utils::isXcmInstalled()) {
        $findParams = $contactData;
        // email alleen gebruiken als het geen fake email adres is en als we geen recruiter aan het maken zijn
        $recruiterSubType = CRM_Streetimport_Config::singleton()->getRecruiterContactSubType();
        if (!isset($contactData['contact_sub_type']) || $contactData['contact_sub_type'] != $recruiterSubType) {
          if (!CRM_Streetimport_Utils::isFakeEmailAddress($importRecord['Email'])) {
            if (isset($importRecord['Email']) && !empty($importRecord['Email'])) {
              $findParams['email'] = $importRecord['Email'];
            }
          }
        }
        $result = civicrm_api3('Contact', 'getorcreate', $findParams);
        $contact = civicrm_api3('Contact', 'getsingle', ['id' => $result['id']]);
      }
      else {
        $result  = civicrm_api3('Contact', 'create', $contactData);
        $contact = $result['values'][$result['id']];
      }
      return $contact;
    }
    catch (CiviCRM_API3_Exception $ex) {
      return $ex->getMessage();
    }
  }

  /**
   * Method to create an organization from the contact notes if required
   *
   * @param int $individualId
   * @param string $importNotes
   * @return string $ex->getMessage()
   */
  public function createOrganizationFromImportData($individualId, $importNotes) {
    $notes = new CRM_Streetimport_Notes();
    $organizationData = $notes->getOrganizationDataFromImportData($importNotes);
    if (!empty($organizationData)) {
      // create organization as soon as we have an organization name
      if (isset($organizationData['organization_name']) && !empty($organizationData['organization_name'])) {
        try {
          $organizationParams = [
            'contact_type' => 'Organization',
            'organization_name' => $organizationData['organization_name'],
          ];
          // add organization number if in data
          if (isset($organizationData['organization_number']) && !empty($organizationData['organization_number'])) {
            $organizationNumber = CRM_Streetimport_Utils::formatOrganisationNumber($organizationData['organization_number']);
            $customField = CRM_Streetimport_Config::singleton()->getAivlOrganizationDataCustomFields('aivl_organization_id');
            $organizationParams['custom_' . $customField['id']] = $organizationNumber;
          }
          $result = civicrm_api3('Contact', 'create', $organizationParams);
          $organization = $result['values'][$result['id']];
          $employerParams = [
            'id' => $individualId,
            'contact_type' => 'Individual',
            'employer_id' => $organization['id']
          ];
          if (isset($organizationData['job_title']) && !empty($organizationData['job_title'])) {
            $employerParams['job_title'] = $organizationData['job_title'];
          }
          try {
            civicrm_api3('Contact', 'create', $employerParams);
          }
          catch (CiviCRM_API3_Exception $ex) {}
          return $organization;
        }
        catch (CiviCRM_API3_Exception $ex) {
          return $ex->getMessage();
        }
      }
    }
  }

  /**
   * Method to valid contact data
   *
   * @param array $contactData
   * @return string|bool
   */
  public function validateContactData($contactData) {
    // validate contact type
    if (!isset($contactData['contact_type']) || empty($contactData['contact_type'])) {
      return CRM_Streetimport_Config::singleton()->translate("Contact missing contact_type");
    }
    // validate household name for household
    if ($contactData['contact_type'] == 'Household') {
      if (empty($contactData['household_name'])) {
        return CRM_Streetimport_Config::singleton()->translate("Contact missing household_name");
      }
    }
    // validate first and last name for individual
    if ($contactData['contact_type'] == 'Individual') {
      if (empty($contactData['first_name']) && empty($contactData['last_name'])) {
        return CRM_Streetimport_Config::singleton()->translate("Contact missing first_name and last_name");
      }
      if (!isset($contactData['first_name']) && !isset($contactData['last_name'])) {
        return CRM_Streetimport_Config::singleton()->translate("Contact missing first_name and last_name");
      }
      if (!isset($contactData['first_name']) || empty($contactData['first_name'])) {
        return CRM_Streetimport_Config::singleton()->translate("Donor missing first_name, contact created without first name");
      }
      if (!isset($contactData['last_name']) || empty($contactData['last_name'])) {
        return CRM_Streetimport_Config::singleton()->translate("Donor missing last_name, contact created without first name");
      }
    }
    return TRUE;
  }

  /**
   * Method to correct the birth date when malformatted in csv
   * https://github.com/CiviCooP/be.aivl.streetimport/issues/39
   *
   * @param $birthDate
   * @return string
   */
  private function formatBirthDate($birthDate) {
    try {
      $correctDate = new DateTime(CRM_Streetimport_Utils::formatCsvDate($birthDate));
      return $correctDate->format('d-m-Y');
    }
    catch (Exception $ex) {
      Civi::log()->error(ts('Could not parse birth date into DateTime object in ') . __METHOD__);
      return "";
    }
  }

  /**
   * Method to get the organization data from the import data (notes column)
   * https://civicoop.plan.io/issues/677
   *
   * @param string $importNote
   * @return array
   */
  private function getOrganizationDataFromImportData($importNote) {
    $result = [];
    $orgParts = explode('/', $importNote);
    if (!empty($orgParts)) {
      // split first element on ':', first part should be companyName and second contain name data
      $nameParts = explode(':', trim($orgParts[0]));
      if (trim($nameParts[0]) == 'companyName' && isset($nameParts[1]) && !empty($nameParts[1])) {
        $result['organization_name'] = trim($nameParts[1]);
      }
      // split second element on ':', second part should be companyNumber going to custom field organization number
      if (isset($orgParts[1])) {
        $orgNumberParts = explode(':', trim($orgParts[1]));
        if (trim($orgNumberParts[0]) == 'companyNumber' && isset($orgNumberParts[1]) && !empty($orgNumberParts[1])) {
          $result['organization_number'] = trim($orgNumberParts[1]);
        }
      }
      // split third element on ':', first part should be companyFunction and second job title data
      if (isset($orgParts[2])) {
        $jobTitleParts = explode(':', trim($orgParts[2]));
        if (trim($jobTitleParts[0]) == 'companyFunction' && isset($jobTitleParts[1]) && !empty($jobTitleParts[1])) {
          $result['job_title'] = trim($jobTitleParts[1]);
        }
      }
    }
    return $result;
  }

  /**
   * Method to check if organization settings in the welcome call are consistent with the related streetimport:
   * - if welcome call is not on organization, street recruitment should als not be
   * - if welcome call is on organization, street recruitment should also be
   *
   * @param array $sourceData
   * @return array
   */
  public function checkOrganizationPersonConsistency($sourceData) {
    // no sense in checking if no mandate reference
    if (!isset($sourceData['Mandate Reference'])) {
      return ['valid' => TRUE];
    }
    $config = CRM_Streetimport_Config::singleton();
    // find street recruitment organization
    $query = 'SELECT s.new_org_mandate AS streetRecOrg
    FROM civicrm_activity AS a
    JOIN civicrm_activity_contact AS ac ON a.id = ac.activity_id AND ac.record_type_id = %1
    LEFT JOIN civicrm_value_street_recruitment AS s ON a.id =s.entity_id
    WHERE a.activity_type_id = %2 AND s.new_sdd_mandate = %3
    ORDER BY a.activity_date_time DESC LIMIT 1';
    $queryParams = [
      1 => [$config->getTargetRecordTypeId(), 'Integer'],
      2 => [$config->getStreetRecruitmentActivityType('value'), 'Integer'],
      3 => [trim($sourceData['Mandate Reference']), 'String'],
    ];
    $streetOrg = CRM_Core_DAO::singleValueQuery($query, $queryParams);
    $acceptedYesValues = $config->getAcceptedYesValues();
    switch ($streetOrg) {
      case 0:
        if (isset($sourceData['Organization Yes/No'])) {
          if (in_array($sourceData['Organization Yes/No'], $acceptedYesValues)) {
            return [
              'valid' => FALSE,
              'message' => 'Street Recruitment did not mention a company where Welcome Call now does! Please check and fix manually',
            ];
          }
        }
        break;

      case 1:
        if (isset($sourceData['Organization Yes/No'])) {
          $acceptedYesValues = $config->getAcceptedYesValues();
          if (!in_array($sourceData['Organization Yes/No'], $acceptedYesValues)) {
            return [
              'valid' => FALSE,
              'message' => 'Street Recruitment did mention a company where Welcome Call now does not! Please check and fix manually',
            ];
          }
        }
        break;
      default:
        return ['valid' => TRUE];
        break;
    }
    return ['valid' => TRUE];
  }
}
