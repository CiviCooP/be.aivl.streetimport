<?php

require_once 'CRM/Core/Page.php';

class CRM_Streetimport_Update_Page_Reset extends CRM_Core_Page {
  public function run() {



    $importBatchId = CRM_Utils_Request::retrieve('id', 'String');

    CRM_Utils_System::setTitle(ts("Reset '{$importBatchId}' street import"));

    if(!isset($importBatchId)){
      throw new Exception('Please supply an import_id');
    }
    $config = CRM_Streetimport_Config::singleton();
    $importFile = $config->getProcessedFileLocation().$importBatchId.'.csv';
    if(!is_file($importFile)){
      $importFile = $config->getImportFileLocation().$importBatchId.'.csv';
      if(!is_file($importFile)){
        throw new Exception("Could not find $importFile");
      }
    }
    $records = CRM_Streetimport_Utils::csvToArray($importFile);
    // rename($importFile, $config->getImportFileLocation().$importBatchId.'.csv');

    $nullLog ='';
    $handler = new CRM_Streetimport_StreetRecruitmentRecordHandler($nullLog);
    foreach($records as $record){
      if($contact = $handler->getDonorWithExternalId($record['Donor_id'], $record['Recruiter_id'], $record)){



      // delete activities
      $activityContacts = civicrm_api3('ActivityContact', 'get', (array('contact_id' => $contact['contact_id'])));
      foreach($activityContacts['values'] as $activityContact){
        // var_dump($activityContact);
        $result = civicrm_api3('Activity', 'get', (array('id' => $activityContact['activity_id'])));
        $result = civicrm_api3('Activity', 'delete', array('id' => $result['id']));

      }
      // delete recurring payments and sepa mandates
      $contributionRecurs = civicrm_api3('ContributionRecur', 'get', (array('contact_id' => $contact['contact_id'])));
      foreach($contributionRecurs['values'] as $contributionRecur){
        $sepaMandate = civicrm_api3('SepaMandate', 'get', array('entity_id' => $contributionRecur['id'], 'entity_table' => 'civicrm_contribution_recur'));
        $sepaMandate = civicrm_api3('SepaMandate', 'delete', array('id' => $sepaMandate['id']));
        civicrm_api3('ContributionRecur', 'delete', array('id' => $contributionRecur['id']));

      }

      // delete memberships
      $memberships = civicrm_api3('Membership', 'get', (array('contact_id' => $contact['contact_id'])));
      foreach($memberships['values'] as $membership){
        civicrm_api3('Membership', 'delete', (array('id' => $membership['id'])));
      }

      // delete the contact - everything else should get deleted automatically
      $contact['skip_undelete']=true;
      $result = civicrm_api3('Contact', 'delete', $contact);

      }
    }

    rename($importFile, $config->getImportFileLocation().$importBatchId.'.csv');


    // civicrm_api3('streetimport', 'importcsvfile', array('id'=>$importBatchId));
    //Get all processed imports
    parent::run();
  }
}
