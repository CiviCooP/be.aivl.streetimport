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
    $config = CRM_Streetimport_Config::singleton();
    if (empty($record['Recruiting organization ID'])) {
      $this->logger->abort($config->translate("Recruiting organization ID not given"));
      return NULL;
    }
    $recruiting_organisation = $this->getContact((int) $record['Recruiting organization ID'], true);
    if ($recruiting_organisation==NULL) {
      $this->logger->abort($config->translate("Recruiting organization")." ".$record['Recruiting organization ID']." ".$config->translate("not found"), true);
      return NULL;
    }
    $this->logger->logDebug($config->translate("Recruiting organization identified as contact")." ".$recruiting_organisation['id']);
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
   * @param array $record
   * @param array $recruiting_organisation
   * @return mixed $recruiter
   */
  protected function processRecruiter($record, $recruiting_organisation) {
    $config = CRM_Streetimport_Config::singleton();
    $recruiter = NULL;
    if (!empty($record['Recruiter ID'])) {
      $recruiter = $this->getContact($record['Recruiter ID']);
    }

    if ($recruiter==NULL) {
      $this->logger->logDebug($config->translate("Recruiter not found, creting new one..."));
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
        $this->logger->logDebug($config->translate("Recruiter")." ".$recruiter['id']." ".$config->translate("already created."));
        return $recruiter;
      }

      // else, we have to create the recruiter...
      $recruiter = $this->createContact($recruiter_data, true);
      if (!$recruiter) {
        $this->logger->abort($config->translate("Recruiter could not be created"));
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
      $this->logger->logDebug($config->translate("Recruiter")." ".$recruiter['id']." ".$config->translate("created"));
    } else {
      $this->logger->logDebug($config->translate("Recruiter")." ".$record['Recruiter ID']." ".$config->translate("found"));
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
      $this->logger->abort($config->translate("Cannot create new donor. Import failed."));
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
    $config = CRM_Streetimport_Config::singleton();
    if (empty($contactId)) {
      $this->logger->logError($config->translate("Cannot set Donor ID").', '.$config->translate("contactId missing"));
    } elseif (empty($donorId)) {
      $this->logger->logError($config->translate("Cannot set Donor ID").', '.$config->translate("donorId missing"));
    } elseif (empty($recruitingOrganizationId)) {
      $this->logger->logError($config->translate("Cannot set Donor ID").', '.$config->translate("recruitingOrganizationId missing"));
    } else {
      $tableName = $config->getExternalDonorIdCustomGroup('table_name');
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
    $config = CRM_Streetimport_Config::singleton();
    $tableName = $config->getExternalDonorIdCustomGroup('table_name');
    $donorCustomField = $config->getExternalDonorIdCustomFields('external_donor_id');
    $orgCustomField = $config->getExternalDonorIdCustomFields('recruiting_organization_id');
    if (empty($donorCustomField)) {
      $this->logger->logError($config->translate("CustomField external_donor_id not found. Please reinstall."));
      return NULL;
    }
    if (empty($orgCustomField)) {
      $this->logger->logError($config->translate("CustomField recruiting_organization_id not found. Please reinstall."));
      return NULL;
    }
    $query = 'SELECT entity_id FROM '.$tableName.' WHERE '.$donorCustomField['column_name'].' = %1 AND '.$orgCustomField['column_name'].' = %2';
    $params = array(
      1 => array($donorId, 'Positive'),
      2 => array($recruitingOrganizationId, 'Positive'));

    $dao = CRM_Core_DAO::executeQuery($query, $params);
    if ($dao->N > 1) {
      $this->logger->logError($config->translate('More than one contact found for donor ID').': '.$donorId);
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
      $this->logger->logWarning($config->translate("No SDD specified, no mandate created."));
      return NULL;
    }


    // extract the mandate type from the 'Frequency Unit' field
    $mandate_data = $config->extractSDDtype($frequency_unit);
    if (!$mandate_data) {
      $this->logger->logError($config->translate("Bad mandate specification").": " . CRM_Utils_Array::value('Frequency Unit', $record));
      return NULL;
    }

    // multiply the frequency_interval, if a value > 1 is given
    $frequency_interval = (int) CRM_Utils_Array::value('Frequency Unit', $record);
    if ($frequency_interval > 1) {
      $mandate_data['frequency_interval'] = $mandate_data['frequency_interval'] * $frequency_interval;
    }

    // check if IBAN is given
    $iban = CRM_Utils_Array::value('IBAN', $record);
    if (empty($iban)) {
      $this->logger->logError("Record has no IBAN.");
      return;
    }

    // look up BIC if it doesn't exist   // BE62510007547061
    $mandate_data['bank_name'] = CRM_Utils_Array::value('Bank Name', $record);
    $bic  = CRM_Utils_Array::value('Bic',  $record);
    if (empty($bic)) {
      try {
        $result = civicrm_api3('Bic', 'getfromiban', array('iban' => $iban));
        $bic = $result['bic'];
        if (empty($mandate_data['bank_name'])) {
          // set bank name, if not given by file
          $mandate_data['bank_name'] = $bic['title'];
        }
        $this->logger->logMessage("Successfully looked up BIC '$bic' with IBAN '$iban'.");
      } catch (CiviCRM_API3_Exception $ex) {
        $this->logger->logError("Record has no BIC, and a lookup with IBAN '$iban' failed.");
        return;
      }
    }

    // get the start date
    $start_date = CRM_Utils_Array::value('Start Date', $record);
    $start_date_parsed = strtotime($start_date);
    $now = strtotime("now");
    if (empty($start_date_parsed)) {
      if (!empty($start_date)) {
        $this->logger->logWarning("Couldn't parse start date '$start_date'. Set to start now.");
      }
      $start_date_parsed = $now;
    } elseif ($start_date_parsed < $now) {
      $this->logger->logWarning("Given start date is in the past. Set to start now.");
      $start_date_parsed = $now;
    }

    // get the signature date
    $signature_date = CRM_Utils_Array::value("Recruitment Date (format jjjj-mm-dd)", $record);
    $signature_date_parsed = strtotime($signature_date);
    if (empty($signature_date_parsed)) {
      if (!empty($signature_date)) {
        $this->logger->logWarning("Couldn't parse signature date '$signature_date'. Set to start now.");
      }
      $signature_date_parsed = $now;
    }
    $signature_date_parsed = max($now, $signature_date_parsed);

    // get the start date
    $end_date = CRM_Utils_Array::value('End Date', $record);
    $end_date_parsed = strtotime($end_date);
    if (empty($end_date_parsed)) {
      if (!empty($end_date)) {
        $this->logger->logWarning("Couldn't parse start end date '$end_date'.");
      }
    } else {
      $end_date_parsed = max($start_date_parsed, $end_date_parsed);
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
    $mandate_data['iban']          = $iban;
    $mandate_data['bic']           = $bic;
    $mandate_data['source']        = $config->translate('Street Recruitment');
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
        $config = CRM_Streetimport_Config::singleton();
        $this->logger->logError($config->translate("Campaign with id").' '.$mandate_data['campaign_id'].' '.$config->translate("could not be uniquely identified"));
        unset($mandate_data['campaign_id']);
      }
    }

    // TODO: more sanity checks?

    try {
      $result = civicrm_api3('SepaMandate', 'createfull', $mandate_data);
      $mandate = $result['values'][$result['id']];
      $config = CRM_Streetimport_Config::singleton();
      $this->logger->logDebug($config->translate("SDD mandate")." ".$mandate['id']." ".$config->translate("created, reference is")." ".$mandate['reference']);
      return $mandate;
    } catch (CiviCRM_API3_Exception $ex) {
      $config = CRM_Streetimport_Config::singleton();
      $this->logger->logError($config->translate("Error while trying to create mandate. Error was").": " . $ex->getMessage(), $config->translate("Create SDD Mandate Error"));
      return NULL;
    }
  }



  /**
   * This function will make sure, that the donor will
   * have a (CiviBanking) bank account entry
   *
   * @param $mandate_data   mandate entity data
   */
  public function saveBankAccount($mandate_data) {
    $config = CRM_Streetimport_Config::singleton();
    $type_id_IBAN = (int) CRM_Core_OptionGroup::getValue('civicrm_banking.reference_types', 'IBAN', 'name', 'String', 'id'); 
    if (empty($type_id_IBAN)) {
      $this->logger->abort("Could't find 'IBAN' reference type. Maybe CiviBanking is not installed?");
      return;
    }

    $account_exists = FALSE;
    try {
      // check the user's bank accounts
      $ba_list = civicrm_api3('BankingAccount', 'get', array('contact_id' => $mandate_data['contact_id']));
      foreach ($ba_list['values'] as $ba_id => $ba) {
        $ref_query = array('ba_id' => $ba['id'], 'reference_type_id' => $type_id_IBAN);
        $ba_ref_list = civicrm_api3('BankingAccountReference', 'get', $ref_query);
        foreach ($ba_ref_list['values'] as $ba_ref_id => $ba_ref) {
          if ($ba_ref['reference'] == $mandate_data['iban']) {
            $account_exists = TRUE;
            break 2;
          }
        }
      }

      if ($account_exists) {
        $this->logger->logDebug("Bank account '{$mandate_data['iban']}' already exists with contact [{$mandate_data['contact_id']}].");
      } else {
        // create bank account (using BAOs)
        $ba_extra = array(
          'BIC'     => $mandate_data['bic'],
          'country' => substr($mandate_data['iban'], 0, 2),
          'source'  => $config->translate('Street Recruitment'),
        );
        if (!empty($mandate_data['bank_name'])) {
          $ba_extra['bank_name'] = $mandate_data['bank_name'];
        }

        $ba = civicrm_api3('BankingAccount', 'create', array(
          'contact_id'   => $mandate_data['contact_id'],
          'description'  => $config->translate('Private Account'),
          'created_date' => date('YmdHis'),
          'data_raw'     => '{}',
          'data_parsed'  => json_encode($ba_extra),
          ));

        // add a reference
        civicrm_api3('BankingAccountReference', 'create', array(
          'reference'         => $mandate_data['iban'],
          'reference_type_id' => $type_id_IBAN,
          'ba_id'             => $ba['id'],
          ));

        $this->logger->logDebug("Bank account '{$mandate_data['iban']}' created for contact [{$mandate_data['contact_id']}].");
      }
    } catch (Exception $ex) {
      
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