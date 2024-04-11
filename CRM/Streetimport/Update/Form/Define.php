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

        if(!$batch = $this->get('batch')){
          if(!$batchId = CRM_Utils_Request::retrieve('id', 'String')){
            Throw new Exception("Couldn't find batch ID");
          }
          $batch = new CRM_Streetimport_ProcessedBatch($batchId);
          $this->set('batch', $batch);
          $this->set('contacts', $batch->getContacts());

        }
        //Attempt to get the batch ID from the URL
        // var_dump($batch->getContacts());exit;

            // else complain that this is not a valid import batch id


        $importedValues = array('campaign_id'=> null, 'Recruiter_id'=> null);

        foreach($batch->getRecords() as $record){

          foreach($importedValues as $key => $value){
            if(isset($importedValues[$key][$record[$key]])){
              $importedValues[$key][$record[$key]]++;
            }else{
              $importedValues[$key][$record[$key]]=1;
            }
          }
        }
        // retrieve external recruiter id custom field id
        try {
          $externalRecruiterIdCustomId = civicrm_api3('CustomField', 'getvalue', array('name' => 'external_recruiter_id', 'return' => 'id'));
        } catch (CRM_Core_Exception $ex) {
          throw new Exception(ts('Could not find a custom field with the name external_recruiter_id, contact your system administrator'));
        }
        $fieldName = 'custom_'.$externalRecruiterIdCustomId;

        $old['recruiters'] = civicrm_api3('Contact', 'get', array($fieldName => array_keys($importedValues['Recruiter_id'])))['values'];

        foreach(array_keys($importedValues['campaign_id']) as $id){
            $old['campaigns'][] = civicrm_api3('Campaign', 'getsingle', array('id' => $id));
        }
        $this->set('old', $old);

        $this->assign('old', $old);
        $this->assign('contacts', $this->get('contacts'));


        $this->addEntityRef('campaign_id', ts('Campaign'), array('entity' => 'campaign'), true);
        $this->addEntityRef('recruiter_id', ts('Recruiter'), array('api' => array( 'params' => array('contact_sub_type' => 'recruiter'))), true);

        // add form elements
        $this->addButtons(array(
          array(
            'type' => 'cancel',
            'name' => ts('cancel'),
            'isDefault' => true,
          ),
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
