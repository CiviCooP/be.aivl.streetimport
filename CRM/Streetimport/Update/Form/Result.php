<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class.
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Streetimport_Update_Form_Result extends CRM_Streetimport_Update_Form_Base
{

    public function buildQuickForm()
    {
        $this->assign('contacts', $this->get('contacts'));
        $this->addButtons(array(
        array(
          'type' => 'submit',
          'name' => ts('OK'),
          'isDefault' => true,
        ),
    ));

    // export form elements

    parent::buildQuickForm();
    }

    public function postProcess()
    {
      CRM_Utils_System::redirect('civicrm/streetimport/update');
    }
}
