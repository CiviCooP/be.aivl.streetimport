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
    return isset($record['Loading type']) && $record['Loading type'] == 1;
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

    // STEP 4: copy all to custom fields
    // TODO: implement

    // STEP 5: create activity "Straatwerving"
    $this->createActivity(array(
                            'activity_type_id'   => $config->getStreetRecruitmentActivityType(),
                            'subject'            => $config->translate("Street Recruitment"),
                            'status_id'          => 1,               // TODO: config
                            'activity_date_time' => date('YmdHis'),
                            'target_contact_id'  => (int) $donor['id'],
                            'source_contact_id'  => $recruiter['id'],
                            //'assignee_contact_id'=> $recruiter['id'],
                            'details'            => $this->renderTemplate('activities/StreetRecruitment.tpl', $record),
                            ));

    // STEP 6: create SEPA mandate
    $mandate = $this->processMandate($record);

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
                              'subject'            => $config->translate("Follow Up Call"),
                              'status_id'          => 1,                   // TODO: config
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
   * will create/lookip the donor along with all relevant information
   *
   * @return array with entity data
   */
  protected function processDonor($record) {
    // TODO: lookup by "DonorID"

    // create base contact
    $contact_data = array();
    if ($this->isTrue($record, 'Organization Yes/No')) {
      $contact_data['contact_type']      = 'Organization';
      $contact_data['organization_name'] = CRM_Utils_Array::value('Last Name',  $record);
    } elseif (empty($record['First Name'])) {
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
    $this->setDonorID($donor['id'], $record['DonorID']);
    if (empty($donor)) {
      $this->logger->abort("Cannot create new donor. Import failed.");
    }

    // create address
    $address = $this->createAddress(array(
        'contact_id'       => $donor['id'],
        'location_type_id' => 1, // TODO: config
        'street_name'      => CRM_Utils_Array::value('Street Name',         $record),
        'street_number'    => (int) CRM_Utils_Array::value('Street Number', $record),
        'street_unit'      => CRM_Utils_Array::value('Street Unit',         $record),
        'postal_code'      => CRM_Utils_Array::value('Postal code',         $record),
        'street_address'   => trim(CRM_Utils_Array::value('Street Name',    $record) . ' ' . CRM_Utils_Array::value('Street Number', $record) . ' ' . CRM_Utils_Array::value('Street Unit',   $record)),
        'city'             => CRM_Utils_Array::value('City',                $record),
        'country_id'       => 1020, // TODO: move to config
      ));

    // create phones
    $this->createPhone(array(
        'contact_id'       => $donor['id'],
        'phone_type_id'    => 1, // TODO: config (land line)
        'location_type_id' => 1, // TODO: config (home)
        'phone'            => CRM_Utils_Array::value('Telephone1', $record),
      ));
    $this->createPhone(array(
        'contact_id'       => $donor['id'],
        'phone_type_id'    => 1, // TODO: config (land line)
        'location_type_id' => 2, // TODO: config (home)
        'phone'            => CRM_Utils_Array::value('Telephone2', $record),
      ));
    $this->createPhone(array(
        'contact_id'       => $donor['id'],
        'phone_type_id'    => 2, // TODO: config (mobile)
        'location_type_id' => 1, // TODO: config (home)
        'phone'            => CRM_Utils_Array::value('Mobile1', $record),
      ));
    $this->createPhone(array(
        'contact_id'       => $donor['id'],
        'phone_type_id'    => 2, // TODO: config (mobile)
        'location_type_id' => 2, // TODO: config (home)
        'phone'            => CRM_Utils_Array::value('Mobile2', $record),
      ));

    // create email
    $this->createEmail(array(
        'contact_id'       => $donor['id'],
        'location_type_id' => 2, // TODO: config (home)
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
  protected function processMandate($record) {
    $config = CRM_Streetimport_Config::singleton();
    $mandate_data = $config->extractSDDtype(CRM_Utils_Array::value('Frequency Unit', $record));

    return $this->createSDDMandate(array(
      'iban'               => CRM_Utils_Array::value('IBAN',  $record),
      'bic'                => CRM_Utils_Array::value('Bic',  $record),
      'bank_name'          => CRM_Utils_Array::value('Bank Name',  $record),
      'start_date'         => CRM_Utils_Array::value('Start Date',  $record),
      'end_date'           => CRM_Utils_Array::value('End Date',  $record),
      'reference'          => CRM_Utils_Array::value('Mandate Reference',  $record),
      'amount'             => CRM_Utils_Array::value('Amount',  $record),
      'frequency_unit'     => CRM_Utils_Array::value('Frequency Unit',  $record),
      'frequency_interval' => CRM_Utils_Array::value('Frequency Interval',  $record),
    ));
  }
}