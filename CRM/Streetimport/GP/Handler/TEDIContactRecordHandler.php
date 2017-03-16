<?php
/*-------------------------------------------------------------+
| GP StreetImporter Record Handlers                            |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/


/**
 * GP TEDI Handler
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
class CRM_Streetimport_GP_Handler_TEDIContactRecordHandler extends CRM_Streetimport_GP_Handler_TMRecordHandler {

  /**
   * Check if the given handler implementation can process the record
   *
   * @param $record  an array of key=>value pairs
   * @return true or false
   */
  public function canProcessRecord($record, $sourceURI) {
    $parsedFileName = $this->parseTmFile($sourceURI);
    return ($parsedFileName && $parsedFileName['file_type'] == 'Kontakte' && $parsedFileName['tm_company'] == 'tedi');
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
    $file_name_data = $this->parseTmFile($sourceURI);

    $contact_id = $this->getContactID($record);
    if (empty($contact_id)) {
      return $this->logger->logError("Contact [{$record['id']}] couldn't be identified.", $record);
    }

    // apply contact base data updates if provided
    // FIELDS: nachname  vorname firma TitelAkademisch TitelAdel TitelAmt  Anrede  geburtsdatum  geburtsjahr strasse hausnummer  hausnummernzusatz PLZ Ort email
    $this->performContactBaseUpdates($contact_id, $record, $file_name_data);

    // Sign up for newsletter
    // FIELDS: emailNewsletter
    if ($this->isTrue($record, 'emailNewsletter')) {
      $newsletter_group_id = $config->getNewsletterGroupID();
      $this->addContactToGroup($contact_id, $newsletter_group_id, $record);
    }

    // Create / update contract
    // FIELDS: Vertragsnummer  Bankleitzahl  Kontonummer Bic Iban  Kontoinhaber  Bankinstitut  Einzugsstart  JahresBetrag  BuchungsBetrag  Einzugsintervall  EinzugsEndeDatum
    if (empty($record['Vertragsnummer'])) {
      $this->createContract($contact_id, $record, $file_name_data);
    } else {
      $this->updateContract($record['Vertragsnummer'], $contact_id, $record, $file_name_data);
    }

    // Add a note if requested
    // FIELDS: BemerkungFreitext
    if (!empty($record['BemerkungFreitext'])) {
      $this->createNote($contact_id, $config->translate('TM Import Note'), $record['BemerkungFreitext'], $record);
    }

    // If "X" then set  "rts_counter" in table "civicrm_value_address_statistics"  to "0"
    // FIELDS: AdresseGeprueft
    if ($this->isTrue($record, 'AdresseGeprueft')) {
      $this->addressValidated($contact_id, $record);
    }

    // TODO: Internal selectionid in IMB which is used to connect this row to the correct activityentry
    // FIELDS: Selektionshistoryid, zielgruppeid

    // weiterbuchen
    // This field kann take "0", "1" and "".
    //    "1" => no break (normal data entry for contract data and Sepa DD issue date will be set as soon as possible;
    //    "0" => break! (If there is a debit planned inbetween of date in field AA (Einzugsstart) and import date) the contract shall be paused and NOT debited asap.
    //    ""  => nothing happens

    // Ergebnisnummer  ErgebnisText
    // result response as sketched in doc "20140331_Telefonie_Response"

    // FIELDS: Bemerkung1  Bemerkung2  Bemerkung3  Bemerkung4  Bemerkung5
    // notes can trigger certain actions within Civi as mentioned in doc "20131107_Responses_Bemerkungen_1-5"

    $this->logger->logImport($record, true, $config->translate('TM Contact'));
  }

  /**
   * Extracts the specific activity date for this line
   */
  protected function getActivityDate($record, $file_name_data) {
    return date('Y-m-d', strtotime($record['TagDerTelefonie']));
  }

  /**
   * apply contact base date updates (if present in the data)
   * FIELDS: nachname  vorname firma TitelAkademisch TitelAdel TitelAmt  Anrede  geburtsdatum  geburtsjahr strasse hausnummer  hausnummernzusatz PLZ Ort email
   */
  public function performContactBaseUpdates($contact_id, $record, $file_name_data) {
    // TODO: implement
  }

  /**
   * create a new contract
   *
   * FIELDS: Vertragsnummer  Bankleitzahl  Kontonummer Bic Iban  Kontoinhaber  Bankinstitut  Einzugsstart  JahresBetrag  BuchungsBetrag  Einzugsintervall  EinzugsEndeDatum
   */
  public function createContract($contact_id, $record, $file_name_data) {
    // TODO: implement
  }

  /**
   * update an existing contract:
   * If contractId is set, then all changes in column U-AE are related to this contractID.
   * In conversion projects you will find no contractid here, which means you have to create a new one,
   * if the response in field AM/AN is positive and there is data in columns U-AE.
   *
   * FIELDS: Vertragsnummer  Bankleitzahl  Kontonummer Bic Iban  Kontoinhaber  Bankinstitut  Einzugsstart  JahresBetrag  BuchungsBetrag  Einzugsintervall  EinzugsEndeDatum
   */
  public function updateContract($contract_id, $contact_id, $record, $file_name_data) {
    // TODO: implement
  }

  /**
   * mark the given address as valid by resetting the RTS counter
   */
  public function addressValidated($contact_id, $record) {
    // TODO: implement
  }

}
