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
   * @param $record array of key=>value pairs
   * @return true or false
   */
  public function canProcessRecord($record) {
    return isset($record['Loading type']) && $record['Loading type'] == CRM_Streetimport_Config::singleton()->getStreetRecruitmentImportType();
  }
  
  /** 
   * process the given record
   *
   * @param array $record array of key=>value pairs
   * @throws exception if failed
   */
  public function processRecord($record) {
    $this->logger->logDebug(CRM_Streetimport_Config::singleton()->translate("Processing StreetRecruitment record")."...", $record);

    // STEP 1: lookup recruiting organisation
    $recruiting_organisation = $this->getRecruitingOrganisation($record);

    // STEP 2: look up / create recruiter
    $recruiter = $this->processRecruiter($record, $recruiting_organisation);

    // STEP 3: look up / create donor
    $donor = $this->processDonor($record, $recruiting_organisation);

    // next steps only if we have a donor (issue #82)
    if (!empty($donor)) {

      // STEP 5: create activity "Straatwerving" if pattern is OK
      $errorMessage = $this->donorAlreadyHasIncomingActivity($donor, 'StreetRecruitment');
      if ($errorMessage) {
        $this->logger->logError(CRM_Streetimport_Config::singleton()->translate($errorMessage) . ", " . CRM_Streetimport_Config::singleton()->translate("donor") . " "
          . $record['DonorID'] . " /" . CRM_Streetimport_Config::singleton()->translate("CiviCRM contact id") . " " . $donor['id'] . " "
          . CRM_Streetimport_Config::singleton()->translate("and name") . " " . $donor['sort_name'] . " "
          . CRM_Streetimport_Config::singleton()->translate("Line in import file for Street Recruitment ignored."), $record,
          CRM_Streetimport_Config::singleton()->translate($errorMessage), "Error");
      }
      else {
        $campaignId = $this->getCampaignParameter($record);
        $streetRecruitmentActivityType = CRM_Streetimport_Config::singleton()->getStreetRecruitmentActivityType();
        $streetRecruitmentSubject = $this->concatActivitySubject("Street Recruitment", $campaignId);
        $streetRecruitmentActivityStatusId = CRM_Streetimport_Config::singleton()->getStreetRecruitmentActivityStatusId();

        $createdActivity = $this->createActivity([
          'activity_type_id' => $streetRecruitmentActivityType,
          'subject' => $streetRecruitmentSubject,
          'status_id' => $streetRecruitmentActivityStatusId,
          'location' => $record['Recruitment Location'],
          'activity_date_time' => date("Ymdhis", strtotime(CRM_Streetimport_Utils::formatCsvDate($record['Recruitment Date']))),
          'target_contact_id' => (int)$donor['id'],
          'source_contact_id' => $recruiter['id'],
          //'assignee_contact_id'=> $recruiter['id'],
          'campaign_id' => $campaignId,
          'details' => CRM_Streetimport_Utils::renderTemplate('activities/StreetRecruitment.tpl', $this->_genericActivityTplInfo),
        ], $record);
        // add custom data to the created activity
        $this->createActivityCustomData($createdActivity->id, CRM_Streetimport_Config::singleton()->getStreetRecruitmentCustomGroup('table_name'), $this->buildActivityCustomData($record), $record);

        // STEP 6: create SEPA mandate (if Cancel is not YES)
        $acceptedYesValues = CRM_Streetimport_Config::singleton()->getAcceptedYesValues();
        if (!in_array($record['Cancellation'], $acceptedYesValues)) {
          $mandate_data = $this->extractMandate($record, $donor['mandate_contact_id']);
          if (!empty($mandate_data)) {
            $mandate = $this->createSDDMandate($mandate_data, $record);
            if ($mandate) {
              // if successful, store the bank account data
              $this->saveBankAccount($mandate_data, $record);
            }
          }
        }

        // STEP 7: add to newsletter group if requested
        if ($this->isTrue($record, "Newsletter")) {
          $newsletter_group_id = CRM_Streetimport_Config::singleton()->getNewsletterGroupID();
          $this->addContactToGroup($donor['id'], $newsletter_group_id, $record);
        }

        // STEP 8: create membership if requested
        if ($this->isTrue($record, "Member")) {
          $membershipTypeId = CRM_Streetimport_Config::singleton()->getMembershipTypeID();
          $membershipSource = CRM_Streetimport_Config::singleton()->translate('Activity') . ' ' . CRM_Streetimport_Config::singleton()->translate('Street Recruitment') . ' ' . $createdActivity->id;
          $this->createMembership([
            'contact_id' => $donor['id'],
            'membership_type_id' => $membershipTypeId,
            'membership_source' => $membershipSource
          ], $recruiter['id'], $record);
        }


        // STEP 8: create activity 'Opvolgingsgesprek' if requested
        if ($this->isTrue($record, "Follow Up Call")) {
          $followUpDateTime = date('YmdHis', strtotime("+" . CRM_Streetimport_Config::singleton()->getFollowUpOffsetDays() . " day"));
          $followUpActivityType = CRM_Streetimport_Config::singleton()->getFollowUpCallActivityType();
          $followUpSubject = CRM_Streetimport_Config::singleton()->translate("Follow Up Call from") . " " . CRM_Streetimport_Config::singleton()->translate('Street Recruitment');
          $followUpActivityStatusId = CRM_Streetimport_Config::singleton()->getFollowUpCallActivityStatusId();
          $fundraiserContactId = CRM_Streetimport_Config::singleton()->getFundraiserContactID();

          $this->createActivity([
            'activity_type_id' => $followUpActivityType,
            'subject' => $followUpSubject,
            'status_id' => $followUpActivityStatusId,
            'activity_date_time' => $followUpDateTime,
            'target_contact_id' => (int)$donor['id'],
            'source_contact_id' => $recruiter['id'],
            'assignee_contact_id' => $fundraiserContactId,
            'campaign_id' => $campaignId,
            'details' => CRM_Streetimport_Utils::renderTemplate('activities/FollowUpCall.tpl', $record),
          ], $record);
        }
      }
    }

    // DONE
    $this->logger->logImport($record, TRUE, CRM_Streetimport_Config::singleton()->translate('StreetRecruitment'));
  }


  /**
   * Method to build data for custom group street recruitment
   *
   * @param $record
   * @return array $customData
   * @access protected
   */
  protected function buildActivityCustomData($record) {
    $acceptedYesValues = CRM_Streetimport_Config::singleton()->getAcceptedYesValues();
    $frequencyUnit = $this->getFrequencyUnit($record['Frequency Unit']);
    $areasOfInterest = $this->getAreasOfInterest($record['Interests']);
    $customData = [];
    if (isset($record['source'])) {
      $customData['new_import_file'] = ['value' => $record['source'], 'type' => 'String'];
    }
    $customData['new_org_mandate'] = ['value' => 0, 'type' => 'Integer'];
    if (isset($record['Organization Yes/No'])) {
      if (in_array($record['Organization Yes/No'], $acceptedYesValues)) {
        $customData['new_org_mandate'] = ['value' => 1, 'type' => 'Integer'];
      }
    }
    $customData['new_date_import'] = ['value' => date('Ymd'), 'type' => 'Date'];
    if (in_array($record['Follow Up Call'], $acceptedYesValues)) {
      $customData['new_follow_up_call'] = ['value' => 1, 'type' => 'Integer'];
    } else {
      $customData['new_follow_up_call'] = ['value' => 0, 'type' => 'Integer'];
    }
    if (in_array($record['Newsletter'], $acceptedYesValues)) {
      $customData['new_newsletter'] = ['value' => 1, 'type' => 'Integer'];
    } else {
      $customData['new_newsletter'] = ['value' => 0, 'type' => 'Integer'];
    }
    if (in_array($record['Member'], $acceptedYesValues)) {
      $customData['new_member'] = ['value' => 1, 'type' => 'Integer'];
    }
    else {
      $customData['new_member'] = ['value' => 0, 'type' => 'Integer'];
    }
    if (in_array($record['Cancellation'], $acceptedYesValues)) {
      $customData['new_sdd_cancel'] = ['value' => 1, 'type' => 'Integer'];
    }
    else {
      $customData['new_sdd_cancel'] = ['value' => 0, 'type' => 'Integer'];
    }
    $customData['new_areas_interest'] = ['value' => $areasOfInterest, 'type' => 'String'];
    $notes = new CRM_Streetimport_Notes();
    if (!$notes->isNotesEmptyCompany($record['Notes'])) {
      // only add notes part
      if ($notes->hasOrganizationStuff($record['Notes'])) {
        $notesTxt = trim($notes->splitRealNoteAndOrganization($record['Notes'])['notes_bit']);
      }
      else {
        $notesTxt = trim($record['Notes']);

      }
      $customData['new_remarks'] = ['value' => $notesTxt, 'type' => 'String'];
    }
    $customData['new_sdd_mandate'] = ['value' => $record['Mandate Reference'], 'type' => 'String'];
    $customData['new_sdd_iban'] = ['value' => $record['IBAN'], 'type' => 'String'];
    $customData['new_sdd_bank_name'] = ['value' => $record['Bank Name'], 'type' => 'String'];
    $customData['new_sdd_bic'] = ['value' => $record['Bic'], 'type' => 'String'];
    $fixedAmount = $this->fixImportedAmount($record['Amount']);
    $customData['new_sdd_amount'] = ['value' => $fixedAmount, 'type' => 'Money'];
    $customData['new_sdd_freq_interval'] = ['value' => $record['Frequency Interval'], 'type' => 'Integer'];
    $customData['new_sdd_freq_unit'] = ['value' => $frequencyUnit, 'type' => 'Integer'];
    if (!empty($record['Start Date'])) {
      $customData['new_sdd_start_date'] = ['value' => date('Ymd', strtotime(CRM_Streetimport_Utils::formatCsvDate($record['Start Date']))), 'type' => 'Date'];
    }
    if (!empty($record['End Date'])) {
      $customData['new_sdd_end_date'] = ['value' => date('Ymd', strtotime(CRM_Streetimport_Utils::formatCsvDate($record['End Date']))), 'type' => 'Date'];
    }
    return $customData;
  }
}