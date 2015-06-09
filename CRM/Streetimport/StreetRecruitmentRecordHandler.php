<?php
/**
 * This class can process records of type 'street recruitment'
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
class CRM_Streetimport_StreetRecruitmentRecordHandler extends CRM_Streetimport_StreetimportRecordHandler {

  /** 
   * Check if the given handler implementation can process the record
   *
   * @param $record  an array of key=>value pairs
   * @return true or false
   */
  public function canProcessRecord($record) {
    $config = CRM_Streetimport_Config::singleton();
    return isset($record['Loading type']) && $record['Loading type'] == $config->getStreetRecruitmentImportType();
  }
  
  /** 
   * process the given record
   *
   * @param $record  an array of key=>value pairs
   * @return true
   * @throws exception if failed
   */
  public function processRecord($record) {
    $config = CRM_Streetimport_Config::singleton();
    $this->logger->logDebug("Processing 'StreetRecruitment' record #{$record['__id']}...");

    // STEP 1: lookup recruiting organisation
    $recruiting_organisation = $this->getRecruitingOrganisation($record);

    // STEP 2: look up / create recruiter
    $recruiter = $this->processRecruiter($record, $recruiting_organisation);

    // STEP 3: look up / create donor
    $donor = $this->processDonor($record);

    // STEP 5: create activity "Straatwerving"
    $createdActivity = $this->createActivity(array(
                            'activity_type_id'   => $config->getStreetRecruitmentActivityType(),
                            'subject'            => $config->translate("Street Recruitment"),
                            'status_id'          => $config->getStreetRecruitmentActivityStatusId(),
                            'activity_date_time' => date('YmdHis'),
                            'target_contact_id'  => (int) $donor['id'],
                            'source_contact_id'  => $recruiter['id'],
                            //'assignee_contact_id'=> $recruiter['id'],
                            'details'            => $this->renderTemplate('activities/StreetRecruitment.tpl', $record),
                              ));
    // add custom data to the created activity
    $this->createActivityCustomData($createdActivity['id'], $config->getStreetRecruitmentCustomGroup('table_name'), $this->buildActivityCustomData($record));

    // STEP 6: create SEPA mandate
    $mandate = $this->processMandate($record, $donor['id']);

    // STEP 7: add to newsletter group if requested
    if ($this->isTrue($record, "Newsletter")) {
      $newsletter_group_id = $config->getNewsletterGroupID();
      $this->addContactToGroup($donor['id'], $newsletter_group_id);
    }
    
    // STEP 8: create membership if requested
    if ($this->isTrue($record, "Member")) {
      $this->createMembership(array(
        'contact_id'         => $donor['id'],
        'membership_type_id' => $config->getMembershipTypeID(),
        // ...
      ));
    }


    // STEP 8: create activity 'Opvolgingsgesprek' if requested
    if ($this->isTrue($record, "Follow Up Call")) {
      $this->createActivity(array(
                              'activity_type_id'   => $config->getFollowUpCallActivityType(),
                              'subject'            => $config->translate("Follow Up Call from ").$config->translate('Street Recruitment'),
                              'status_id'          => $config->getFollowUpCallActivityStatusId(),
                              'activity_date_time' => date('YmdHis', strtotime("+1 day")),
                              'target_contact_id'  => (int) $donor['id'],
                              'source_contact_id'  => $recruiter['id'],
                              'assignee_contact_id'=> $config->getFundraiserContactID(),
                              'details'            => $this->renderTemplate('activities/FollowUpCall.tpl', $record),
                              ));
    }

    // DONE
    $this->logger->logImport($record['__id'], true, 'StreetRecruitment');
  }

  /**
   * will create/lookup the donor along with all relevant information
   *
   * @param $record
   * @return array with entity data
   */
  protected function processDonor($record) {
    $config = CRM_Streetimport_Config::singleton();
    $donor = $this->getDonorWithExternalId($record['DonorID']);
    if ($donor) {
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
    $this->setDonorID($donor['id'], $record['DonorID'], $record['Recruiting Organization ID']);
    if (empty($donor)) {
      $this->logger->abort("Cannot create new donor. Import failed.");
    }

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
   * will extract the required information for a SEPA mandate 
   *  and create it accordingly
   *
   * @return array with entity data
   */
  protected function processMandate($record, $donor_id) {
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

    return $this->createSDDMandate($mandate_data);
  }

  /**
   * Method to build data for custom group street recruitment
   *
   * @param $record
   * @return array $customData
   * @access protected
   */
  protected function buildActivityCustomData($record) {
    $config = CRM_Streetimport_Config::singleton();
    $acceptedYesValues = $config->getAcceptedYesValues();
    $customData = array();
    $customData['new_date_import'] = date('YmdHis');
    $customData['new_recruit_location'] = $record['Recruitment Location'];
    $customData['new_recruit_type'] = $this->getRecruitmentType($record['Recruitment Type']);
    if (in_array($record['Follow Up Call'], $acceptedYesValues)) {
      $customData['new_follow_up_call'] = 1;
    } else {
      $customData['new_follow_up_call'] = 0;
    }
    if (in_array($record['Newsletter'], $acceptedYesValues)) {
      $customData['new_newsletter'] = 1;
    } else {
      $customData['new_newsletter'] = 0;
    }
    if (in_array($record['Member'], $acceptedYesValues)) {
      $customData['new_member'] = 1;
    } else {
      $customData['new_member'] = 0;
    }
    if (in_array($record['Cancellation'], $acceptedYesValues)) {
      $customData['new_sdd_cancel'] = 1;
    } else {
      $customData['new_sdd_cancel'] = 0;
    }
    $customData['new_areas_interest'] = $this->getAreasOfInterest($record['Interests']);
    $customData['new_remarks'] = $record['Notes'];
    $customData['new_sdd_mandate'] = $record['Mandate Reference'];
    $customData['new_sdd_iban'] = $record['IBAN'];
    $customData['new_sdd_bankname'] = $record['Bank Name'];
    $customData['new_sdd_bic'] = $record['Bic'];
    $customData['new_sdd_amount'] = CRM_Utils_Money::format($record['Amount']);
    $customData['new_sdd_freq_interval'] = $record['Frequency Interval'];
    $customData['new_sdd_freq_unit'] = $this->getFrequencyUnit($record['Frequency Unit']);
    $customData['new_sdd_start_date'] = date('Ymd', strtotime($record['Start Date']));
    if (!empty($record['End Date'])) {
      $customData['new_sdd_end_date'] = date('Ymd', strtotime($record['End Date']));
    }
    return $customData;
  }
}