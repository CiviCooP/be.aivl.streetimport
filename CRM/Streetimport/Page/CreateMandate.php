<?php

require_once 'CRM/Core/Page.php';

class CRM_Streetimport_Page_CreateMandate extends CRM_Core_Page {
  public function run() {
    $contactId = CRM_Utils_Request::retrieve('contact_id', 'Positive');
    $activityId = CRM_Utils_Request::retrieve('activity_id', 'Positive');
    $activityTypeId = CRM_Utils_Request::retrieve('activity_type_id', 'Positive');
    //try and create a mandate
    $success = true;
    if($success){
      CRM_Core_Session::setStatus('All is well', 'Mandate created', 'info');
    }else{
      CRM_Core_Session::setStatus('Something went wrong', 'Could not create mandate', 'alert');
    }
    // Example: Assign a variable for use in a template
    CRM_Utils_System::redirect('/civicrm/activity?atype=56&action=view&reset=1&id=65&cid=56');

    // createSDDMandate

    // saveBankAccount
    parent::run();
  }
}

// STEP 6: create SEPA mandate
