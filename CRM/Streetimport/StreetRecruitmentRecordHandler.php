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

}