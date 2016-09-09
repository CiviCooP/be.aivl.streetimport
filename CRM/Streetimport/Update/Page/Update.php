<?php

require_once 'CRM/Core/Page.php';

class CRM_Streetimport_Update_Page_Update extends CRM_Core_Page {
  public function run() {

    CRM_Utils_System::setTitle(ts('Update a street import'));

    //Get all processed imports
    $ibr = new CRM_Streetimport_ImportBatchRepo;
    $imports = $ibr->getAllProcessedImports();
    $this->assign('imports', $imports);

    parent::run();
  }
}
