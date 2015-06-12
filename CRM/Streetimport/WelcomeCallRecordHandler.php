<?php
/**
 * This class can process records of type 'welcome call'
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
class CRM_Streetimport_WelcomeCallRecordHandler extends CRM_Streetimport_StreetimportRecordHandler {

  /** 
   * Check if the given handler implementation can process the record
   *
   * @param $record  an array of key=>value pairs
   * @return true or false
   */
  public function canProcessRecord($record) {
    $config = CRM_Streetimport_Config::singleton();
    return isset($record['Loading type']) && $record['Loading type'] == $config->getWelcomeCallImportType();
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
    $this->logger->logDebug("Processing 'WelcomeCall' record #{$record['__id']}...");

    // STEP 1: lookup recruiting organisation
    $recruiting_organisation = $this->getRecruitingOrganisation($record);

    // STEP 2: look up / create recruiter
    $recruiter = $this->processRecruiter($record, $recruiting_organisation);

    // STEP 3: look up / create donor
    $donor = $this->getDonorWithExternalId($record['DonorID']);
    if (empty($donor)) {
      $this->logger->logError("Donor [{$record['DonorID']}] should already exist. Created new contact in order to process record anyway.");
      $donor = $this->processDonor($record, $recruiting_organisation);
    }

    // STEP 5: create activity "WelcomeCall"
    $createdActivity = $this->createActivity(array(
      'activity_type_id'   => $config->getWelcomeCallActivityType(),
      'subject'            => $config->translate("Welcome Call"),
      'status_id'          => $config->getWelcomeCallActivityStatusId(),
      'activity_date_time' => date('YmdHis'),
      'target_contact_id'  => (int) $donor['id'],
      'source_contact_id'  => $recruiter['id'],
      //'assignee_contact_id'=> $recruiter['id'],
      'details'            => $this->renderTemplate('activities/WelcomeCall.tpl', $record),
    ));
    // add custom data to the created activity
    $this->createActivityCustomData($createdActivity->id, $config->getWelcomeCallCustomGroup('table_name'), $this->buildActivityCustomData($record));

    // STEP 6: update SEPA mandate if required
    $this->processMandate($record, $donor['id']);

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
        'subject'            => $config->translate("Follow Up Call from ").$config->translate('Welcome Call'),
        'status_id'          => $config->getFollowUpCallActivityStatusId(),
        'activity_date_time' => date('YmdHis', strtotime("+1 day")),
        'target_contact_id'  => (int) $donor['id'],
        'source_contact_id'  => $recruiter['id'],
        'assignee_contact_id'=> $config->getFundraiserContactID(),
        'details'            => $this->renderTemplate('activities/FollowUpCall.tpl', $record),
      ));
    }

    // DONE
    $this->logger->logImport($record['__id'], true, 'WelcomeCall');
  }


  /**
   * process SDD mandate
   */
  protected function processMandate($record, $donor_id) {
    // first, extract the new mandate information
    $new_mandate_data = $this->extractMandate($record, $donor_id);

    // then load the existing mandate
    try {
      $old_mandate_data = civicrm_api3('SepaMandate', 'getsingle', array('reference' => $new_mandate_data['reference']));    
    } catch (Exception $e) {
      $this->logger->logError("SDD mandate '{$new_mandate_data['reference']}' could not be found.");
      return NULL;
    }
    
    // ...and the attached contribution
    try {
      if ($old_mandate_data['entity_table']=='civicrm_contribution_recur') {
        $old_contribution = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $old_mandate_data['entity_id']));
      } elseif ($old_mandate_data['type']=='civicrm_contribution') {
        $old_contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $old_mandate_data['entity_id']));
        $old_contribution['amount'] = $old_contribution['total_amount'];
      } else {
        $this->logger->abort("Bad SDD mandate type found. Contact developer.");
        return NULL;
      }
    } catch (Exception $e) {
      $this->logger->logError("Couldn't load contribution entity for mandate [{$old_mandate_data['id']}].");
      return NULL;      
    }

    // now, compare new data with old mandate/contribution
    $mandate_diff = array();
    foreach ($new_mandate_data as $key => $value) {
      if (isset($old_mandate_data[$key])) {
        if ($new_mandate_data[$key] != $old_mandate_data[$key]) {
          $mandate_diff[$key] = $new_mandate_data[$key];
        }
      }
      if (isset($old_contribution[$key])) {
        if ($new_mandate_data[$key] != $old_contribution[$key]) {
          $mandate_diff[$key] = $new_mandate_data[$key];
        }
      }
    }

    // if both dates are in the past, we can ignore the change 
    //   (they're both probably just auto-generated)
    if (!empty($mandate_diff['start_date'])) {
      $now = strtotime("now");
      if (  $old_contribution['start_date'] > $now 
         || $new_mandate_data['start_date'] > $now ) {
        unset($mandate_diff['start_date']);
      }
    }

    // filter the changes, some can be safely ignored
    $ignore_changes_for = array('creation_date', 'contact_id');
    foreach ($ignore_changes_for as $field) unset($mandate_diff[$field]);
    // TODO: can we really ignore changes for contact_id? 
    //  => this should only happen if the donor ID failed...

    // TODO: apply changes according to status:
    // INIT: can be changed: frequency_unit/frequency_interval/amount/startdate/enddate/iban/bic/campaign_id
    // BUSY: can be changed: amount/enddate

    // BUT: for the time being, bail if anything had changed:
    if (!empty($mandate_diff)) {
      $field_list = implode(',', array_keys($mandate_diff));
      $this->logger->logError("SDD mandate update requested ($field_list), but this has not been implemented yet.");
    } else {
      $this->logger->logDebug("No SDD mandate update required.");
    }
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
    $customData['wc_date_import'] = date('YmdHis');
    $customData['wc_recruit_location'] = $record['Recruitment Location'];
    $customData['wc_recruit_type'] = $this->getRecruitmentType($record['Recruitment Type']);
    if (in_array($record['Follow Up Call'], $acceptedYesValues)) {
      $customData['wc_follow_up_call'] = 1;
    } else {
      $customData['wc_follow_up_call'] = 0;
    }
    if (in_array($record['Newsletter'], $acceptedYesValues)) {
      $customData['wc_newsletter'] = 1;
    } else {
      $customData['wc_newsletter'] = 0;
    }
    if (in_array($record['Member'], $acceptedYesValues)) {
      $customData['wc_member'] = 1;
    } else {
      $customData['wc_member'] = 0;
    }
    if (in_array($record['Cancellation'], $acceptedYesValues)) {
      $customData['wc_sdd_cancel'] = 1;
    } else {
      $customData['wc_sdd_cancel'] = 0;
    }
    $customData['wc_areas_interest'] = $this->getAreasOfInterest($record['Interests']);
    $customData['wc_remarks'] = $record['Notes'];
    $customData['wc_sdd_mandate'] = $record['Mandate Reference'];
    $customData['wc_sdd_iban'] = $record['IBAN'];
    $customData['wc_sdd_bank_name'] = $record['Bank Name'];
    $customData['wc_sdd_bic'] = $record['Bic'];
    $customData['wc_sdd_amount'] = CRM_Utils_Money::format($record['Amount']);
    $customData['wc_sdd_freq_interval'] = $record['Frequency Interval'];
    $customData['wc_sdd_freq_unit'] = $this->getFrequencyUnit($record['Frequency Unit']);
    $customData['wc_sdd_start_date'] = date('Ymd', strtotime($record['Start Date']));
    if (!empty($record['End Date'])) {
      $customData['wc_sdd_end_date'] = date('Ymd', strtotime($record['End Date']));
    }
    return $customData;
  }
}