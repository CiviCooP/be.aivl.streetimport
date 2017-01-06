<?php

require_once 'CRM/Core/Form.php';

class CRM_Streetimport_Form_CreateMandate extends CRM_Core_Form
{
    public function preProcess()
    {
        foreach (array('contact_id', 'activity_id', 'activity_type_id') as $fp) {
            if ($value = CRM_Utils_Request::retrieve($fp, 'Positive')) {
                $this->set($fp, $value);
            }
        }
    }

    public function buildQuickForm()
    {
        //get default data for the form
        //try and create a mandate

        $activityType = civicrm_api3('OptionValue', 'getsingle', array('option_group_id' => 'activity_type', 'value' => $this->get('activity_type_id')));
        if ($activityType['name'] == 'streetRecruitment') {
            $fieldPrefix = 'new_';
        } elseif ($activityType['name'] == 'welcomeCall') {
            $fieldPrefix = 'wc_';
        }

        $this->assign('activityId', $this->get('activity_id'));
        $this->assign('activityType', $activityType['label']);

        $fieldNames['amount'] = 'sdd_amount';
        $this->add('text', 'amount', ts('Amount'));

        $fieldNames['reference'] = 'sdd_mandate';
        $this->add('text', 'reference', ts('Reference'));

        $fieldNames['frequency_interval'] = 'sdd_freq_interval';
        $this->add('text', 'frequency_interval', ts('Frequency interval'));

        $fieldNames['iban'] = 'sdd_iban';
        $this->add('text', 'iban', ts('IBAN'));

        $fieldNames['bic'] = 'sdd_bic';
        $this->add('text', 'bic', ts('BIC code'));

        $fieldNames['bank_name'] = 'sdd_bank_name';
        $this->add('text', 'bank_name', ts('Bank name'));


        // create a record for the extractor to process
        $activity = civicrm_api3('activity', 'getsingle', array('id' => $this->get('activity_id')));
        foreach ($fieldNames as $recordKey => $customFieldName) {
            $result = civicrm_api3('CustomField', 'getsingle', array('name' => $fieldPrefix.$customFieldName));
            if (isset($activity['custom_'.$result['id']])) {
                $defaults[$recordKey] = $activity['custom_'.$result['id']];
            }
        }
        $this->setDefaults($defaults);

        $this->addButtons(array(
          array(
            'type' => 'submit',
            'name' => ts('Create'),
            'isDefault' => true,
          ),
        ));
    }
    public function postProcess()
    {
        // collect valid params submitted info, form vars, and sensible defaults
        $validKeys = array(
          'amount' => null,
          'reference' => null,
          'frequency_interval' => null,
          'iban' => null,
          'bic' => null,
          'bank_name' => null
        );

        $params = array_intersect_key($this->getSubmitValues(), $validKeys);

        $params['type'] = 'RCUR'; // Should always be recur
        $params['contact_id'] = $this->get('contact_id');

        $mandate_data = array(
          'contact_id' => $params['contact_id'],
          'iban' => $params['iban'],
          'bic' => $params['bic'],
          'bank_name' => $params['bank_name']
        );

        // use the already existing saveBankAccount method to save bank account details

        // create a simple logger that will be useful if we need to display any of
        // the logged messages that ::saveBankAccountgenerates to the user, and also
        // keeps tracks of whether any errors have occured.

        $logger = new onScreenLogger();
        $rh = new CRM_Streetimport_StreetRecruitmentRecordHandler($logger);
        $record = '';
        $rh->saveBankAccount($mandate_data, $record);
        try{
          $result = civicrm_api3('SepaMandate', 'createfull', $params);
        }catch(Exception $e){
          CRM_Core_Session::setStatus($e->getMessage(), 'Could not create mandate', 'alert');
          $result = array('is_error' => 1);
        }
        if(!$result['is_error'] && !$logger->error){
            CRM_Core_Session::setStatus('All is well', 'Mandate created', 'info');
            CRM_Utils_System::redirect('/civicrm/activity?atype='.$this->get('activity_type_id').'&action=view&reset=1&id='.$this->get('activity_id').'&cid='.$this->get('contact_id').'&context=activity&searchContext=activity');
        } else {
        }
    }
}

class onScreenLogger{

  function __construct(){
    $this->error = 0;
  }

  function abort($message, $record){
    $this->error = 1;
    $this->messages[]=$message;
  }

  function logDebug($message, $record){
    $this->messages[]=$message;
  }

  function logError($message, $record){
    $this->error = 1;
    $this->messages[]=$message;
  }
}
