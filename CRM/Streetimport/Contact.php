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
   * @return array|string with contact data or $ex->getMessage() when CiviCRM API Exception
   */
  public function createFromImportData($contactData) {
    if (isset($contactData['birth_date'])) {
      $contactData['birth_date'] = $this->formatBirthDate($contactData['birth_date']);
    }
    // create via API
    try {
      $result  = civicrm_api3('Contact', 'create', $contactData);
      $contact = $result['values'][$result['id']];
      return $contact;
    } catch (CiviCRM_API3_Exception $ex) {
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
    $config = CRM_Streetimport_Config::singleton();
    $organizationData = $this->getOrganizationDataFromImportData($importNotes);
    if (!empty($organizationData)) {
      // create organization as soon as we have an organization name
      if (isset($organizationData['organization_name']) && !empty($organizationData['organization_name'])) {
        try {
          $result = civicrm_api3('Contact', 'create', array(
            'contact_type' => 'Organization',
            'organization_name' => $organizationData['organization_name']
          ));
          $organization = $result['values'][$result['id']];
          // add phone if in organization data
          if (isset($organizationData['organization_phone']) && !empty($organizationData['organization_phone'])) {
            try {
              civicrm_api3('Phone', 'create', array(
              'contact_id'       => $organization['id'],
              'phone_type_id'    => $config->getDefaultPhoneTypeId(),
              'location_type_id' => $config->getDefaultLocationTypeId(),
              'phone'            => $organizationData['organization_phone']));
            } catch (CiviCRM_API3_Exception $ex) {}
          }
          // if job title in organization data, update individual with job_title and current employer
          if (isset($organizationData['job_title']) && !empty($organizationData['job_title'])) {
            try {
              civicrm_api3('Contact', 'create', array(
                'id' => $individualId,
                'job_title' => $organizationData['job_title'],
                'employer_id' => $organization['id']
              ));
            } catch (CiviCRM_API3_Exception $ex) {}
          }
          return $organization;
        } catch (CiviCRM_API3_Exception $ex) {
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
    $config = CRM_Streetimport_Config::singleton();
    // validate contact type
    if (!isset($contactData['contact_type']) || empty($contactData['contact_type'])) {
      return $config->translate("Contact missing contact_type");
    }
    // validate household name for household
    if ($contactData['contact_type'] == 'Household') {
      if (empty($contactData['household_name'])) {
        return $config->translate("Contact missing household_name");
      }
    }
    // validate first and last name for individual
    if ($contactData['contact_type'] == 'Individual') {
      if (empty($contactData['first_name']) && empty($contactData['last_name'])) {
        return $config->translate("Contact missing first_name and last_name");
      }
      if (!isset($contactData['first_name']) && !isset($contactData['last_name'])) {
        return $config->translate("Contact missing first_name and last_name");
      }
      if (!isset($contactData['first_name']) || empty($contactData['first_name'])) {
        return $config->translate("Donor missing first_name, contact created without first name");
      }
      if (!isset($contactData['last_name']) || empty($contactData['last_name'])) {
        return $config->translate("Donor missing last_name, contact created without first name");
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
    $correctDate = new DateTime(CRM_Streetimport_Utils::formatCsvDate($birthDate));
    return $correctDate->format('d-m-Y');
  }

  /**
   * Method to get the organization data from the import data (notes column)
   * https://civicoop.plan.io/issues/677
   *
   * @param string $importNote
   * @return array
   */
  private function getOrganizationDataFromImportData($importNote) {
    $result = array();
    $orgParts = explode('/', $importNote);
    if (!empty($orgParts)) {
      // split first element on ':', first part should be companyName and second contain name data
      $nameParts = explode(':', trim($orgParts[0]));
      if (trim($nameParts[0]) == 'companyName' && isset($nameParts[1]) && !empty($nameParts[1])) {
        $result['organization_name'] = trim($nameParts[1]);
      }
      // split second element on ':', second part should be companyNumber and second contact phone data
      if (isset($orgParts[1])) {
        $phoneParts = explode(':', trim($orgParts[1]));
        if (trim($phoneParts[0]) == 'companyNumber' && isset($phoneParts[1]) && !empty($phoneParts[1])) {
          $result['organization_phone'] = trim($phoneParts[1]);
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
}