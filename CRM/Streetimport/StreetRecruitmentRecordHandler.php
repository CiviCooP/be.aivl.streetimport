<?php
/**
 * This class can process records of type 'street recruitment'
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
class CRM_Streetimport_StreetRecruitmentRecordHandler extends CRM_Streetimport_StreetimportRecordHandler {

  /** 
   * Check if the given handler implementation can process the record
   *
   * @param $record  an array of key=>value pairs
   * @return true or false
   */
  public function canProcessRecord($record) {
    $config = CRM_Streetimport_Config::singleton();
    foreach ($record as $key => $value) {
    }
    return isset($record['Loading type']) && $record['Loading type'] == $config->getStreetRecruitmentImportType();
  }
  
  /** 
   * process the given record
   *
   * @param $record  an array of key=>value pairs
   * @return true
   * @throws exception if failed
   */
  public function processRecord($record) {
    $config = CRM_Streetimport_Config::singleton();
    $this->logger->logDebug("Processing 'StreetRecruitment' record #{$record['__id']}...");

    // STEP 1: lookup recruiting organisation
    $recruiting_organisation = $this->getRecruitingOrganisation($record);


    // STEP 2: look up / create recruiter
    $recruiter = $this->processRecruiter($record, $recruiting_organisation);

    // STEP 3: look up / create donor
    $donor = $this->processDonor($record, $recruiting_organisation);

    // STEP 5: create activity "Straatwerving"
    $createdActivity = $this->createActivity(array(
                            'activity_type_id'   => $config->getStreetRecruitmentActivityType(),
                            'subject'            => $config->translate("Street Recruitment"),
                            'status_id'          => $config->getStreetRecruitmentActivityStatusId(),
                            'activity_date_time' => date('YmdHis'),
                            'target_contact_id'  => (int) $donor['id'],
                            'source_contact_id'  => $recruiter['id'],
                            //'assignee_contact_id'=> $recruiter['id'],
                            'details'            => $this->renderTemplate('activities/StreetRecruitment.tpl', $record),
                              ));
    // add custom data to the created activity
    $this->createActivityCustomData($createdActivity->id, $config->getStreetRecruitmentCustomGroup('table_name'), $this->buildActivityCustomData($record));

    // STEP 6: create SEPA mandate
    $mandate_data = $this->extractMandate($record, $donor['id']);
    $mandate = $this->createSDDMandate($mandate_data);

    // STEP 7: add to newsletter group if requested
    if ($this->isTrue($record, "Newsletter")) {
      $newsletter_group_id = $config->getNewsletterGroupID();
      $this->addContactToGroup($donor['id'], $newsletter_group_id);
    }
    
    // STEP 8: create membership if requested
    if ($this->isTrue($record, "Member")) {
      $this->createMembership(array(
        'contact_id'         => $donor['id'],
        'membership_type_id' => $config->getMembershipTypeID(),
        'membership_source' => $config->translate('Activity').' '.$config->translate('Street Recruitment').' '.$createdActivity->id
      ));
    }


    // STEP 8: create activity 'Opvolgingsgesprek' if requested
    if ($this->isTrue($record, "Follow Up Call")) {
      $this->createActivity(array(
                              'activity_type_id'   => $config->getFollowUpCallActivityType(),
                              'subject'            => $config->translate("Follow Up Call from ").$config->translate('Street Recruitment'),
                              'status_id'          => $config->getFollowUpCallActivityStatusId(),
                              'activity_date_time' => date('YmdHis', strtotime("+1 day")),
                              'target_contact_id'  => (int) $donor['id'],
                              'source_contact_id'  => $recruiter['id'],
                              'assignee_contact_id'=> $config->getFundraiserContactID(),
                              'details'            => $this->renderTemplate('activities/FollowUpCall.tpl', $record),
                              ));
    }

    // DONE
    $this->logger->logImport($record['__id'], true, 'StreetRecruitment');
  }


  /**
   * Method to build data for custom group street recruitment
   *
   * @param $record
   * @return array $customData
   * @access protected
   */
  protected function buildActivityCustomData($record) {
    $config = CRM_Streetimport_Config::singleton();
    $acceptedYesValues = $config->getAcceptedYesValues();
    $customData = array();
    $customData['new_date_import'] = array('value' => date('Ymd'), 'type' => 'Date');
    $customData['new_recruit_location'] = array('value' => $record['Recruitment Location'], 'type' => 'String');
    // TODO re-activate if recruiment type required after all
    //$customData['new_recruit_type'] = $this->getRecruitmentType($record['Recruitment Type']);
    if (in_array($record['Follow Up Call'], $acceptedYesValues)) {
      $customData['new_follow_up_call'] = array('value' => 1, 'type' => 'Integer');
    } else {
      $customData['new_follow_up_call'] = array('value' => 0, 'type' => 'Integer');
    }
    if (in_array($record['Newsletter'], $acceptedYesValues)) {
      $customData['new_newsletter'] = array('value' => 1, 'type' => 'Integer');
    } else {
      $customData['new_newsletter'] = array('value' => 0, 'type' => 'Integer');
    }
    if (in_array($record['Member'], $acceptedYesValues)) {
      $customData['new_member'] = array('value' => 1, 'type' => 'Integer');
    } else {
      $customData['new_member'] = array('value' => 0, 'type' => 'Integer');
    }
    if (in_array($record['Cancellation'], $acceptedYesValues)) {
      $customData['new_sdd_cancel'] = array('value' => 1, 'type' => 'Integer');
    } else {
      $customData['new_sdd_cancel'] = array('value' => 0, 'type' => 'Integer');
    }
    $customData['new_areas_interest'] = array('value' => $this->getAreasOfInterest($record['Interests']), 'type' => 'String');
    $customData['new_remarks'] = array('value' => $record['Notes'], 'type' => 'String');
    $customData['new_sdd_mandate'] = array('value' => $record['Mandate Reference'], 'type' => 'String');
    $customData['new_sdd_iban'] = array('value' => $record['IBAN'], 'type' => 'String');
    $customData['new_sdd_bank_name'] = array('value' => $record['Bank Name'], 'type' => 'String');
    $customData['new_sdd_bic'] = array('value' => $record['Bic'], 'type' => 'String');
    $customData['new_sdd_amount'] = array('value' => $record['Amount'], 'type' => 'Money');
    $customData['new_sdd_freq_interval'] = array('value' => $record['Frequency Interval'], 'type' => 'Integer');
    $customData['new_sdd_freq_unit'] = array('value' => $this->getFrequencyUnit($record['Frequency Unit']), 'type' => 'Integer');
    if (!empty($record['Start Date'])) {
      $customData['new_sdd_start_date'] = array('value' => date('Ymd', strtotime($record['Start Date'])), 'type' => 'Date');
    }
    if (!empty($record['End Date'])) {
      $customData['new_sdd_end_date'] = array('value' => date('Ymd', strtotime($record['End Date'])), 'type' => 'Date');
    }
    return $customData;
  }
}