<?php
/**
 * Abstract class bundle common street import functions
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
abstract class CRM_Streetimport_StreetimportRecordHandler extends CRM_Streetimport_RecordHandler {

  /** 
   * in case the recruiter ID is not set,
   * we will create a new recruiter, but store it so we can use the same one
   * 
   * keys for the array are "$last_name//$first_name//$prefix"
   */
  protected $created_recruiters = array();


  protected function getConfigValue($key) {
    $returnValue = 'LOOKUP-ERROR';
    $extensionConfig = CRM_Streetimport_Config::singleton();
    switch ($key) {
      case 'Recruiter':
        $returnValue = $extensionConfig->getRecruiterContactSubType();
        break;
    }
    return $returnValue;
  }


  /** 
   * look up the recruiting organisation
   *
   * From the process description:
   * "For first record of file: check if recruiting organization exists.
   * If it does not, create activity of type ‘Foutieve data import’ with 
   * relevant error message in subject and details. No further processing of file possible."
   */
  protected function getRecruitingOrganisation($record) {
    if (empty($record['Recruiting organization ID'])) {
      $this->logger->abort("Recruiting organization ID not given.");
      return NULL;
    }
    $recruiting_organisation = $this->getContact((int) $record['Recruiting organization ID'], true);
    if ($recruiting_organisation==NULL) {
      $this->logger->abort("Recruiting organization [{$record['Recruiting organization ID']}] not found.", true);
      return NULL;
    }
    $this->logger->logDebug("Recruiting organization identified as contact [{$recruiting_organisation['id']}]");
    return $recruiting_organisation;
  }

  /**
   * this method will lookup or create the recruiter
   *
   * From the process description:
   * "check if recruiter exists. If not, create recruiter (see CSV description) 
   * and create activity of type ‘Foutieve data import’ 
   * with relevant error message in subject and details"
   *
   * @return $recruiter array with contact entity
   */
  protected function processRecruiter($record, $recruiting_organisation) {
    $config = CRM_Streetimport_Config::singleton();
    $recruiter = NULL;
    if (!empty($record['Recruiter ID'])) {
      $recruiter = $this->getContact($record['Recruiter ID']);
    }

    if ($recruiter==NULL) {
      $this->logger->logDebug("Recruiter not found, creting new one...");      
      // "If the contact is not known, a contact of the contact subtype 'Werver' is to be created"
      $recruiter_data = array(
        'contact_type'     => 'Individual',
        'contact_sub_type' => $this->getConfigValue('Recruiter'),
        'first_name'       => CRM_Utils_Array::value('Recruiter First Name', $record),
        'last_name'        => CRM_Utils_Array::value('Recruiter Last Name',  $record),
        'prefix'           => CRM_Utils_Array::value('Recruiter Prefix', $record),
      );
      $recruiter_known = TRUE;
      
      // "If the first name and last name are empty, the values 'Unknown Werver' 
      //  "and 'Organization name of recruiting org' will be used as first and last name."
      if (empty($record['Recruiter First Name']) && empty($record['Recruiter Last Name'])) {
        $recruiter_data['first_name'] = $this->getConfigValue('Unknown Recruiter');
        $recruiter_data['last_name']  = CRM_Utils_Array::value('organization_name', $recruiting_organisation);
        $recruiter_data['prefix']     = '';
        $recruiter_known = FALSE;
      }

      // check if we had already created the recruiter
      $recruiter_key = "{$recruiter_data['last_name']}//{$recruiter_data['first_name']}//{$recruiter_data['prefix']}";
      if (!empty($this->created_recruiters[$recruiter_key])) {
        // ...and indeed we have
        $recruiter = $this->created_recruiters[$recruiter_key];
        $this->logger->logDebug("Recruiter [{$recruiter['id']}] already created.");
        return $recruiter;
      }

      // else, we have to create the recruiter...
      $recruiter = $this->createContact($recruiter_data, true);
      if (!$recruiter) {
        $this->logger->abort("Recruiter could not be created.");
      }

      // ..."with a relationship 'Werver' to the recruiting organization."
      $relationshipData = array(
        'contact_id_a' => $recruiting_organisation['id'],
        'contact_id_b' => $recruiter['id'],
        'relationship_type_id' => $config->getRecruiterRelationshipType()
      );
      $this->createRelationship($relationshipData);

      // "In all cases where the contact is not known, an activity of the type 'Incompleet werver contact' 
      //     will be generated  and assigned to the admin ID entered as a param"
      if (!$recruiter_known) {
        $this->createActivity(array(
                              'activity_type_id'   => $config->getImportErrorActivityType(),
                              'subject'            => $config->translate("Incomplete Recruiter Contact"),
                              'status_id'          => $config->getImportErrorActivityStatusId(),
                              'activity_date_time' => date('YmdHis'),
                              'target_contact_id'  => (int) $recruiter['id'],
                              'source_contact_id'  => (int) $recruiter['id'],
                              'assignee_contact_id'=> $config->getAdminContactID(),
                              'details'            => $this->renderTemplate('activities/IncompleteRecruiterContact.tpl', $record),
                              ));        
      }

      // finally, store the result so we don't create the same recruiter over and over
      $this->created_recruiters[$recruiter_key] = $recruiter;
      $this->logger->logDebug("Recruiter [{$recruiter['id']}] created.");
    } else {
      $this->logger->logDebug("Recruiter [{$record['Recruiter ID']}] found.");
    }

    return $recruiter;
  }

  /**
   * will create/lookup the donor along with all relevant information
   *
   * @param $record
   * @return array with entity data
   */
  protected function processDonor($record, $recruiting_organisation) {
    $config = CRM_Streetimport_Config::singleton();
    $donor = $this->getDonorWithExternalId($record['DonorID']);
    if ($donor) {
      // TODO: update existing donor with latest contact information?
      return $donor;
    }

    // create base contact
    $householdPrefixes = $config->getHouseholdPrefixIds();
    $contact_data = array();
    if ($this->isTrue($record, 'Organization Yes/No')) {
      $contact_data['contact_type']      = 'Organization';
      $contact_data['organization_name'] = CRM_Utils_Array::value('Last Name',  $record);
    } elseif (in_array($record['Donor Prefix'], $householdPrefixes)) {
      $contact_data['contact_type']      = 'Household';
      $contact_data['household_name']    = CRM_Utils_Array::value('Last Name',  $record);
    } else {
      $contact_data['contact_type']      = 'Individual';
      $contact_data['first_name']        = CRM_Utils_Array::value('First Name', $record);
      $contact_data['last_name']         = CRM_Utils_Array::value('Last Name',  $record);
      $contact_data['prefix']            = CRM_Utils_Array::value('Prefix',     $record);
      $contact_data['birth_date']        = CRM_Utils_Array::value('Birth date (format jjjj-mm-dd)', $record);
    }
    $donor = $this->createContact($contact_data, true);
    if (empty($donor)) {
      $this->logger->abort("Cannot create new donor. Import failed.");
    }
    $this->setDonorID($donor['id'], $record['DonorID'], $recruiting_organisation['id']);

    // create address
    if (isset($record['Country']) && !empty($record['Country'])) {
      $countryId = CRM_Streetimport_Utils::getCountryByIso($record['Country']);
    } else {
      $countryId = $config->getDefaultCountryId();
    }
    $this->createAddress(array(
        'contact_id'       => $donor['id'],
        'location_type_id' => $config->getLocationTypeId(),
        'street_name'      => CRM_Utils_Array::value('Street Name',         $record),
        'street_number'    => (int) CRM_Utils_Array::value('Street Number', $record),
        'street_unit'      => CRM_Utils_Array::value('Street Unit',         $record),
        'postal_code'      => CRM_Utils_Array::value('Postal code',         $record),
        'street_address'   => trim(CRM_Utils_Array::value('Street Name',    $record) . ' ' . CRM_Utils_Array::value('Street Number', $record) . ' ' . CRM_Utils_Array::value('Street Unit',   $record)),
        'city'             => CRM_Utils_Array::value('City',                $record),
        'country_id'       => $countryId
      ));

    // create phones
    $this->createPhone(array(
        'contact_id'       => $donor['id'],
        'phone_type_id'    => $config->getPhonePhoneTypeId(),
        'location_type_id' => $config->getLocationTypeId(),
        'phone'            => CRM_Utils_Array::value('Telephone1', $record),
      ));
    $this->createPhone(array(
        'contact_id'       => $donor['id'],
        'phone_type_id'    => $config->getPhonePhoneTypeId(),
        'location_type_id' => $config->getOtherLocationTypeId(),
        'phone'            => CRM_Utils_Array::value('Telephone2', $record),
      ));
    $this->createPhone(array(
        'contact_id'       => $donor['id'],
        'phone_type_id'    => $config->getMobilePhoneTypeId(),
        'location_type_id' => $config->getLocationTypeId(),
        'phone'            => CRM_Utils_Array::value('Mobile1', $record),
      ));
    $this->createPhone(array(
        'contact_id'       => $donor['id'],
        'phone_type_id'    => $config->getMobilePhoneTypeId(),
        'location_type_id' => $config->getOtherLocationTypeId(),
        'phone'            => CRM_Utils_Array::value('Mobile2', $record),
      ));

    // create email
    $this->createEmail(array(
        'contact_id'       => $donor['id'],
        'location_type_id' => $config->getLocationTypeId(),
        'email'            => CRM_Utils_Array::value('Email', $record),
      ));
    
    return $donor;
  }
  /**
   * Manages the contact_id <-> donor_id (external) mapping
   *
   * @param int $contactId
   * @param int $donorId
   * @param int $recruitingOrganizationId
   */
  protected function setDonorID($contactId, $donorId, $recruitingOrganizationId) {
    if (empty($contactId)) {
      $this->logger->logError("Cannot set Donor ID, 'contactId' missing.");
    } elseif (empty($donorId)) {
      $this->logger->logError("Cannot set Donor ID, 'donorId' missing.");
    } elseif (empty($recruitingOrganizationId)) {
      $this->logger->logError("Cannot set Donor ID, 'recruitingOrganizationId' missing.");
    } else {
      $extensionConfig = CRM_Streetimport_Config::singleton();
      $tableName = $extensionConfig->getExternalDonorIdCustomGroup('table_name');
      $query = 'REPLACE INTO '.$tableName.' SET recruiting_organization_id = %1,
        external_donor_id = %2, entity_id = %3';
      $params = array(
        1 => array($recruitingOrganizationId, 'Positive'),
        2 => array($donorId,                  'String'),
        3 => array($contactId,                'Positive')
      );
      CRM_Core_DAO::executeQuery($query, $params);      
    }
  }

  /**
   * Manages the contact_id <-> donor_id (external) mapping
   * 
   * @return mixed contact_id or NULL if not found
   */
  protected function getContactForDonorID($donorId) {
    $extensionConfig = CRM_Streetimport_Config::singleton();
    $tableName = $extensionConfig->getExternalDonorIdCustomGroup('table_name');
    $customField = $extensionConfig->getExternalDonorIdCustomFields('external_donor_id');
    if (empty($customField)) {
      $this->logger->logError("CustomField 'external_donor_id' not found. Please reinstall.");
      return NULL;
    }    
    $query = 'SELECT entity_id FROM '.$tableName.' WHERE '.$customField['column_name'].' = %1';
    $params = array(1 => array($donorId, 'Positive'));
    $dao = CRM_Core_DAO::executeQuery($query, $params);
    if ($dao->N > 1) {
      $this->logger->logError('More than one contact found for donor ID '.$donorId);
    } else {
      if ($dao->fetch) {
        return $dao->entity_id;
      }
    }
    return NULL;
  }

  /**
   * Create CiviSEPA mandate
   */
  protected function createSDDMandate($mandate_data) {
    // TODO: sanity checks?

    try {
      $result = civicrm_api3('SepaMandate', 'createfull', $mandate_data);
      $mandate = $result['values'][$result['id']];
      $this->logger->logDebug("SDD mandate [{$mandate['id']}] created, reference is '{$mandate['reference']}'");
      return $mandate;
    } catch (CiviCRM_API3_Exception $ex) {
      $this->logger->logError("Error while trying to create mandate. Error was: " . $ex->getMessage(), "Create SDD Mandate Error");
      return NULL;
    }
  }

  /**
   * Method to get contact data with donor Id
   *
   * @param int $donorId
   * @return array
   * @access public
   */
  public function getDonorWithExternalId($donorId) {
    if (empty($donorId)) {
      return array();
    }
    $contactId = $this->getContactForDonorID($donorId);
    if (empty($contactId)) {
      return array();
    }
    return $this->getContact($contactId);

  }
  public function getRecruitmentType($sourceRecruitmentType) {
    // TODO not in sample from Ilja, discuss (ErikH)
    return '';
  }

  /**
   * Method to set the areas of interest
   *
   * @param $sourceAreasInterest
   * @return null|string
   * @access public
   */
  public function getAreasOfInterest($sourceAreasInterest) {
    $areasOfInterest = null;
    if (!empty($sourceAreasInterest)) {
      $config = CRM_Streetimport_Config::singleton();
      $tempAreas = array();
      $optionGroupId = $config->getAreasOfIntereestOptionGroup();
      $parts = explode('/', $sourceAreasInterest);
      foreach ($parts as $part) {
        $params = array(
          'option_group_id' => $optionGroupId,
          'label' => trim($part),
          'return' => 'value');
        try {
          $tempAreas[] = civicrm_api3('OptionValue', 'Getvalue', $params);
        } catch (CiviCRM_API3_Exception $ex) {}
      }
    }
    if (!empty($tempAreas)) {
      $areasOfInterest = CRM_Core_DAO::VALUE_SEPARATOR.implode(CRM_Core_DAO::VALUE_SEPARATOR, $tempAreas).CRM_Core_DAO::VALUE_SEPARATOR;
    }
    return $areasOfInterest;
  }

  /**
   * Method to retrieve the frequency unit value with a label
   *
   * @param $sourceFrequencyUnit
   * @return array|null
   */
  public function getFrequencyUnit($sourceFrequencyUnit) {
    $config = CRM_Streetimport_Config::singleton();
    $optionGroupId = $config->getFrequencyUnitOptionGroup();
    $params = array(
      'option_group_id' => $optionGroupId,
      'label' => strtolower($sourceFrequencyUnit),
      'return' => 'value');
    try {
      $frequencyUnit = civicrm_api3('OptionValue', 'Getvalue', $params);
    } catch (CiviCRM_API3_Exception $ex) {
      $frequencyUnit = null;
    }
    return $frequencyUnit;
  }
}