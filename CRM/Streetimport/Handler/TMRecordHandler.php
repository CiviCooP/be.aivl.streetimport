<?php
/**
 * Abstract class bundle common GP importer functions
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
abstract class CRM_Streetimport_Handler_TMRecordHandler extends CRM_Streetimport_Handler_GPRecordHandler {

  /** file name pattern as used by TM company */
  protected static $TM_PATTERN = '#^(?P<org>[a-zA-Z\-]+)_(?P<project1>\w+)_(?P<tm_company>[a-z]+)_(?P<code>\d{4})_(?P<date>\d{8})_(?P<time>\d{6})_(?P<project2>.+)_(?P<file_type>[a-zA-Z]+)[.]csv$#';

  /**
   * Will try to parse the given name and extract the parameters outlined in TM_PATTERN
   * 
   * @return NULL if not matched, data else
   */
  protected function parseTmFile($sourceID) {
    if (preg_match(self::$TM_PATTERN, $sourceID, $matches)) {
      return $matches;
    } else {
      return NULL;
    }
  }
}
