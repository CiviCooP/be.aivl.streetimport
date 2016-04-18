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
    return isset($record['Loading type']) && $record['Loading type'] == $config->getStreetRecruitmentImportType();
  }
  
  /** 
   * process the given record
   *
   * @param array $record array of key=>value pairs
   * @return true
   * @throws exception if failed
   */
  public function processRecord($record) {
    $config = CRM_Streetimport_Config::singleton();
    $this->logger->logDebug($config->translate("Processing StreetRecruitment record")."...", $record);

    // STEP 1: lookup recruiting organisation
    $recruiting_organisation = $this->getRecruitingOrganisation($record);


    // STEP 2: look up / create recruiter
    $recruiter = $this->processRecruiter($record, $recruiting_organisation);

    // STEP 3: look up / create donor
    $donor = $this->processDonor($record, $recruiting_organisation);

    // next steps only if we have a donor (issue #82)
    if (!empty($donor)) {

      // STEP 5: create activity "Straatwerving"
      $campaignId = $this->getCampaignParameter($record);
      $streetRecruitmentActivityTypeId = $config->getStreetRecruitmentActivityType();
      $streetRecruitmentSubject = $this->concatActivitySubject("Street Recruitment", $campaignId);
      $streetRecruitmentActivityStatusId = $config->getStreetRecruitmentActivityStatusId();
      $streetRecruitmentActivityDateTime = date("Ymdhis", strtotime(CRM_Streetimport_Utils::formatCsvDate($record['Recruitment Date'])));
      $streetRecruitmentDetails = $this->renderTemplate('activities/StreetRecruitment.tpl', $record);
      $createdActivity = $this->createActivity(array(
          'activity_type_id' => $streetRecruitmentActivityTypeId,
          'subject' => $streetRecruitmentSubject,
          'status_id' => $streetRecruitmentActivityStatusId,
          'location' => $record['Recruitment Location'],
          'activity_date_time' => $streetRecruitmentActivityDateTime,
          'target_contact_id' => (int)$donor['id'],
          'source_contact_id' => $recruiter['id'],
        //'assignee_contact_id'=> $recruiter['id'],
          'campaign_id' => $campaignId,
          'details' => $streetRecruitmentDetails,
      ), $record);
      // add custom data to the created activity
      $this->createActivityCustomData($createdActivity->id, $config->getStreetRecruitmentCustomGroup('table_name'), $this->buildActivityCustomData($record), $record);
      
      // STEP 6: create SEPA mandate
      $mandate_data = $this->extractMandate($record, $donor['id'], $record);
      if (!empty($mandate_data)) {
        $mandate = $this->createSDDMandate($mandate_data, $record);
        if ($mandate) {
          // if successful, store the bank account data
          $this->saveBankAccount($mandate_data, $record);
        }
      }

      // STEP 7: add to newsletter group if requested
      if ($this->isTrue($record, "Newsletter")) {
        $newsletter_group_id = $config->getNewsletterGroupID();
        $this->addContactToGroup($donor['id'], $newsletter_group_id, $record);
      }

      // STEP 8: create membership if requested
      if ($this->isTrue($record, "Member")) {
        $membershipTypeId = $config->getMembershipTypeID();
        $this->createMembership(array(
            'contact_id' => $donor['id'],
            'membership_type_id' => $config->getMembershipTypeID(),
            'membership_source' => $config->translate('Activity') . ' ' . $config->translate('Street Recruitment') . ' ' . $createdActivity->id
        ), $recruiter['id'], $record);
      }


      // STEP 8: create activity 'Opvolgingsgesprek' if requested
      if ($this->isTrue($record, "Follow Up Call")) {
        $followUpCallActivityType = $config->getFollowUpCallActivityType();
        $followUpSubject = $config->translate("Follow Up Call from") . " " . $config->translate('Street Recruitment');
        $followUpCallStatusId = $config->getFollowUpCallActivityStatusId();
        $fundraiserContactId = $config->getFundraiserContactID();
        $followUpCampaignId = $this->getCampaignParameter($record);
        $followUpDetails = $this->renderTemplate('activities/FollowUpCall.tpl', $record);



        $followUpDateTime = date('YmdHis', strtotime("+" . $config->getFollowUpOffsetDays() . " day"));
        $this->createActivity(array(
            'activity_type_id' => $followUpCallActivityType,
            'subject' => $followUpSubject,
            'status_id' => $followUpCallStatusId,
            'activity_date_time' => $followUpDateTime,
            'target_contact_id' => (int)$donor['id'],
            'source_contact_id' => $recruiter['id'],
            'assignee_contact_id' => $fundraiserContactId,
            'campaign_id' => $followUpCampaignId,
            'details' => $followUpDetails,
        ), $record);
      }
    }

    // DONE
    $this->logger->logImport($record, true, $config->translate('StreetRecruitment'));
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
    $areasOfInterest = $this->getAreasOfInterest($record['Interests']);
    $frequencyUnit = $this->getFrequencyUnit($record['Frequency Unit']);
    $customData['new_areas_interest'] = array('value' => $areasOfInterest, 'type' => 'String');
    $customData['new_remarks'] = array('value' => $record['Notes'], 'type' => 'String');
    $customData['new_sdd_mandate'] = array('value' => $record['Mandate Reference'], 'type' => 'String');
    $customData['new_sdd_iban'] = array('value' => $record['IBAN'], 'type' => 'String');
    $customData['new_sdd_bank_name'] = array('value' => $record['Bank Name'], 'type' => 'String');
    $customData['new_sdd_bic'] = array('value' => $record['Bic'], 'type' => 'String');
    $fixedAmount = $this->fixImportedAmount($record['Amount']);
    $customData['new_sdd_amount'] = array('value' => $fixedAmount, 'type' => 'Money');
    $customData['new_sdd_freq_interval'] = array('value' => $record['Frequency Interval'], 'type' => 'Integer');
    $customData['new_sdd_freq_unit'] = array('value' => $frequencyUnit, 'type' => 'Integer');
    if (!empty($record['Start Date'])) {
      $customData['new_sdd_start_date'] = array('value' => date('Ymd', strtotime(CRM_Streetimport_Utils::formatCsvDate($record['Start Date']))), 'type' => 'Date');
    }
    if (!empty($record['End Date'])) {
      $customData['new_sdd_end_date'] = array('value' => date('Ymd', strtotime(CRM_Streetimport_Utils::formatCsvDate($record['End Date']))), 'type' => 'Date');
    }
    return $customData;
  }
}