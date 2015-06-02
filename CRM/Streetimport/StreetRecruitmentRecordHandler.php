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
    $this->logger->logDebug("Processing 'StreetRecruitment' record #{$record['__id']}...");

    // lookup recruiting organisation
    $recruiting_organisation = $this->getRecruitingOrganisation($record);

    // look up / create recruiter
    $recruiter = $this->processRecruiter($record);

    // look up / create donor
    $donor = $this->processDonor($record);



    // "For the Straatwerving or Welkomstgesprek activiteit that will be generated, 
    //    the recruiter will be set as source and assignee.")
    $this->createActivity(array(
                            'type'     => 'Welkomstgesprek',
                            'title'    => 'Welkomstgesprek',
                            'assignee' => (int) $recruiter['id'],
                            'target'   => (int) $recruiter['id'],
                            ));



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
    $organisation_yes_string = 'J'; // TODO: config
    $contact_data = array();
    if (!empty($record['Organization Yes/No']) && $record['Organization Yes/No'] == $organisation_yes_string) {
      $contact_data['contact_type']      = 'Organization';
      $contact_data['organization_name'] = CRM_Utils_Array::value('Last Name', $record);
    } elseif (empty($record['First Name'])) {
      $contact_data['contact_type']      = 'Household';
      $contact_data['household_name']    = CRM_Utils_Array::value('Last Name', $record);
    } else {
      $contact_data['contact_type']      = 'Individual';
      $contact_data['first_name']        = CRM_Utils_Array::value('First Name', $record);
      $contact_data['last_name']         = CRM_Utils_Array::value('Last Name', $record);
      $contact_data['prefix']            = CRM_Utils_Array::value('Prefix', $record);
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
        'street_name'      => CRM_Utils_Array::value('Street Name',   $record),
        'street_number'    => (int) CRM_Utils_Array::value('Street Number', $record),
        'street_unit'      => CRM_Utils_Array::value('Street Unit',   $record),
        'postal_code'      => CRM_Utils_Array::value('Postal code',   $record),
        'street_address'   => trim(CRM_Utils_Array::value('Street Name',   $record) . ' ' . CRM_Utils_Array::value('Street Number', $record) . ' ' . CRM_Utils_Array::value('Street Unit',   $record)),
        'city'             => CRM_Utils_Array::value('City',          $record),
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
    
  }
}