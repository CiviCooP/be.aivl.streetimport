<?php

require_once 'CRM/Core/Page.php';

class CRM_Streetimport_Update_Page_Update extends CRM_Core_Page {
  public function run() {

    CRM_Utils_System::setTitle(ts('Update a street import'));

    //Get all processed imports
    $pbr = new CRM_Streetimport_ProcessedBatchRepo;
    $imports = $pbr->getAll();
    $this->assign('imports', $imports);

    parent::run();
  }
}
