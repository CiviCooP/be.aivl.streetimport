<?php
/**
 * Abstract class bundle common GP importer functions
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
abstract class CRM_Streetimport_Handler_GPRecordHandler extends CRM_Streetimport_RecordHandler {

  // TODO
  

  /**
   * look up contact with GP ID
   */
  protected function getContactIDbyGPID($gp_id) {
    if (empty($gp_id)) return NULL;

    $external_identifier = 'IMB-' . trim($gp_id);

    // look up contact via external_identifier
    $contacts = civicrm_api3('Contact', 'get', array(
      'external_identifier' => $external_identifier,
      'return'              => 'id'));
    if ($contacts['count'] == 1) {
      return $contacts['id'];
    } elseif ($contacts['count'] > 1) {
      // not unique? this shouldn't happen
      return NULL;
    } else {
      // NOT found
      return NULL;
    }
  }
}
