<?php

class CRM_Streetimport_ImportBatchRepo{

    function getAllProcessedImports(){
        $config = CRM_Streetimport_Config::singleton();
        foreach(glob($config->getProcessedFileLocation().'*.csv') as $import){
            $imports[]=array(
                'id'=>pathinfo($import)['filename'],
                'path'=>$import
            );
        }
        return $imports;
    }
}
