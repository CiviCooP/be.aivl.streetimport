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
    error_log("processing street recruitment");

    // lookup recruiting organisation
    $recruiting_organisation = $this->getRecruitingOrganisation();

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
    // check, if this is an organisation or not
    $is_organisation = !empty($record['Organization Yes/No']) && $record['Organization Yes/No'] == 'Yes';

    // TODO: lookup by "DonorID"

    // create base contact
    $donor = $this->createContact(array(
        'contact_type'     => ($is_organisation?'Organization':'Individual'),
        //'contact_sub_type' => ???? "Donor"?
        'first_name'       => CRM_Utils_Array::value('First Name', $record),
        'last_name'        => CRM_Utils_Array::value('Last Name',  $record),
        'prefix'           => CRM_Utils_Array::value('Prefix',     $record),
        'birth_date'       => CRM_Utils_Array::value('Birth date (format jjjj-mm-dd)', $record),
      ));
    $this->setDonorID($donor['id'], $record['DonorID']);
    if (empty($donor)) {
      $this->logger->abort("Cannot create new donor. Import failed.");
    }

    // create address
    $address = $this->createAddress(array(
        'contact_id'       => $donor['id'],
        'location_type_id' => 1, // TODO: config
        'street_name'      => CRM_Utils_Array::value('Street Name',   $record),
        'street_number'    => CRM_Utils_Array::value('Street Number', $record),
        'street_unit'      => CRM_Utils_Array::value('Street Unit',   $record),
        'postal_code'      => CRM_Utils_Array::value('Postal code',   $record),
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