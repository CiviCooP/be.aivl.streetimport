<?php

class CRM_Streetimport_ImportBatchRepo{

    function getAllProcessedImports(){
        $config = CRM_Streetimport_Config::singleton();
        foreach(glob($config->getProcessedFileLocation().'*.csv') as $import){
            $imports[]=new CRM_Streetimport_ImportBatch(pathinfo($import)['filename']);
        }
        return $imports;
    }
}
