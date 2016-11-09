<?php

class CRM_Streetimport_ProcessedBatch
{
    private $id;
    private $records = array();
    private $contacts = array();

    public function __construct($importBatchId)
    {
        $config = CRM_Streetimport_Config::singleton();
        $importBatchCsvFile = $config->getProcessedFileLocation().$importBatchId.'.csv';
        if (!file_exists($importBatchCsvFile)) {
            throw new Exception('Could not find import batch with id: '.$importBatchId);
        }
        $this->importBatchId = $importBatchId;
        $this->id = $importBatchId;
        $this->importBatchCsvFile = $importBatchCsvFile;
        $records = CRM_Streetimport_Utils::csvToArray($importBatchCsvFile);
        foreach ($records as $record) {
          $this->records[$record['Donor_id']]=$record;
        }
    }

    public function getId()
    {
        return $this->id;
    }
    public function getRecords()
    {
        return $this->records;
    }
    public function getContacts()
    {
        if (!$this->contacts) {
            foreach ($this->records as $record) {
                $contactIds[$record['Donor_id']] = CRM_Streetimport_Utils::getContactIdFromDonorId($record['Donor_id'], $record['Recruiting_organization_id']);
            }
            $result = civicrm_api3('Contact', 'get', array('id' => $contactIds));
            foreach ($contactIds as $donorId => $contactId) {
              if(isset($result['values'][$contactId])){
                $this->contacts[$donorId] = $result['values'][$contactId];
              }
            }
        }
        return $this->contacts;
    }
}
