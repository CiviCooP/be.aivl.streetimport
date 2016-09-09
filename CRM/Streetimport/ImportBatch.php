<?php

class CRM_Streetimport_ImportBatch{

    function __construct($importBatchId){
        $config = CRM_Streetimport_Config::singleton();
        $importBatchCsvFile = $config->getProcessedFileLocation().$importBatchId.'.csv';
        if(!file_exists($importBatchCsvFile)){
          throw new Exception('Could not find import batch with id: '.$importBatchId);
        }
        $this->importBatchId = $importBatchId;
        $this->importBatchCsvFile = $importBatchCsvFile;
        $this->records = CRM_Streetimport_Utils::csvToArray($importBatchCsvFile);
    }

    function getContacts(){
        foreach($this->records as $record){
          $contactIds[]=CRM_Streetimport_Utils::getContactIdFromDonorId($record['Donor_id'], $record['Recruiting_organization_id']);
        }
        $result = civicrm_api3('Contact', 'get', array('id' => $contactIds));
        return $result['values'];
    }
}
