<?php
/**
 * This class can process records of type 'welcome call'
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
class CRM_Streetimport_WelcomeCallRecordHandler extends CRM_Streetimport_StreetimportRecordHandler {

  private $_replaceCausedByField = NULL;

  /** 
   * Check if the given handler implementation can process the record
   *
   * @param $record array of key=>value pairs
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
    $this->logger->logDebug($config->translate("Processing WelcomeCall record")."...", $record);

    // STEP 1: lookup recruiting organisation
    $recruiting_organisation = $this->getRecruitingOrganisation($record);

    // STEP 2: look up / create recruiter
    $recruiter = $this->processRecruiter($record, $recruiting_organisation);

    // STEP 3: look up / create donor
    $donor = $this->processDonor($record, $recruiting_organisation);
    if (empty($donor)) {
      $this->logger->logError("Donor ".$record['DonorID']." ".$config->translate("should already exist. Created new contact in order to process record anyway."), $record);
      $donor = $this->processDonor($record, $recruiting_organisation);
    } else {
      $this->logger->logDebug($config->translate("Donor [{$donor['id']}] identified."), $record);
    }

    // STEP 4: issue 86 do not process welcome call if no street recruitment for the contact
    if (!$this->donorHasActivity($donor, 'StreetRecruitment')) {
      $this->logger->logError("Donor ".$record['DonorID']." /CiviCRM contact id "
        .$donor['id']." ".$config->translate("and name")." ".$donor['sort_name']." "
        .$config->translate("has no Street Recruitment activity.")." "
        .$config->translate("Line in import file for Welcome Call ignored."), $record,
        $config->translate("No previous Street Recruitment when loading WelcomeCall"), "Error");
    } else {
      // STEP 5: issue 264: if contact already has a welcome call do not process
      if ($this->donorHasActivity($donor, 'WelcomeCall')) {
        $this->logger->logError("Donor " . $record['DonorID'] . " /CiviCRM contact id "
          . $donor['id'] . " " . $config->translate("and name") . " " . $donor['sort_name'] . " "
          . $config->translate("already has a WelcomeCall, no further processing for error line.") . " "
          . $config->translate("Line in import file for Welcome Call ignored."), $record,
          $config->translate("Already has WelcomeCall"), "Info");
      } else {
        // STEP 6: create activity "WelcomeCall"
        $campaignId = $this->getCampaignParameter($record);

        $welcomeCallActvityType = $config->getWelcomeCallActivityType();
        $concatActivitySubject = $this->concatActivitySubject("Welcome Call", $campaignId);
        $welcomeCallActivityStatusId = $config->getWelcomeCallActivityStatusId();
        $activityDateTime = date("Ymdhis", strtotime(CRM_Streetimport_Utils::formatCsvDate($record['Recruitment Date'])));
        $activityDetails = CRM_Streetimport_Utils::renderTemplate('activities/WelcomeCall.tpl', $record);
        
        $createdActivity = $this->createActivity(array(
          'activity_type_id' => $welcomeCallActvityType,
          'subject' => $concatActivitySubject,
          'status_id' => $welcomeCallActivityStatusId,
          'activity_date_time' => $activityDateTime,
          'location' => $record['Recruitment Location'],
          'target_contact_id' => (int)$donor['id'],
          'source_contact_id' => $recruiter['id'],
          'campaign_id' => $campaignId,
          //'assignee_contact_id'=> $recruiter['id'],
          'details' => $activityDetails,
        ), $record);
        // add custom data to the created activity
        $this->createActivityCustomData($createdActivity->id, $config->getWelcomeCallCustomGroup('table_name'), $this->buildActivityCustomData($record), $record);

        // STEP 7: update SEPA mandate if required
        $this->processMandate($record, $donor['id']);

        // STEP 8: add to newsletter group if requested
        if ($this->isTrue($record, "Newsletter")) {
          $newsletter_group_id = $config->getNewsletterGroupID();
          $this->addContactToGroup($donor['id'], $newsletter_group_id, $record);
        }

        // STEP 9: CHECK membership
        if ($this->isTrue($record, "Member")) {
          // check if membership exists
          $membership_data = array(
            'contact_id' => $donor['id'],
            'membership_type_id' => $config->getMembershipTypeID(),
          );
          $existing_memberships = civicrm_api3('Membership', 'get', $membership_data);
          if ($existing_memberships['count'] == 0) {
            // the contact has no membership yet, create (see https://github.com/CiviCooP/be.aivl.streetimport/issues/49)
            $membership_data['membership_source'] = $config->translate('Activity') . ' ' . $config->translate('Welcome Call') . ' ' . $createdActivity->id;
            $this->createMembership($membership_data, $recruiter['id'], $record);
          }
        }

        // STEP 10: create activity 'Opvolgingsgesprek' if requested
        if ($this->isTrue($record, "Follow Up Call")) {
          $followUpDateTime = date('YmdHis', strtotime("+" . $config->getFollowUpOffsetDays() . " day"));
          $followUpActivityType = $config->getFollowUpCallActivityType();
          $followUpSubject = $config->translate("Follow Up Call from") . " " . $config->translate('Welcome Call');
          $followUpActivityStatusId = $config->getFollowUpCallActivityStatusId();
          $fundRaiserId = $config->getFundraiserContactID();
          
          $this->createActivity(array(
            'activity_type_id' => $followUpActivityType,
            'subject' => $followUpSubject,
            'status_id' => $followUpActivityStatusId,
            'activity_date_time' => $followUpDateTime,
            'target_contact_id' => (int)$donor['id'],
            'source_contact_id' => $recruiter['id'],
            'assignee_contact_id' => $fundRaiserId,
            'campaign_id' => $campaignId,
            'details' => CRM_Streetimport_Utils::renderTemplate('activities/FollowUpCall.tpl', $record),
          ), $record);
        }
      }

      // DONE
      $this->logger->logImport($record, true, $config->translate('WelcomeCall'));
    }
  }

  /**
   * process SDD mandate
   *
   * @param array $record
   * @param int $donorId
   * @return mixed
   */
  protected function processMandate($record, $donorId) {
    $config = CRM_Streetimport_Config::singleton();
    // first, extract the new mandate information
    $newMandateData = $this->extractMandate($record, $donorId);
    $oldMandateData = $this->getOldMandateData($newMandateData['reference'], $record, $donorId);
    // if this is a cancellation, nothing else matters:
    $acceptedYesValues = $config->getAcceptedYesValues();
    if (in_array($record['Cancellation'], $acceptedYesValues)) {
      CRM_Sepa_BAO_SEPAMandate::terminateMandate($oldMandateData['id'], date('YmdHis', strtotime("today")),
        $cancelReason = $config->translate("Cancelled after welcome call."));
      return NULL;
    }
    // if any changes to campaign, update recurring contribution (issue 1139) <https://civicoop.plan.io/issues/1139>
    if (isset($newMandateData['campaign_id'])) {
      $this->changeCampaign($oldMandateData, $newMandateData, $record);
    }
    else {
      if (isset($oldMandateData['campaign_id'])) {
        $newMandateData['campaign_id'] = $oldMandateData['campaign_id'];
      }
    }
    // if OOFF just process changes
    if ($oldMandateData['type'] == ' OOFF') {
      $this->processOOFFChanges($newMandateData, $oldMandateData, $record);
    }
    else {
      if ($this->requiresNewMandate($newMandateData, $oldMandateData) == FALSE) {
        $this->processRCURChanges($newMandateData, $oldMandateData, $record);
      }
      else {
        $this->replaceMandate($oldMandateData, $newMandateData, $record);
      }
    }
    return TRUE;
  }

  /**
   * Method to replace mandate with new one
   *
   * @param $oldMandateData
   * @param $newMandateData
   * @param $record
   * @return null
   * @throws CiviCRM_API3_Exception
   */
  private function replaceMandate($oldMandateData, $newMandateData, $record) {
    $config = CRM_Streetimport_Config::singleton();
    // step 1: find new reference (append letters)
    for ($suffix = ord('a'); $suffix < ord('z'); $suffix++) {
      $newReferenceNumber = $newMandateData['reference'] . chr($suffix);
      $countQuery = civicrm_api3('SepaMandate', 'getcount', array('reference' => $newReferenceNumber));
      if ($countQuery['result'] == 0) {
        break;
      } else {
        $newReferenceNumber = NULL; // this number is in use
      }
    }
    if (empty($newReferenceNumber)) {
      $this->logger->logError(sprintf($config->translate("Couldn't create reference for amended mandate '%s'."), $newMandateData['reference']), $record, "Error");
      return NULL;
    }

    // step 2: create new mandate
    $newMandateData['reference'] = $newReferenceNumber;
    $newMandate = $this->createSDDMandate($newMandateData, $record);

    // step 3: stop old mandate (if cancel date is in the past set to today)
    $cancelDate = new DateTime();
    if (!empty($newMandateData['end_date'])) {
      $endDate = new DateTime($newMandateData['end_date']);
      if ($endDate > $cancelDate) {
        $cancelDate = $endDate;
      }
    }
    $cancelReason = $config->translate('Replaced with') . ' ' . $newReferenceNumber . ' ' . $config->translate('due to the following change in the Welcome Call')
      . ': ' . $this->_replaceCausedByField;
    CRM_Sepa_BAO_SEPAMandate::terminateMandate( $oldMandateData['id'], $cancelDate->format('Ymd'), $cancelReason);

    // step 4: save bank account if it has changed:
    if ($oldMandateData['iban'] != $newMandateData['iban'] || $oldMandateData['bic'] != $newMandateData['bic']) {
      $this->saveBankAccount($newMandateData, $record);
    }
    return $newMandate;
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
    $frequencyUnit = $this->getFrequencyUnit($record['Frequency Unit']);
    $areasOfInterest = $this->getAreasOfInterest($record['Interests']);
    $customData = array();
    $customData['wc_date_import'] = array('value' => date('Ymd'), 'type' => 'Date');
    if (in_array($record['Follow Up Call'], $acceptedYesValues)) {
      $customData['wc_follow_up_call'] = array('value' => 1, 'type' => 'Integer');
    } else {
      $customData['wc_follow_up_call'] = array('value' => 0, 'type' => 'Integer');
    }
    if (in_array($record['Newsletter'], $acceptedYesValues)) {
      $customData['wc_newsletter'] = array('value' => 1, 'type' => 'Integer');
    } else {
      $customData['wc_newsletter'] = array('value' => 0, 'type' => 'Integer');
    }
    if (in_array($record['Member'], $acceptedYesValues)) {
      $customData['wc_member'] = array('value' => 1, 'type' => 'Integer');
    } else {
      $customData['wc_member'] = array('value' => 0, 'type' => 'Integer');
    }
    if (in_array($record['Cancellation'], $acceptedYesValues)) {
      $customData['wc_sdd_cancel'] = array('value' => 1, 'type' => 'Integer');
    } else {
      $customData['wc_sdd_cancel'] = array('value' => 0, 'type' => 'Integer');
    }
    $customData['wc_areas_interest'] = array('value' => $areasOfInterest, 'type' => 'String');
    $customData['wc_remarks'] = array('value' => $record['Notes'], 'type' => 'String');
    $customData['wc_sdd_mandate'] = array('value' => $record['Mandate Reference'], 'type' => 'String');
    $customData['wc_sdd_iban'] = array('value' => $record['IBAN'], 'type' => 'String');
    $customData['wc_sdd_bank_name'] = array('value' => $record['Bank Name'], 'type' => 'String');
    $customData['wc_sdd_bic'] = array('value' => $record['Bic'], 'type' => 'String');
    $fixedAmount = $this->fixImportedAmount($record['Amount']);
    $customData['wc_sdd_amount'] = array('value' => $fixedAmount, 'type' => 'Money');
    $customData['wc_sdd_freq_interval'] = array('value' => $record['Frequency Interval'], 'type' => 'Integer');
    $customData['wc_sdd_freq_unit'] = array('value' => $frequencyUnit, 'type' => 'Integer');
    if (!empty($record['Start Date'])) {
      $customData['wc_sdd_start_date'] = array('value' => date('Ymd', strtotime(CRM_Streetimport_Utils::formatCsvDate($record['Start Date']))), 'type' => 'Date');
    }
    if (!empty($record['End Date'])) {
      $customData['wc_sdd_end_date'] = array('value' => date('Ymd', strtotime(CRM_Streetimport_Utils::formatCsvDate($record['End Date']))), 'type' => 'Date');
    }
    return $customData;
  }

  /**
   * Method to check if the donor already has a street recruitment or welcome call activity
   *
   * @param $donor
   * @param $type
   * @return bool
   * @access protected
   */
  protected function donorHasActivity($donor, $type) {
    $config = CRM_Streetimport_Config::singleton();
    if ($type == "StreetRecruitment") {
      $activityTypeId = $config->getStreetRecruitmentActivityType('value');
    } else {
      $activityTypeId = $config->getWelcomeCallActivityType('value');
    }
    $query = "SELECT COUNT(*) as countActivities
      FROM civicrm_activity_contact a JOIN civicrm_activity b ON a.activity_id = b.id
      WHERE a.record_type_id = %1 and a.contact_id = %2 and b.is_current_revision = %3 and b.activity_type_id = %4
      AND b.is_test = %5 and b.is_deleted = %5";
    $params = array(
      1 => array(3, 'Integer'),
      2 => array($donor['id'], 'Integer'),
      3 => array(1, 'Integer'),
      4 => array($activityTypeId, 'Integer'),
      5 => array(0, 'Integer')
    );
    $countActivity = CRM_Core_DAO::singleValueQuery($query, $params);
    if ($countActivity > 0) {
      return TRUE;
    } else {
      return FALSE;
    }
  }

  /**
   * Method to update campaign on recurring contribution/contribution if required
   *
   * @param array $oldMandateData
   * @param array $newMandateData
   */
  protected function changeCampaign($oldMandateData, $newMandateData, $record) {
    $config = CRM_Streetimport_Config::singleton();
    if ($oldMandateData['type'] == 'OOFF') {
      $entity = 'Contribution';
      $returnValue = 'contribution_campaign_id';
    } else {
      $entity = 'ContributionRecur';
      $returnValue = 'campaign_id';
    }
    try {
      $oldCampaignId = civicrm_api3($entity, 'getvalue', array(
        'id' => $oldMandateData['entity_id'],
        'return' => $returnValue,
      ));
      if ($newMandateData['campaign_id'] != $oldCampaignId) {
        civicrm_api3($entity, 'create', array(
          'id' => $oldMandateData['entity_id'],
          'campaign_id' => $newMandateData['campaign_id'],
        ));
        $this->logger->logDebug($config->translate("Campaign changed from") . " " . $oldCampaignId . " "
          . $config->translate("to") . " " . $newMandateData['campaign_id'], $record);
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
      $this->logger->logError($config->translate("Couldn't find or update (recurring) contribution with campaign for mandate").' '
        .$newMandateData['reference'], $record, "Warning");
    }
  }

  /**
   * Method to determine if new mandate is required. This is the case if one of the values in the array forcesNewMandate is different
   *
   * @param array $newMandateData
   * @param array $oldMandateData
   * @return bool
   */
  private function requiresNewMandate($newMandateData, $oldMandateData) {
    $newFields = array(
      'iban',
      'frequency_interval',
      'frequency_unit',
    );
    // if campaign is empty, get campaign from the old one
    foreach ($newFields as $newField) {
      if ($oldMandateData[$newField] != $newMandateData[$newField]) {
        $this->_replaceCausedByField = $newField;
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Method to process mandate changes (also into recurring contributions
   *
   * @param $newMandateData
   * @param $oldMandataData
   * @param $record
   */
  private function processRCURChanges($newMandateData, $oldMandataData, $record) {
    $config = CRM_Streetimport_Config::singleton();
    // if amount changed, process into recurring contribution
    if ($newMandateData['amount'] != $oldMandataData['amount']) {
      CRM_Sepa_BAO_SEPAMandate::adjustAmount($oldMandataData['id'], $newMandateData['amount']);
    }
    // if end date set, terminate mandate per end date
    if (isset($newMandateData['end_date']) && !empty($newMandateData['end_date'])) {
      CRM_Sepa_BAO_SEPAMandate::terminateMandate($oldMandataData['id'], $newMandateData['end_date'], $config->translate('Update end date via welcome call.'));
    }
    // in other cases, update data with API
    $sepaParams = array();
    $recurParams = array();
    $recurFields = array(
      'cycle_day',
      'source',
      'start_date',
    );
    $sepaFields = array(
      'bic',
      'creation_date',
      'validation_date',
    );
    foreach ($sepaFields as $sepaField) {
      if ($newMandateData[$sepaField] != $oldMandataData[$sepaField]) {
        $sepaParams[$sepaField] = $newMandateData[$sepaField];
      }
    }
    foreach ($recurFields as $recurField) {
      if ($newMandateData[$recurField] != $oldMandataData[$recurField]) {
        $recurParams[$recurField] = $newMandateData[$recurField];
      }
    }
    if (!empty($sepaParams)) {
      $sepaParams['id'] = $oldMandataData['id'];
      $this->saveSepaChanges($sepaParams, $record, $newMandateData);
      // process bank account if bic changed
      if ($newMandateData['bic'] != $oldMandataData['bic']) {
        $this->saveBankAccount($newMandateData, $record);
      }
    }
    if (!empty($recurParams)) {
      $recurParams['id'] = $oldMandataData['entity_id'];
      $this->saveRecurChanges($recurParams, $record, $newMandateData);
    }
  }

  /**
   * Save changes to sepa mandate
   *
   * @param $sepaParams
   * @param $record
   * @param $newMandateData
   */
  private function saveSepaChanges($sepaParams, $record, $newMandateData) {
    $config = CRM_Streetimport_Config::singleton();
    try {
      civicrm_api3('SepaMandate', 'create', $sepaParams);
    }
    catch (CiviCRM_API3_Exception $ex) {
      $this->logger->logError($config->translate("Couldn't update mandate with reference") . ' '
        . $newMandateData['reference'], $record, "Error");
    }
  }

  /**
   * Save changes to recurring contribution
   *
   * @param $recurParams
   * @param $record
   * @param $newMandateData
   */
  private function saveRecurChanges($recurParams, $record, $newMandateData) {
    $config = CRM_Streetimport_Config::singleton();
    try {
      civicrm_api3('ContributionRecur', 'create', $recurParams);
    }
    catch (CiviCRM_API3_Exception $ex) {
      $this->logger->logError($config->translate("Couldn't update recurring contribution with id ")  . $recurParams['id']
        . $config->translate(" for mandate reference ") . " " . $newMandateData['reference'], $record, "Error");
    }
  }

  /**
   * Method to get old mandate data
   *
   * @param $reference
   * @param $record
   * @param $donorId
   * @return array
   */
  private function getOldMandateData($reference, $record, $donorId) {
    $config = CRM_Streetimport_Config::singleton();
    try {
      $oldMandateData = civicrm_api3('SepaMandate', 'getsingle', array('reference' => $reference));
      if (!isset($oldMandateData['end_date'])) {
        $oldMandateData['end_date'] = '';
      }
      // add either contribution or recurring contribution data
      switch ($oldMandateData['entity_table']) {
        case 'civicrm_contribution_recur':
          $this->addRecurringData($oldMandateData, $record);
          break;

        case 'civicrm_contribution':
          $this->addContributionData($oldMandateData, $record);
          break;
      }
      return $oldMandateData;
    } catch (CiviCRM_API3_Exception $ex) {
      $this->logger->logError($config->translate("SDD mandate") . " " . $reference . " " . $config->translate("not found for donor") .
        " " . $donorId . ". " . $config->translate("Mandate not updated at Welcome Call for") . " " . $record['First Name'] .
        " " . $record['Last Name'], $record, $config->translate("SDD Mandate not found"), "Error");
      return array();
    }
  }

  /**
   * Method to retrieve the recurring contribution data for mandate (if RCUR)
   *
   * @param array $oldMandateData
   * @param array $record
   */
  private function addRecurringData(&$oldMandateData, $record) {
    $config = CRM_Streetimport_Config::singleton();
    try {
      $recurring = civicrm_api3('ContributionRecur', 'getsingle', array('id' => $oldMandateData['entity_id']));
      if (isset($recurring['frequency_unit'])) {
        $oldMandateData['frequency_unit'] = $recurring['frequency_unit'];
      }
      if (isset($recurring['frequency_interval'])) {
        $oldMandateData['frequency_interval'] = $recurring['frequency_interval'];
      }
      if (isset($recurring['cycle_day'])) {
        $oldMandateData['cycle_day'] = $recurring['cycle_day'];
      }
      if (isset($recurring['amount'])) {
        $oldMandateData['amount'] = $recurring['amount'];
      }
      if (isset($recurring['campaign_id'])) {
        $oldMandateData['campaign_id'] = $recurring['campaign_id'];
      }
      if (isset($recurring['financial_type_id'])) {
        $oldMandateData['financial_type_id'] = $recurring['financial_type_id'];
      }
      if (isset($recurring['start_date'])) {
        $oldMandateData['start_date'] = $recurring['start_date'];
      }
      if (isset($recurring['end_date'])) {
        $oldMandateData['end_date'] = $recurring['end_date'];
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
      $this->logger->logError($config->translate('Could not find a recurring contribution for mandate ' . $oldMandateData['reference']),
        $record, $config->translate('No recurring contribution found', ' Warning'));
    }

  }

  /**
   * Method to get the contribution for the mandate (if OOFF)
   *
   * @param array $oldMandateData
   * @param array $record
   */
  private function addContributionData(&$oldMandateData, $record) {
    $config = CRM_Streetimport_Config::singleton();
    try {
      $contribution = civicrm_api3('Contribution', 'getsingle', array('id' => $oldMandateData['entity_id']));
      if (isset($contribution['financial_type_id'])) {
        $oldMandateData['financial_type_id'] = $contribution['financial_type_id'];
      }
      if (isset($contribution['total_amount'])) {
        $oldMandateData['amount'] = $contribution['total_amount'];
      }
      if (isset($contribution['campaign_id'])) {
        $oldMandateData['campaign_id'] = $contribution['campaign_id'];
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
      $this->logger->logError($config->translate('Could not find a (one off) contribution for mandate ' . $oldMandateData['reference']),
        $record, $config->translate('No (one off) contribution found', ' Warning'));
    }
  }
}