<?php

require_once 'CRM/Core/Form.php';

/**
 * Form controller class.
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Streetimport_Update_Form_Confirm extends CRM_Streetimport_Update_Form_Base
{
    public function buildQuickForm()
    {
        $this->assign('old', $this->get('old'));
        $this->assign('contacts', $this->get('contacts'));
        $new['recruiter'] = civicrm_api3('Contact', 'getsingle', array('id' => $this->controller->exportValue('Define', 'recruiter_id')));
        $new['campaign'] = civicrm_api3('Campaign', 'getsingle', array('id' => $this->controller->exportValue('Define', 'campaign_id')));
        $this->assign('new', $new);

        $this->addButtons(array(
            array('type' => 'back', 'name' => ts('back')),
            array('type' => 'next', 'name' => ts('update'), 'isDefault' => true,)
        ));

        parent::buildQuickForm();
    }

    public function postProcess()
    {
        $recruiterId = $this->controller->exportValue('Define', 'recruiter_id');
        $campaignId = $this->controller->exportValue('Define', 'campaign_id');

        // use the updater to update the four entities
        //
// the added by contact for the Activity type streetRecruitment

        $contacts = $this->get('contacts');
        $records = $this->get('batch')->getRecords();

        // Get the correct entities to update
        // This process feels quite complex + expensive
        // Might be simpler if we assign a batch to all entities as they get processed

        $recruiterFieldGroup = civicrm_api3('CustomGroup', 'getsingle', array('name' => 'externalDonorId'))['id'];
        $recruiterField = 'custom_'.civicrm_api3('CustomField', 'getsingle', array('name' => 'recruiter_id', 'custom_group_id' => $recruiterFieldGroup))['id'];

        $mandateFieldGroup = civicrm_api3('CustomGroup', 'getsingle', array('name' => 'streetRecruitment'))['id'];
        $mandateField = 'custom_'.civicrm_api3('CustomField', 'getsingle', array('name' => 'new_sdd_mandate', 'custom_group_id' => $mandateFieldGroup))['id'];

        $activityTypeOptionGroupId = civicrm_api3('OptionGroup', 'getsingle', array('name' => 'Activity_Type'))['id'];
        $activityTypeId = civicrm_api3('OptionValue', 'getsingle', array('option_group_id' => $activityTypeOptionGroupId, 'name' => 'streetRecruitment'))['value'];

        foreach ($contacts as $Donor_id => $contact) {
            if($contact['id']){
              $contactIds[] = $contact['id'];
              $params = array($mandateField => $records[$Donor_id]['Mandate_reference'], 'target_contact_id'=>$contact['id'], 'activity_type_id' => $activityTypeId);
              $result = civicrm_api3('Activity', 'get', $params);
              if($result['count']==1){
                $activityIds[] = $result['id'];
              }
              $result = civicrm_api3('SepaMandate', 'get', array('reference' => $records[$Donor_id]['Mandate_reference']));
              if($result['count']==1){
                $contributionRecurIds[] = $result['values'][$result['id']]['entity_id'];
              }
              // $activityContactIds[] = civicrm_api3('ActivityContact', 'getsingle', array('activity_id' => $activityId, 'record_type_id' => 2)))['id'];
            }
        }

        $updater = new CRM_Streetimport_Updater();
        $updater->setEntity('Contact');
        $updater->setEntityIds($contactIds);
        $updater->setUpdate(array(
          $recruiterField => $recruiterId
        ));
        $updater->run();

        $updater = new CRM_Streetimport_Updater();
        $updater->setEntity('Activity');
        $updater->setEntityIds($activityIds);
        $updater->setUpdate(array(
          'campaign_id' => $campaignId
        ));
        $updater->run();

        // civicrm_contribution_recur.campaign_id
        $updater = new CRM_Streetimport_Updater();
        $updater->setEntity('ContributionRecur');
        $updater->setEntityIds($contributionRecurIds);
        $updater->setUpdate(array(
          'campaign_id' => $campaignId
        ));
        $updater->run();

        parent::postProcess();
    }
}
