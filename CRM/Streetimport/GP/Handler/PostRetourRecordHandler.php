<?php
/*-------------------------------------------------------------+
| GP StreetImporter Record Handlers                            |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * Processes PostRetour barcode lists (GP-331)
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
class CRM_Streetimport_GP_Handler_PostRetourRecordHandler extends CRM_Streetimport_GP_Handler_GPRecordHandler {

  /** file name / reference patterns as defined in GP-331 */
  protected static $FILENAME_PATTERN      = '#^Postret_(?P<category>[a-zA-Z\-]+)_(?P<date>\d{8})[.](csv|CSV)$#';
  protected static $REFERENCE_PATTERN_NEW = '#^(?P<campaign_id>[0-9]{4})C(?P<contact_id>[0-9]{9})$#';
  protected static $REFERENCE_PATTERN_OLD = '#^(?P<campaign_id>[0-9]{4})(?P<campaign_id>[0-9]{6-9})$#';

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
      $this->file_name_data = $this->parseRetourFile($sourceURI);
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

    switch ($this->getCategory()) {
      case 'Abgabestelle unbenutzt':
        // TODO: implement
        break;

      case 'Anschrift ungenügend':
        // TODO: implement
        break;

      case 'Falsche PLZ':
        // TODO: implement
        break;

      case 'Nicht angenommen':
        // TODO: implement
        break;

      case 'Nicht behoben':
        // TODO: implement
        break;

      case 'Sonstiges':
        // TODO: implement
        break;

      case 'Unbekannt':
        // TODO: implement
        break;

      case 'Verstorben':
        // TODO: implement
        break;

      case 'Verzogen':
        // TODO: implement
        break;

      default:
        // TODO: implement
        break;
    }

    $this->logger->logImport($record, true, $config->translate('DD Contact'));
  }


  /**
   * Will try to parse the given name and extract the parameters outlined in TM_PATTERN
   *
   * @return NULL if not matched, data else
   */
  protected function parseRetourFile($sourceID) {
    if (preg_match(self::$FILENAME_PATTERN, $sourceID, $matches)) {
      return $matches;
    } else {
      return NULL;
    }
  }

  /**
   * get the reference
   */
  protected function getReference($record) {
    $reference = trim(CRM_Utils_Array::value('Kundennummer', $record, ''));
    if (empty($reference)) {
      $this->logger->logError("Couldn't find 'Kundennummer'.", $record);
    }
    return $reference;
  }

  /**
   * Extract the campaign ID from the Kundennummer
   */
  protected function getCampaignID($record) {
    $reference = $this->getReference();
    if (preg_match(self::$REFERENCE_PATTERN_NEW, $reference, $matches)) {
      return $matches['campaign_id'];
    } elseif (preg_match(self::$REFERENCE_PATTERN_OLD, $reference, $matches)) {
      // look up campaign
      $campaign = civicrm_api3('Campaign', 'get', array(
        'external_identifier' => "AKTION-{$matches['campaign_id']}",
        'return'              => 'id'));
      if (!empty($campaign['id'])) {
        return $campaign['id'];
      } else {
        $this->logger->logError("Couldn't find campaign 'AKTION-{$matches['campaign_id']}'.", $record);
        return NULL;
      }
    } else {
      $this->logger->logError("Couldn't parse reference '{$reference}'.", $record);
      return NULL;
    }
  }

  /**
   * Extract the contact ID from the Kundennummer
   */
  protected function getContactID($record) {
    $reference = $this->getReference();
    if (preg_match(self::$REFERENCE_PATTERN_NEW, $reference, $matches)) {
      // use identity tracker
      return $this->resolveContactID($contact_id, $record);

    } elseif (preg_match(self::$REFERENCE_PATTERN_OLD, $reference, $matches)) {
      // use identity tracker
      return $this->resolveContactID("IMB-{$contact_id}", $record, 'external');

    } else {
      $this->logger->logError("Couldn't parse reference '{$reference}'.", $record);
      return NULL;
    }
  }
}
