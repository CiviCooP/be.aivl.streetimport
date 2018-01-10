<?php
/*-------------------------------------------------------------+
| GP StreetImporter Record Handlers                            |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

define('TM_KONTAKT_RESPONSE_OFFF_SPENDE',            3);

define('TM_KONTAKT_RESPONSE_ZUSAGE_FOERDER',         1);
define('TM_KONTAKT_RESPONSE_ZUSAGE_GUARDIAN',       51);
define('TM_KONTAKT_RESPONSE_ZUSAGE_FLOTTE',         53);
define('TM_KONTAKT_RESPONSE_ZUSAGE_ARKTIS',         54);
define('TM_KONTAKT_RESPONSE_ZUSAGE_DETOX',          55);
define('TM_KONTAKT_RESPONSE_ZUSAGE_WAELDER',        57);
define('TM_KONTAKT_RESPONSE_ZUSAGE_GP4ME',          58);
define('TM_KONTAKT_RESPONSE_ZUSAGE_ATOM',           59);

define('TM_KONTAKT_RESPONSE_KONTAKT_STORNO_ZS',     30);
define('TM_KONTAKT_RESPONSE_KONTAKT_STORNO_ZSO',    31);
define('TM_KONTAKT_RESPONSE_KONTAKT_STORNO_SMS',    32);
define('TM_KONTAKT_RESPONSE_KONTAKT_STORNO_DONE',   33);

define('TM_KONTAKT_RESPONSE_KONTAKT_RESCUE',        24);
define('TM_KONTAKT_RESPONSE_KONTAKT_LOESCHEN',      25);
define('TM_KONTAKT_RESPONSE_KONTAKT_STILLEGEN',     26);
define('TM_KONTAKT_RESPONSE_KONTAKT_VERSTORBEN',    40);
define('TM_KONTAKT_RESPONSE_KONTAKT_ANRUFSPERRE',   41);



define('TM_PROJECT_TYPE_CONVERSION',   'umw'); // Umwandlung
define('TM_PROJECT_TYPE_UPGRADE',      'upg'); // Upgrade
define('TM_PROJECT_TYPE_REACTIVATION', 'rea'); // Reaktivierung
define('TM_PROJECT_TYPE_RESEARCH',     'rec'); // Recherche
define('TM_PROJECT_TYPE_SURVEY',       'umf'); // Umfrage
define('TM_PROJECT_TYPE_MIDDLE_DONOR', 'mdu'); // Middle-Donor - two subtypes:
define('TM_PROJECT_TYPE_MD_UPGRADE',   'mdup');//     subtype 1: upgrade
define('TM_PROJECT_TYPE_MD_CONVERSION','mdum');//     subtype 2: conversion (Umwandlung)


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
    $this->file_name_data = $this->parseTmFile($sourceURI);

    $contact_id = $this->getContactID($record);
    if (empty($contact_id)) {
      $this->logger->logImport($record, FALSE, $config->translate('TM Contact'));
      return $this->logger->logError("Contact [{$record['id']}] couldn't be identified.", $record);
    }

    // apply contact base data updates if provided
    // FIELDS: nachname  vorname firma TitelAkademisch TitelAdel TitelAmt  Anrede  geburtsdatum  geburtsjahr strasse hausnummer  hausnummernzusatz PLZ Ort email
    $this->performContactBaseUpdates($contact_id, $record);


    /************************************
     *           VERIFICATION           *
     ***********************************/
    $project_type_full = strtolower($this->file_name_data['project1']);
    $project_type = strtolower(substr($this->file_name_data['project1'], 0, 3));
    $modify_command = 'update';
    $contract_id = NULL;
    $contract_id_required = FALSE;
    switch ($project_type) {
      case TM_PROJECT_TYPE_CONVERSION:
        if (!empty($this->getContractID($contact_id, $record))) {
          $this->logger->logImport($record, FALSE, $config->translate('TM Contact'));
          return $this->logger->logError("Conversion projects shouldn't provide a contract ID", $record);
        }
        break;

      case TM_PROJECT_TYPE_UPGRADE:
        $modify_command = 'update';
        $contract_id_required = TRUE;
        break;

      case TM_PROJECT_TYPE_REACTIVATION:
      case TM_PROJECT_TYPE_RESEARCH:
        $modify_command = 'revive';
        $contract_id_required = TRUE;
        break;

      case TM_PROJECT_TYPE_SURVEY:
        // Nothing to do here?
        break;

      case TM_PROJECT_TYPE_MIDDLE_DONOR:
        if ($project_type_full == TM_PROJECT_TYPE_MD_UPGRADE) {
          $modify_command = 'update';
          $contract_id_required = FALSE;
          break;

        } elseif ($project_type_full == TM_PROJECT_TYPE_MD_CONVERSION) {
          // copied from TM_PROJECT_TYPE_CONVERSION
          if (!empty($this->getContractID($contact_id, $record))) {
            $this->logger->logImport($record, FALSE, $config->translate('TM Contact'));
            return $this->logger->logError("Conversion projects shouldn't provide a contract ID", $record);
          }
          break;
        }

      default:
        $this->logger->logFatal("Unknown project type {$project_type}. Processing stopped.", $record);
        break;
    }



    /************************************
     *         MAIN PROCESSING          *
     ***********************************/
    switch ($record['Ergebnisnummer']) {
      case TM_KONTAKT_RESPONSE_ZUSAGE_FOERDER:
      case TM_KONTAKT_RESPONSE_ZUSAGE_FLOTTE:
      case TM_KONTAKT_RESPONSE_ZUSAGE_ARKTIS:
      case TM_KONTAKT_RESPONSE_ZUSAGE_DETOX:
      case TM_KONTAKT_RESPONSE_ZUSAGE_WAELDER:
      case TM_KONTAKT_RESPONSE_ZUSAGE_GP4ME:
      case TM_KONTAKT_RESPONSE_ZUSAGE_ATOM:
      case TM_KONTAKT_RESPONSE_KONTAKT_RESCUE:
      case TM_KONTAKT_RESPONSE_ZUSAGE_GUARDIAN:
        // this is a conversion/upgrade
        $contract_id = $this->getContractID($contact_id, $record);
        if (empty($contract_id)) {
          // make sure this is no mistake (see GP-1123)
          if ($contract_id_required) {
            // this whole line should not be imported (see GP-1123)
            $this->logger->logFatal("This record type expects a contract id, but it's missing.", $record);
            // $this->logger->logImport($record, FALSE, $config->translate('TM Contact'));
            // stop processing this file alltogether:
            throw new Exception("Format violation, the record type requires a contract_id.", 1);
          } else {
            $contract_id = $this->createContract($contact_id, $record);
          }
        } else {
          // load the contract
          $contract  = $this->getContract($record, $contact_id);
          if (empty($contract)) {
            $this->logger->logError("Couldn't find contract to update (ID: {$contract_id})", $record);
          } else {
            // check if the 'active' state is right
            $is_active = $this->isContractActive($contract);
            if ($modify_command == 'update' && !$is_active) {
              $this->logger->logError("Update projects should refer to active contracts", $record);
            } elseif ($modify_command == 'revive' && $is_active) {
              $this->logger->logError("This project should only refer to inactive contracts", $record);
            } else {
              // submit membership type if there's a change
              if (   $record['Ergebnisnummer'] == TM_KONTAKT_RESPONSE_ZUSAGE_FOERDER
                  || $record['Ergebnisnummer'] == TM_KONTAKT_RESPONSE_KONTAKT_RESCUE) {
                // this simply means 'carry on' (see GP-1000/GP-1328)
                $membership_type_id = NULL;
              } else {
                $membership_type_id = $this->getMembershipTypeID($record);
              }

              // ALL GOOD: do the upgrade!
              $this->updateContract($contract_id, $contact_id, $record, $membership_type_id, $modify_command);
            }
          }
        }
        break;

      case TM_KONTAKT_RESPONSE_OFFF_SPENDE:
        // create a simple OOFF mandate
        $this->createOOFFMandate($contact_id, $record);
        break;

      case TM_KONTAKT_RESPONSE_KONTAKT_STORNO_ZS:
      case TM_KONTAKT_RESPONSE_KONTAKT_STORNO_ZSO:
      case TM_KONTAKT_RESPONSE_KONTAKT_STORNO_SMS:
      case TM_KONTAKT_RESPONSE_KONTAKT_STORNO_DONE:
        // contact wants to cancel his/her contract
        $membership = $this->getContract($record, $contact_id);
        if ($membership) {
          $this->cancelContract($membership, $record);
        } else {
          $this->logger->logWarning("NO contract (membership) found.", $record);
        }
        break;

      case TM_KONTAKT_RESPONSE_KONTAKT_LOESCHEN:
        // contact wants to be erased from GP database
        $this->disableContact($contact_id, 'erase', $record);
        break;

      case TM_KONTAKT_RESPONSE_KONTAKT_STILLEGEN:
        // contact should be disabled
        $this->disableContact($contact_id, 'disable', $record);
        break;

      case TM_KONTAKT_RESPONSE_KONTAKT_VERSTORBEN:
        // contact should be disabled
        $this->disableContact($contact_id, 'deceased', $record);
        break;

      case TM_KONTAKT_RESPONSE_KONTAKT_ANRUFSPERRE:
        // contact doesn't want to be called
        civicrm_api3('Contact', 'create', array(
          'id'           => $contact_id,
          'do_not_phone' => 1));
        break;

      default:
        // in all other cases nothing needs to happen except the
        //  to create the reponse activity, see below.
    }

    // GENERATE RESPONSE ACTIVITY
    $response_title = trim("{$record['Ergebnisnummer']} {$record['ErgebnisText']}");
    $this->createResponseActivity($contact_id, $response_title, $record);


    /************************************
     *      SECONDARY PROCESSING        *
     ***********************************/

    // Sign up for newsletter
    // FIELDS: emailNewsletter
    if ($this->isTrue($record, 'emailNewsletter')) {
      $newsletter_group_id = $config->getNewsletterGroupID();
      $this->addContactToGroup($contact_id, $newsletter_group_id, $record);
    }

    // Add a note if requested
    // FIELDS: BemerkungFreitext
    if (!empty($record['BemerkungFreitext'])) {
      $this->createManualUpdateActivity($contact_id, $record['BemerkungFreitext'], $record);
    }

    // If "X" then set  "rts_counter" in table "civicrm_value_address_statistics"  to "0"
    // FIELDS: AdresseGeprueft
    if ($this->isTrue($record, 'AdresseGeprueft')) {
      $this->addressValidated($contact_id, $record);
    }

    // process additional fields
    // FIELDS: Bemerkung1  Bemerkung2  Bemerkung3  Bemerkung4  Bemerkung5 ...
    for ($i=1; $i <= 10; $i++) {
      if (!empty($record["Bemerkung{$i}"])) {
        $this->processAdditionalFeature($record["Bemerkung{$i}"], $contact_id, $contract_id, $record);
      }
    }

    $this->logger->logImport($record, true, $config->translate('TM Contact'));
  }

  /**
   * Extracts the specific activity date for this line
   */
  protected function getDate($record) {
    if (!empty($record['TagDerTelefonie'])) {
      return date('YmdHis', strtotime($record['TagDerTelefonie']));
    } else {
      return parent::getDate($record);
    }
  }

  /**
   * apply contact base date updates (if present in the data)
   * FIELDS: nachname  vorname firma TitelAkademisch TitelAdel TitelAmt  Anrede  geburtsdatum  geburtsjahr strasse hausnummer  hausnummernzusatz PLZ Ort email
   */
  public function performContactBaseUpdates($contact_id, $record) {
    $config = CRM_Streetimport_Config::singleton();

    // ---------------------------------------------
    // |            Contact Entity                 |
    // ---------------------------------------------
    $contact_base_attributes = array(
      'nachname'        => 'last_name',
      'vorname'         => 'first_name',
      'firma'           => 'current_employer',
      'Anrede'          => 'prefix_id',
      'geburtsdatum'    => 'birth_date',
      'geburtsjahr'     => $config->getGPCustomFieldKey('birth_year'),
      'TitelAkademisch' => 'formal_title_1',
      'TitelAdel'       => 'formal_title_2',
      'TitelAmt'        => 'formal_title_3');

    // extract attributes
    $contact_base_update = array();
    foreach ($contact_base_attributes as $record_key => $civi_key) {
      if (!empty($record[$record_key])) {
        $contact_base_update[$civi_key] = trim($record[$record_key]);
      }
    }

    // compile formal title
    $formal_title = '';
    for ($i=1; $i <= 3; $i++) {
      if (isset($contact_base_update["formal_title_{$i}"])) {
        $formal_title = trim($formal_title . ' ' . $contact_base_update["formal_title_{$i}"]);
        unset($contact_base_update["formal_title_{$i}"]);
      }
    }
    if (!empty($formal_title)) {
      $contact_base_update['formal_title'] = $formal_title;
    }

    // update contact
    if (!empty($contact_base_update)) {
      $contact_base_update['id'] = $contact_id;
      $this->resolveFields($contact_base_update, $record);

      // check if contact is really individual
      $contact = civicrm_api3('Contact', 'getsingle', array(
        'id'     => $contact_id,
        'return' => 'contact_type'));

      if ($contact['contact_type'] != 'Individual') {
        // this is NOT and Individual: create an activity (see GP-1229)
        unset($contact_base_update['id']);
        $this->createManualUpdateActivity(
            $contact_id,
            "Convert to 'Individual'",
            $record,
            'activities/ManualConversion.tpl',
            array('contact' => $contact, 'update' => $contact_base_update));
        $this->logger->logError("Contact [{$contact_id}] is not an Individual and cannot be updated. A manual update activity has been created.", $record);
      } else {
        civicrm_api3('Contact', 'create', $contact_base_update);
        $this->createContactUpdatedActivity($contact_id, $config->translate('Contact Base Data Updated'), NULL, $record);
        $this->logger->logDebug("Contact [{$contact_id}] base data updated: " . json_encode($contact_base_update), $record);
      }
    }

    // ---------------------------------------------
    // |            Address Entity                 |
    // ---------------------------------------------
    $address_base_attributes = array(
      'PLZ'               => 'postal_code',
      'Ort'               => 'city',
      'strasse'           => 'street_address_1',
      'hausnummer'        => 'street_address_2',
      'hausnummernzusatz' => 'street_address_3'
      );

    // extract attributes
    $address_update = array();
    foreach ($address_base_attributes as $record_key => $civi_key) {
      if (!empty($record[$record_key])) {
        $address_update[$civi_key] = trim($record[$record_key]);
      }
    }

    // compile street address
    $street_address = '';
    for ($i=1; $i <= 3; $i++) {
      if (isset($address_update["street_address_{$i}"])) {
        $street_address = trim($street_address . ' ' . $address_update["street_address_{$i}"]);
        unset($address_update["street_address_{$i}"]);
      }
    }
    if (!empty($street_address)) {
      $address_update['street_address'] = $street_address;
    }

    // hand this data over to a dedicated alogorithm
    $this->createOrUpdateAddress($contact_id, $address_update, $record);


    // ---------------------------------------------
    // |             Email Entity                  |
    // ---------------------------------------------
    if (!empty($record['email'])) {
      $this->addDetail($record, $contact_id, 'Email', array('email' => $record['email']), TRUE);
    }
  }

  /**
   * mark the given address as valid by resetting the RTS counter
   */
  public function addressValidated($contact_id, $record) {
    $config = CRM_Streetimport_Config::singleton();

    $address_id = $this->getAddressId($contact_id, $record);
    if ($address_id) {
      civicrm_api3('Address', 'create', array(
        'id' => $address_id,
        $config->getGPCustomFieldKey('rts_counter') => 0));
      $this->logger->logDebug("RTS counter for address [{$address_id}] (contact [{$contact_id}]) was reset.", $record);
    } else {
      $this->logger->logDebug("RTS counter couldn't be reset, (primary) address for contact [{$contact_id}] couldn't be identified.", $record);
    }
  }

  /**
   * Process addational feature from the semi-formal "Bemerkung" note fields
   *
   * Those can trigger certain actions within Civi as mentioned in doc "20131107_Responses_Bemerkungen_1-5"
   */
  public function processAdditionalFeature($note, $contact_id, $contract_id, $record) {
    $config = CRM_Streetimport_Config::singleton();
    $this->logger->logDebug("Contact [{$contact_id}] wants '{$note}'", $record);
    switch ($note) {
       case 'erhält keine Post':
         // Marco: "Posthäkchen setzen, Adresse zurücksetzen, Kürzel 15 + 18 + ZO löschen"
         // i.e.: 1) allow mailing
         civicrm_api3('Contact', 'create', array(
          'id' => $contact_id,
          'do_not_mail' => 0));

         // i.e.: 2) mark address as validated
         $this->addressValidated($contact_id, $record);

         // i.e.: 3) remove from groups
         $this->removeContactFromGroup($contact_id, $config->getGPGroupID('kein ACT'), $record);
         $this->removeContactFromGroup($contact_id, $config->getGPGroupID('ACT nur online'), $record);
         $this->removeContactFromGroup($contact_id, $config->getGPGroupID('Zusendungen nur online'), $record);
         break;

       case 'kein Telefonkontakt erwünscht':
         // Marco: "Telefonkanal schließen"
         civicrm_api3('Contact', 'create', array(
          'id' => $contact_id,
          'do_not_phone' => 1));
         $this->logger->logDebug("Setting 'do_not_phone' for contact [{$contact_id}].", $record);
         break;

       case 'keine Kalender senden':
         // Marco: 'Negativleistung "Kalender"'
         $this->addContactToGroup($contact_id, $config->getGPGroupID('kein Kalender'), $record);
         $this->logger->logDebug("Added contact [{$contact_id}] to group 'kein Kalender'.", $record);
         break;

       case 'nur Vereinsmagazin, sonst keine Post':
       case 'nur Vereinsmagazin mit Spendenquittung':
         // Marco: Positivleistung "Nur ACT"
         $this->addContactToGroup($contact_id, $config->getGPGroupID('Nur ACT'), $record);
         $this->logger->logDebug("Added contact [{$contact_id}] to group 'Nur ACT'.", $record);
         break;

       case 'nur Vereinsmagazin mit 1 Mailing':
         // Marco: Positivleistung "Nur ACT"
         $this->addContactToGroup($contact_id, $config->getGPGroupID('Nur ACT'), $record);
         $this->logger->logDebug("Added contact [{$contact_id}] to group 'Nur ACT'.", $record);

         //  + alle Monate bis auf Oktober deaktivieren
         $dm_restrictions = $config->getGPCustomFieldKey('dm_restrictions');
         civicrm_api3('Contact', 'create', array(
            'id'             => $contact_id,
            $dm_restrictions => '1')); // one mailing only
         $this->logger->logDebug("Contact [{$contact_id}]: DM restrictions set to '1'.", $record);
         break;

       case 'möchte keine Incentives':
         // Marco: Negativleistung " Geschenke"
         $this->addContactToGroup($contact_id, $config->getGPGroupID('keine Geschenke'), $record);
         $this->logger->logDebug("Added contact [{$contact_id}] to group 'keine Geschenke'.", $record);
         break;

       case 'möchte keine Postsendungen':
         // Marco: Postkanal schließen
         civicrm_api3('Contact', 'create', array(
          'id' => $contact_id,
          'do_not_mail' => 1));
         $this->logger->logDebug("Setting 'do_not_mail' for contact [{$contact_id}].", $record);
         break;

       case 'möchte max 4 Postsendungen':
         // Marco: Leistung Januar, Februar, März, Mai, Juli, August, September, November deaktivieren
         $dm_restrictions = $config->getGPCustomFieldKey('dm_restrictions');
         civicrm_api3('Contact', 'create', array(
            'id'             => $contact_id,
            $dm_restrictions => '4')); // 4 mailings
         $this->logger->logDebug("Contact [{$contact_id}]: DM restrictions set to '4'.", $record);
         break;

       case 'Postsendung nur bei Notfällen':
         // Marco: Im Leistungstool alle Monate rot einfärben
         $dm_restrictions = $config->getGPCustomFieldKey('dm_restrictions');
         civicrm_api3('Contact', 'create', array(
            'id'             => $contact_id,
            $dm_restrictions => '0')); // only emergency mailings
         $this->logger->logDebug("Contact [{$contact_id}]: DM restrictions set to '0'.", $record);
         break;

       case 'hat kein Konto':
       case 'möchte nur Jahresbericht':
         // do nothing according to '20131107_Responses_Bemerkungen_1-5.xlsx'
         $this->logger->logDebug("Nothing to be done for feature '{$note}'", $record);
         break;

       case 'Bankdaten gehören nicht dem Spender':
       case 'Spende wurde übernommen, Daten geändert':
       case 'erhält Post doppelt':
         // for these cases a manual update is required
         $this->createManualUpdateActivity($contact_id, $note, $record);
         $this->logger->logDebug("Manual update ticket created for contact [{$contact_id}]", $record);
         break;

       default:
         // maybe it's a T-Shirt?
         if (preg_match('#^(?P<shirt_type>M|W)/(?P<shirt_size>[A-Z]{1,2})$#', $note, $match)) {
           // create a webshop activity (Activity type: ID 75)  with the status "scheduled"
           //  and in the field "order_type" option value 11 "T-Shirt"
           $this->createWebshopActivity($contact_id, $record, array(
             'subject' => "order type T-Shirt {$match['shirt_type']}/{$match['shirt_size']} AND number of items 1",
             $config->getGPCustomFieldKey('order_type')        => 11, // T-Shirt
             $config->getGPCustomFieldKey('order_count')       => 1,  // 1 x T-Shirt
             $config->getGPCustomFieldKey('shirt_type')        => $match['shirt_type'],
             $config->getGPCustomFieldKey('shirt_size')        => $match['shirt_size'],
             $config->getGPCustomFieldKey('linked_membership') => $contract_id,
             ));
           break;
         }

         return $this->logger->logError("Unkown feature '{$note}' ignored.", $record);
         break;

     }
  }

  /**
   * Extract the contract id from the record
   */
  protected function getContractID($contact_id, $record) {
    if (empty($record['Vertragsnummer'])) {
      return NULL;
    }

    if ($this->isCompatibilityMode($record)) {
      // legacy files: look up via membership_imbid
      $config = CRM_Streetimport_Config::singleton();
      $membership_imbid =$config->getGPCustomFieldKey('membership_imbid');
      $membership = civicrm_api3('Membership', 'get', array(
        'contact_id'      => $contact_id,
        $membership_imbid => $record['Vertragsnummer']));
      return $membership['id'];
    } else {
      return (int) $record['Vertragsnummer'];
    }
  }

  /**
   * Get the requested membership type ID from the data record
   */
  protected function getMembershipTypeID($record) {
    switch ($record['Ergebnisnummer']) {
      case TM_KONTAKT_RESPONSE_ZUSAGE_FOERDER:
        $name = 'Förderer';
        break;

      case TM_KONTAKT_RESPONSE_ZUSAGE_FLOTTE:
        $name = 'Flottenpatenschaft';
        break;

      case TM_KONTAKT_RESPONSE_ZUSAGE_ARKTIS:
        $name = 'arctic defender';
        break;

      case TM_KONTAKT_RESPONSE_ZUSAGE_DETOX:
        // TODO: is this correct?
        $name = 'Landwirtschaft';
        break;

      case TM_KONTAKT_RESPONSE_ZUSAGE_WAELDER:
        $name = 'Könige der Wälder';
        break;

      case TM_KONTAKT_RESPONSE_ZUSAGE_GP4ME:
        $name = 'Greenpeace for me';
        break;

      case TM_KONTAKT_RESPONSE_ZUSAGE_ATOM:
        $name = 'Atom-Eingreiftrupp';
        break;

      case TM_KONTAKT_RESPONSE_ZUSAGE_GUARDIAN:
        $name = 'Guardian of the Ocean';
        break;


      default:
        $this->logger->logError("No membership type can be derived from result code (Ergebnisnummer) '{$record['Ergebnisnummer']}'.", $record);
        return NULL;
    }

    // find a membership type with that name
    $membership_types = $this->getMembershipTypes();
    foreach ($membership_types as $membership_type) {
      if ($membership_type['name'] == $name) {
        return $membership_type['id'];
      }
    }

    $this->logger->logError("Membership type '{$name}' not found.", $record);
    return NULL;
  }
}
