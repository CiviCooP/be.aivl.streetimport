<?php

class CRM_Streetimport_ProcessedBatchRepo{

    function getAll(){


        $imports = array();
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
