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
    $donor = $this->getDonorWithExternalId($record['DonorID'], $recruiting_organisation['id']);
    if (!empty($donor)) {
      // TODO: update existing donor with latest contact information?
      return $donor;
    }

    // create base contact
    $householdPrefixes = $config->getHouseholdPrefixIds();
    $contact_data = array();
    if ($this->isTrue($record, 'Organization Yes/No')) {
      $contact_data['contact_type']      = 'Organization';
      $contact_data['organization_name'] = CRM_Utils_Array::value('Last Name',  $record);
    } elseif (in_array($record['Prefix'], $householdPrefixes)) {
      $contact_data['contact_type']      = 'Household';
      $contact_data['household_name']    = CRM_Utils_Array::value('Last Name',  $record);
    } else {
      $contact_data['contact_type']      = 'Individual';
      $contact_data['first_name']        = CRM_Utils_Array::value('First Name', $record);
      $contact_data['last_name']         = CRM_Utils_Array::value('Last Name',  $record);
      $contact_data['prefix']            = CRM_Utils_Array::value('Prefix',     $record);
      $contact_data['gender_id']         = CRM_Streetimport_Utils::determineGenderWithPrefix($record['Prefix']);
      $contact_data['birth_date']        = CRM_Utils_Array::value('Birth date (format jjjj-mm-dd)', $record);
    }
    $donor = $this->createContact($contact_data, true);
    if (empty($donor)) {
      $this->logger->abort("Cannot create new donor. Import failed.");
    }
    $this->setDonorID($donor['id'], $record['DonorID'], $recruiting_organisation['id']);

    // create address
    if (!empty($record['Country'])) {
      $country = CRM_Streetimport_Utils::getCountryByIso($record['Country']);
      if (empty($country)) {
        $countryId = $config->getDefaultCountryId();
      } else {
        $countryId = $country['country_id'];
      }
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

      $result = CRM_Core_DAO::executeQuery($query, $params);
    }
  }

  /**
   * Manages the contact_id <-> donor_id (external) mapping
   *
   * @param int $donorId
   * @param int $recruitingOrganizationId
   * 
   * @return mixed contact_id or NULL if not found
   */
  protected function getContactForDonorID($donorId, $recruitingOrganizationId) {
    $extensionConfig = CRM_Streetimport_Config::singleton();
    $tableName = $extensionConfig->getExternalDonorIdCustomGroup('table_name');
    $donorCustomField = $extensionConfig->getExternalDonorIdCustomFields('external_donor_id');
    $orgCustomField = $extensionConfig->getExternalDonorIdCustomFields('recruiting_organization_id');
    if (empty($donorCustomField)) {
      $this->logger->logError("CustomField 'external_donor_id' not found. Please reinstall.");
      return NULL;
    }
    if (empty($orgCustomField)) {
      $this->logger->logError("CustomField 'recruiting_organization_id' not found. Please reinstall.");
      return NULL;
    }
    $query = 'SELECT entity_id FROM '.$tableName.' WHERE '.$donorCustomField['column_name'].' = %1 AND '.$orgCustomField['column_name'].' = %2';
    $params = array(
      1 => array($donorId, 'Positive'),
      2 => array($recruitingOrganizationId, 'Positive'));

    $dao = CRM_Core_DAO::executeQuery($query, $params);
    if ($dao->N > 1) {
      $this->logger->logError('More than one contact found for donor ID '.$donorId);
    } else {
      if ($dao->fetch()) {
        return $dao->entity_id;
      }
    }
    return NULL;
  }

  /**
   * will extract the required information for a SEPA mandate 
   *
   * @return array with mandate data as provided by the record
   */
  protected function extractMandate($record, $donor_id) {
    $config = CRM_Streetimport_Config::singleton();

    // check values
    $frequency_unit = CRM_Utils_Array::value('Frequency Unit', $record);
    if (empty($frequency_unit)) {
      $this->logger->logWarning("No SDD specified, no mandate created.");
      return NULL;
    }


    // extract the mandate type from the 'Frequency Unit' field
    $mandate_data = $config->extractSDDtype($frequency_unit);
    if (!$mandate_data) {
      $this->logger->logError("Bad mandate specification: " . CRM_Utils_Array::value('Frequency Unit', $record));
      return NULL;
    }

    // multiply the frequency_interval, if a value > 1 is given
    $frequency_interval = (int) CRM_Utils_Array::value('Frequency Unit', $record);
    if ($frequency_interval > 1) {
      $mandate_data['frequency_interval'] = $mandate_data['frequency_interval'] * $frequency_interval;
    }

    // get the start date
    $start_date = CRM_Utils_Array::value('Start Date', $record);
    $start_date_parsed = strtotime($start_date);
    if (empty($start_date_parsed)) {
      if (!empty($start_date)) {
        $this->logger->logWarning("Couldn't parse start date '$start_date'. Set to start now.");
      }
      $start_date_parsed = strtotime("now");
    }

    // get the signature date
    $signature_date = CRM_Utils_Array::value("Recruitment Date (format jjjj-mm-dd)", $record);
    $signature_date_parsed = strtotime($signature_date);
    if (empty($signature_date_parsed)) {
      if (!empty($signature_date)) {
        $this->logger->logWarning("Couldn't parse signature date '$signature_date'. Set to start now.");
      }
      $signature_date_parsed = strtotime("now");
    }

    // get the start date
    $end_date = CRM_Utils_Array::value('End Date', $record);
    $end_date_parsed = strtotime($end_date);
    if (empty($end_date_parsed)) {
      if (!empty($end_date)) {
        $this->logger->logWarning("Couldn't parse start end date '$end_date'.");
      }
    } else {
      $mandate_data['end_date'] = date('YmdHis', $end_date_parsed);
    }

    // get campaign
    $campaign_id = (int) CRM_Utils_Array::value("Campaign ID", $record);
    if ($campaign_id) {
      $mandate_data['campaign_id'] = $campaign_id;
    }

    // fill the other required fields
    $mandate_data['contact_id']    = $donor_id;
    $mandate_data['reference']     = CRM_Utils_Array::value('Mandate Reference', $record);
    $mandate_data['amount']        = (float) CRM_Utils_Array::value('Amount', $record);
    $mandate_data['start_date']    = date('YmdHis', $start_date_parsed);
    $mandate_data['creation_date'] = date('YmdHis', $signature_date_parsed);
    $mandate_data['iban']          = CRM_Utils_Array::value('IBAN', $record);
    $mandate_data['bic']           = CRM_Utils_Array::value('Bic', $record);
    $mandate_data['bank_name']     = CRM_Utils_Array::value('Bank Name', $record);

    $mandate_data['financial_type_id']  = $config->getDefaultFinancialTypeId();

    // don't set $mandate_data['creditor_id'], use default creditor

    return $mandate_data;
  }


  /**
   * Create CiviSEPA mandate
   */
  protected function createSDDMandate($mandate_data) {
    // verify campaign_id
    if (!empty($mandate_data['campaign_id'])) {
      $mandate_data['campaign_id'] = (int) $mandate_data['campaign_id'];
      $result = civicrm_api3('Campaign', 'getcount', array('id' => $mandate_data['campaign_id']));
      if ($result != 1) {
        $this->logger->logError("Campaign with id '{$mandate_data['campaign_id']}' could not be uniquely identified.");
        unset($mandate_data['campaign_id']);
      }
    }

    // TODO: more sanity checks?

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
   * @param int $recruitingOrganizationId
   * @return array
   * @access public
   */
  public function getDonorWithExternalId($donorId, $recruitingOrganizationId) {
    if (empty($donorId)) {
      return array();
    }
    $contactId = $this->getContactForDonorID($donorId, $recruitingOrganizationId);
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