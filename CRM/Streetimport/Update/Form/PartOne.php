<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class.
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Streetimport_Update_Form_PartOne extends CRM_Streetimport_Update_Form_Base
{
    public function buildQuickForm()
    {
        $importBatchId = CRM_Utils_Request::retrieve('id', 'String');

        // If id is in the URL, presume we have just started the batch update wizard
        // Check that it is a valid batch id and save it to form scope

        if ($importBatchId) {
            try {
                $importBatch = new CRM_Streetimport_ImportBatch($importBatchId);
                $this->set('importBatchId', $importBatchId);
            } catch (Exception $e) {
                // else complain that this is not a valid import batch id
            CRM_Core_Error::fatal($e->getMessage());
            }
        }

        $this->assign('contacts', $importBatch->getContacts());

        $updatableFields = new CRM_Streetimport_UpdatableFields;
        $updatableFields->get('Activity', 'werg');

        // add form elements
        $this->addButtons(array(
          array(
            'type' => 'next',
            'name' => ts('next'),
            'isDefault' => true,
          ),
        ));

        parent::buildQuickForm();
    }

    public function postProcess()
    {
        parent::postProcess();
    }
}
