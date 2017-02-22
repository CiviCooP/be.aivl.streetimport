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
        //
        $validKeys = array(
          'amount' => null,
          'reference' => null,
          'frequency_interval' => null,
          'iban' => null,
          'bic' => null,
          'bank_name' => null,
        );

        $params = array_intersect_key($this->getSubmitValues(), $validKeys);

        $params['type'] = 'RCUR'; // Should always be recur
        $params['contact_id'] = $this->get('contact_id');

        // create a bank account (if necessary)

        $account_exists = false;
        $type_id_IBAN = (int) CRM_Core_OptionGroup::getValue('civicrm_banking.reference_types', 'IBAN', 'name', 'String', 'id');

        try {

          // check the user's bank accounts
          $ba_list = civicrm_api3('BankingAccount', 'get', array('contact_id' => $params['contact_id']));
            foreach ($ba_list['values'] as $ba_id => $ba) {
                $ref_query = array('ba_id' => $ba['id'], 'reference_type_id' => $type_id_IBAN);
                $ba_ref_list = civicrm_api3('BankingAccountReference', 'get', $ref_query);
                foreach ($ba_ref_list['values'] as $ba_ref_id => $ba_ref) {
                    if ($ba_ref['reference'] == $params['iban']) {
                        $account_exists = true;
                        break 2;
                    }
                }
            }

            if (!$account_exists) {
                $config = CRM_Streetimport_Config::singleton();
            // create bank account (using BAOs)
            $baExtraSource = $config->translate('Street Recruitment');
                $baDescription = $config->translate('Private Account');
                $ba_extra = array(
                    'BIC' => $params['bic'],
                    'country' => substr($params['iban'], 0, 2),
                    'source' => $baExtraSource,
            );
                if (!empty($params['bank_name'])) {
                    $ba_extra['bank_name'] = $params['bank_name'];
                }

                $ba = civicrm_api3('BankingAccount', 'create', array(
                    'contact_id' => $params['contact_id'],
                    'description' => $baDescription,
                    'created_date' => date('YmdHis'),
                    'data_raw' => '{}',
                    'data_parsed' => json_encode($ba_extra),
                ));

            // add a reference
            civicrm_api3('BankingAccountReference', 'create', array(
                'reference' => $params['iban'],
                'reference_type_id' => $type_id_IBAN,
                'ba_id' => $ba['id'],
            ));
            }
            CRM_Core_Session::setStatus('Bank account saved', 'info');
        } catch (Exception $ex) {
            CRM_Core_Session::setStatus('An error occurred while saving the bank account', 'info');
            $result = array('is_error' => 1);
        }

        // create a mandate

        try {
            $result = civicrm_api3('SepaMandate', 'createfull', $params);
        } catch (Exception $e) {
            CRM_Core_Session::setStatus($e->getMessage(), 'Could not create mandate', 'alert');
            $result = array('is_error' => 1);
        }

        // If there are no errors, report success

        if (!$result['is_error']) {
            CRM_Core_Session::setStatus('Mandate created', 'info');
        }

        // redirect (is this last part necessary?)

        CRM_Utils_System::redirect('/civicrm/activity?atype='.$this->get('activity_type_id').'&action=view&reset=1&id='.$this->get('activity_id').'&cid='.$this->get('contact_id').'&context=activity&searchContext=activity');
    }
}
