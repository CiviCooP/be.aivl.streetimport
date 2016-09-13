<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class.
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Streetimport_Update_Form_Define extends CRM_Streetimport_Update_Form_Base
{
    public function buildQuickForm()
    {
        $batchId = CRM_Utils_Request::retrieve('id', 'String');

        // If id is in the URL, presume we have just started the batch update wizard
        // Check that it is a valid batch id and save it to form scope

        if ($importBatchId) {
            try {
                $batch = new CRM_Streetimport_ProcessedBatch($batchId);
                $this->set('batchId', $batchId);
            } catch (Exception $e) {
                // else complain that this is not a valid import batch id
            CRM_Core_Error::fatal($e->getMessage());
            }
        }





        $this->assign('contacts', $importBatch->getContacts());



        $updater = new CRM_Streetimport_Updater;
        // $updater->setEntity('Contact','Organization'); // should work
        // $updater->setEntity('Contact', 'Individual'); // should work
        // $updater->setEntity('Contact', 'recruiter'); // should work
        // $updater->setEntity('Membership', 'General'); // should work
        // $updater->setEntity('Membership', 'Student'); // should work
        // $updater->setEntity('Activity', ''); // should work
        $updater->setEntity('Activity', 'streetRecruitment'); // should work
        // $updater->setEntity('ContributionRecur'); // should work

        $updater->setUpdate(array(
          'campaign_id' =>'newcampid',
          'recruiter_id' =>'newrecid'
        ));
        $updater->runUpdate();

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
