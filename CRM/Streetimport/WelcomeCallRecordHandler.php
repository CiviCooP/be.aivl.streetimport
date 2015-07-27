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
    $this->logger->logDebug($config->translate("Processing WelcomeCall record..."), $record);

    // STEP 1: lookup recruiting organisation
    $recruiting_organisation = $this->getRecruitingOrganisation($record);

    // STEP 2: look up / create recruiter
    $recruiter = $this->processRecruiter($record, $recruiting_organisation);

    // STEP 3: look up / create donor
    $donor = $this->getDonorWithExternalId($record['DonorID'], $recruiting_organisation['id'], $record);
    if (empty($donor)) {
      $this->logger->logError("Donor ".$record['DonorID']." ".$config->translate("should already exist. Created new contact in order to process record anyway."), $record);
      $donor = $this->processDonor($record, $recruiting_organisation);
    } else {
      $this->logger->logDebug($config->translate("Donor [{$donor['id']}] identified."), $record);
    }

    // STEP 5: create activity "WelcomeCall"
    $createdActivity = $this->createActivity(array(
      'activity_type_id'   => $config->getWelcomeCallActivityType(),
      'subject'            => $config->translate("Welcome Call"),
      'status_id'          => $config->getWelcomeCallActivityStatusId(),
      'activity_date_time' => date("Ymdhis", strtotime(CRM_Streetimport_Utils::formatCsvDate($record['Recruitment Date']))),
      'target_contact_id'  => (int) $donor['id'],
      'source_contact_id'  => $recruiter['id'],
      'campaign_id'        => $this->getCampaignParameter($record),
      //'assignee_contact_id'=> $recruiter['id'],
      'details'            => $this->renderTemplate('activities/WelcomeCall.tpl', $record),
    ), $record);
    // add custom data to the created activity
    $this->createActivityCustomData($createdActivity->id, $config->getWelcomeCallCustomGroup('table_name'), $this->buildActivityCustomData($record), $record);

    // STEP 6: update SEPA mandate if required
    $this->processMandate($record, $donor['id']);

    // STEP 7: add to newsletter group if requested
    if ($this->isTrue($record, "Newsletter")) {
      $newsletter_group_id = $config->getNewsletterGroupID();
      $this->addContactToGroup($donor['id'], $newsletter_group_id, $record);
    }

    // STEP 8: CHECK membership 
    if ($this->isTrue($record, "Member")) {
      // check if membership exists
      $membership_data = array(
        'contact_id'         => $donor['id'],
        'membership_type_id' => $config->getMembershipTypeID(),
      );
      $existing_memberships = civicrm_api3('Membership', 'get', $membership_data);
      if ($existing_memberships['count'] == 0) {
        // the contact has no membership yet, create (see https://github.com/CiviCooP/be.aivl.streetimport/issues/49)
        $membership_data['membership_source'] = $config->translate('Activity').' '.$config->translate('Welcome Call').' '.$createdActivity->id;
        $this->createMembership($membership_data, $record);
      }
    }

    // STEP 9: create activity 'Opvolgingsgesprek' if requested
    if ($this->isTrue($record, "Follow Up Call")) {
      $this->createActivity(array(
        'activity_type_id'   => $config->getFollowUpCallActivityType(),
        'subject'            => $config->translate("Follow Up Call from")." ".$config->translate('Welcome Call'),
        'status_id'          => $config->getFollowUpCallActivityStatusId(),
        'activity_date_time' => date('YmdHis', strtotime("+1 day")),
        'target_contact_id'  => (int) $donor['id'],
        'source_contact_id'  => $recruiter['id'],
        'assignee_contact_id'=> $config->getFundraiserContactID(),
        'campaign_id'        => $this->getCampaignParameter($record),
        'details'            => $this->renderTemplate('activities/FollowUpCall.tpl', $record),
      ), $record);
    }

    // DONE
    $this->logger->logImport($record, true, $config->translate('WelcomeCall'));
  }


  /**
   * process SDD mandate
   */
  protected function processMandate($record, $donor_id) {
    $config = CRM_Streetimport_Config::singleton();
    $now = strtotime("now");

    // first, extract the new mandate information
    $new_mandate_data = $this->extractMandate($record, $donor_id);

    // then load the existing mandate
    try {
      $old_mandate_data = civicrm_api3('SepaMandate', 'getsingle', array('reference' => $new_mandate_data['reference']));    
      if (!isset($old_mandate_data['end_date'])) {
        $old_mandate_data['end_date'] = '';
      }
    } catch (Exception $e) {
      $this->logger->logError(sprintf($config->translate("SDD mandate '%s' count not be found."), $record, $new_mandate_data['reference']));
      return NULL;
    }

    // if this is a cancellation, nothing else matters:
    $acceptedYesValues = $config->getAcceptedYesValues();
    if (in_array($record['Cancellation'], $acceptedYesValues)) {
        CRM_Sepa_BAO_SEPAMandate::terminateMandate( $old_mandate_data['id'],
                                                    date('YmdHis', strtotime("today")),
                                                    $cancel_reason=$config->translate("Cancelled after welcome call."));
      return;
    }
    
    // ...and the attached contribution
    try {
      if ($old_mandate_data['entity_table']=='civicrm_contribution_recur') {
        $old_contribution = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $old_mandate_data['entity_id']));
      } elseif ($old_mandate_data['type']=='civicrm_contribution') {
        $old_contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $old_mandate_data['entity_id']));
        $old_contribution['amount'] = $old_contribution['total_amount'];
      } else {
        $config= CRM_Streetimport_Config::singleton();
        $this->logger->abort($config->translate("Bad SDD mandate type found. Contact developer"));
        return NULL;
      }
    } catch (Exception $e) {
      $this->logger->logError($config->translate("Couldn't load contribution entity for mandate").' '.$old_mandate_data['id'], $record);
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
      if (  $old_contribution['start_date'] > $now 
         || $new_mandate_data['start_date'] > $now ) {
        unset($mandate_diff['start_date']);
      }
    }

    // filter the changes, some can be safely ignored
    $ignore_changes_for = array('creation_date', 'contact_id');
    foreach ($ignore_changes_for as $field) unset($mandate_diff[$field]);
    // TODO: can we really ignore changes for contact_id? 
    //  => this should only happen if the donor ID lookup failed...

    if (empty($mandate_diff)) {
      $this->logger->logDebug($config->translate("No SDD mandate update required"), $record);
      return;
    }

    // if only the attributes amount and/or end_date have changed
    $require_new_mandate = $mandate_diff;
    unset($require_new_mandate['amount']);
    unset($require_new_mandate['end_date']);
    unset($require_new_mandate['date']);
    unset($require_new_mandate['validation_date']);

    if (empty($require_new_mandate)) {
      // CHANGES ONLY TO end_date and/or amount
      if (!empty($mandate_diff['amount'])) {
        CRM_Sepa_BAO_SEPAMandate::adjustAmount(     $old_mandate_data['id'], 
                                                    $new_mandate_data['amount']);
      }

      if (!empty($mandate_diff['end_date'])) {
        CRM_Sepa_BAO_SEPAMandate::terminateMandate( $old_mandate_data['id'], 
                                                    $new_mandate_data['end_date'], 
                                                    $cancel_reason=$config->translate("Update via welcome call."));
      }

      if (!empty($mandate_diff['validation_date'])) {
        // update validation date
        $new_validation_date = strtotime($new_mandate_data['validation_date']);
        $new_signature_date  = strtotime($new_mandate_data['date']);
        civicrm_api3('SepaMandate', 'create', array( 
                  'id'              => $old_mandate_data['id'], 
                  'validation_date' => date("YmdHis", $new_validation_date),
                  'date'            => date("YmdHis", $new_signature_date),
                  ));
      }

    } else {
      // MORE/OTHER CHANGES -> create new mandte
      // step 1: find new reference (append letters)
      for ($suffix=ord('a'); $suffix < ord('z'); $suffix++) {
        $new_reference_number = $new_mandate_data['reference'] . chr($suffix);
        $count_query = civicrm_api3('SepaMandate', 'getcount', array('reference' => $new_reference_number));
        if ($count_query['result'] == 0) {
          break;
        } else {
          $new_reference_number = NULL; // this number is in use
        }
      }
      if (empty($new_reference_number)) {
        $this->logger->logError(sprintf($config->translate("Couldn't create reference for amended mandate '%s'."), $new_mandate_data['reference']), $record);
        return;
      }

      // step 2: create new mandate
      $new_mandate_data['reference'] = $new_reference_number;
      $new_mandate = $this->createSDDMandate($new_mandate_data, $record);
      
      // step 3: stop old mandate
      $cancel_date = $now;
      if (!empty($new_mandate_data['end_date'])) {
        $cancel_date = strtotime($new_mandate_data['end_date']);
        $cancel_date = max($now, $cancel_date);
      }
      $cancel_date_str = date('Y-m-d');
      CRM_Sepa_BAO_SEPAMandate::terminateMandate( $old_mandate_data['id'], 
                                                  $cancel_date_str, 
                                                  $cancel_reason=sprintf($config->translate("Replaced with '%s' due to welcome call."), $new_reference_number));

      // step 4: save bank account if it has changed:
      if (!empty($mandate_diff['iban']) || !empty($mandate_diff['bic'])) {
        $this->saveBankAccount($new_mandate_data, $record);
      }

      return $new_mandate;
    }
  }

  /**
   * Method to build data for custom group welcome call
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