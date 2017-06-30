<?php
/*-------------------------------------------------------------+
| GP StreetImporter Record Handlers                            |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * Abstract class bundle common GP importer functions
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
class CRM_Streetimport_GP_Handler_DDRecordHandler extends CRM_Streetimport_GP_Handler_GPRecordHandler {

  /** file name pattern as used by TM company */
  protected static $DD_PATTERN = '#(?P<org>[a-zA-Z\-]+)_(?P<agency>\w+)_(?P<date>\d{8})[.](csv|CSV)$#';

  /** stores the parsed file name */
  protected $file_name_data = 'not parsed';

  /**
   * Check if the given handler implementation can process the record
   *
   * @param $record  an array of key=>value pairs
   * @return true or false
   */
  public function canProcessRecord($record, $sourceURI) {
    if ($this->file_name_data === 'not parsed') {
      $this->file_name_data = $this->parseDdFile($sourceURI);
    }
    return $this->file_name_data != NULL;
  }

  /**
   * process the given record
   *
   * @param $record  an array of key=>value pairs
   * @return true
   * @throws exception if failed
   */
  public function processRecord($record, $sourceURI) {
    $config = CRM_Streetimport_Config::singleton();

    // STEP 1: match/create contact
    $contact_id = $this->processContact($record);

    // STEP 2: process Features and stuff
    $this->processAdditionalInformation($contact_id, $record);

    // STEP 3: create a new contract for the contact
    $this->createContract($contact_id, $record);

    // STEP 4: create 'manual check' activity
    $note = trim(CRM_Utils_Array::value('Bemerkungen', $record, ''));
    if ($note) {
      $this->createManualUpdateActivity($contact_id, $note, $record);
    }

    $deprecated_start_date = trim(CRM_Utils_Array::value('Vertrags_Beginn', $record, ''));
    if ($deprecated_start_date) {
      $this->createManualUpdateActivity($contact_id, "Deprecated value 'Vertrags_Beginn' given: {$deprecated_start_date}", $record);
    }
  }


  /**
   * Create/Find the contact and make sure no base data is lost
   */
  protected function processContact($record) {
    // compile contact data
    $contact_data = array(
      'formal_title'   => CRM_Utils_Array::value('Titel', $record),
      'first_name'     => CRM_Utils_Array::value('Vorname', $record),
      'last_name'      => CRM_Utils_Array::value('Nachname', $record),
      'prefix_id'      => CRM_Utils_Array::value('Anrede', $record),
      'birth_date'     => CRM_Utils_Array::value('Geburtsdatum', $record),
      'postal_code'    => CRM_Utils_Array::value('PLZ', $record),
      'city'           => CRM_Utils_Array::value('Ort', $record),
      'street_address' => trim(CRM_Utils_Array::value('Straße', $record, '') . ' ' . CRM_Utils_Array::value('HNR', $record, '')),
      'email'          => CRM_Utils_Array::value('Email', $record),
    );

    // postprocess contact data
    $this->resolveFields($contact_data, $record);

    // and match using XCM
    $contact_match = civicrm_api3('Contact', 'getorcreate', $contact_data);
    $contact_id = $contact_match['id'];

    // make sure the extra fields are stored
    $this->addDetail($record, $contact_id, 'Email', array('email' => $record['email']));

    // ...including the phones
    if (!empty(CRM_Utils_Array::value('Telefon', $record))) {
      $phone = '+' . CRM_Utils_Array::value('Telefon', $record);
      $this->addDetail($record, $contact_id, 'Phone', array('phone' => $phone), FALSE, array('phone_type_id' => 1));
    }
    if (!empty(CRM_Utils_Array::value('Mobilnummer', $record))) {
      $phone = '+' . CRM_Utils_Array::value('Mobilnummer', $record);
      $this->addDetail($record, $contact_id, 'Phone', array('phone' => $phone), FALSE, array('phone_type_id' => 2));
    }
  }

  /**
   * Process the additional information for the contact:
   *  groups, tags, contact restrictions, etc.
   */
  protected function processAdditionalInformation($contact_id, $record) {
    // TODO:
    // Informationen_elektronisch
    // Nicht_Kontaktieren
    // Interesse1
    // Interesse2
    // Leistung1
    // Leistung2
  }

  /**
   * Create a new contract for this contact
   */
  protected function createContract($contact_id, $record) {
    //  ---------------------------------------------
    // |           CREATE MANDATE                    |
    //  ---------------------------------------------
    $mandate_data = array(
      'iban'               => CRM_Utils_Array::value('IBAN', $record),
      'bic'                => CRM_Utils_Array::value('BIC', $record),
      'start_date'         => CRM_Utils_Array::value('Vertrags_Beginn', $record),
      'amount'             => CRM_Utils_Array::value('MG_Beitrag_pro_Jahr', $record),
      'frequency_interval' => $this->getFrequency(CRM_Utils_Array::value('Zahlungszeitraum', $record)),
      'frequency_unit'     => 'month',
      'contact_id'         => $contact_id,
      'currency'           => 'EUR',
      'type'               => 'RCUR',
      'campaign_id'        => $this->getCampaignID($record),
    );

    // process/adjust data:
    //  - calculate amount/frequency
    $amount = number_format($mandate_data['amount'] / $mandate_data['frequency_interval'], 2, '.', '');
    if ($amount * $mandate_data['frequency_interval'] != $mandate_data['amount']) {
      // this is a bad contract amount for the interval
      $frequency = CRM_Utils_Array::value('Vertrags_Beginn', $record);
      $this->logger->logError("Contract annual amount '{$mandate_data['amount']}' not divisiable by frequency {$frequency}.", $record);
    }
    $mandate_data['amount'] = $amount;

    //  - parse start date
    $mandate_data['start_date'] = date('YmdHis', strtotime($mandate_data['start_date']));
    $mandate_data['cycle_day']  = $config->getNextCycleDay($mandate_data['start_date']);

    // create mandate
    $mandate = civicrm_api3('SepaMandate', 'createfull', $mandate_data);
    $mandate = civicrm_api3('SepaMandate', 'getsingle', array('id' => $mandate['id']));


    //  ---------------------------------------------
    // |           CREATE MEMBERSHIP                 |
    //  ---------------------------------------------
    $contract_data = array(
      'membership_type_id'                                   => $this->getMembershipType($record),
      'start_date'                                           => CRM_Utils_Array::value('Aufnahmedatum', $record),
      'membership_general.membership_channel'                => CRM_Utils_Array::value('Kontaktart', $record),
      'membership_general.membership_contract'               => CRM_Utils_Array::value('MG_NR_Formular', $record),
      'membership_general.membership_dialoger'               => $this->getDialoger($record),
      'membership_payment.membership_recurring_contribution' => $mandate['entity_id'],
      'membership_payment.from_ba'                           => CRM_Contract_BankingLogic::getOrCreateBankAccount($contact_id, $record['IBAN'], $record['BIC']),
      'membership_payment.to_ba'                             => CRM_Contract_BankingLogic::getCreditorBankAccount(),
    );

    // process/adjust data:
    $contract_data['start_date'] = date('YmdHis', strtotime($contract_data['start_date']));

    // TODO:
    // $this->getFrequency
    // $this->getMembershipType
    // $this->getDialoger
    // create

  }


  //  ---------------------------------------------
  // |            Helper Functions                 |
  //  ---------------------------------------------

  /**
   * Will try to parse the given name and extract the parameters outlined in TM_PATTERN
   *
   * @return NULL if not matched, data else
   */
  protected function parseDdFile($sourceID) {
    if (preg_match(self::$DD_PATTERN, $sourceID, $matches)) {
      return $matches;
    } else {
      return NULL;
    }
  }

  /**
   * Checks if this record uses IMB or CiviCRM IDs
   */
  protected function getCampaignID($record) {
    switch ($this->file_name_data['agency']) {
      case 'GP':
        return $this->getCampaignIDbyExternalIdentifier('AKTION-7874');
        break;

      case 'DDI':
        return $this->getCampaignIDbyExternalIdentifier('AKTION-7875');
        break;

      case 'WS':
        return $this->getCampaignIDbyExternalIdentifier('AKTION-7876');
        break;

      default:
        $this->logger->logFatal("Unknown agency '{$this->file_name_data['agency']}'. Processing stopped.", $record);
        return;
    }
  }
}
