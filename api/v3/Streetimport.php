<?php
/**
 * Basic API for street importer
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */

/**
 * import a simple CSV file
 * 
 * @param path  file path to a csv file
 *
 * @return array API result array
 * @access public
 */
function civicrm_api3_streetimport_importcsvfile($params) {
  $dataSource = new CRM_Streetimport_FileCsvDataSource($params['filepath']);
  CRM_Streetimport_RecordHandler::processDataSource($dataSource);
  return civicrm_api3_create_success();
}

/**
 * simple metadata for import_csv_file
 */
function _civicrm_api3_streetimport_importcsvfile(&$params) {
  $params['filepath']['api.required'] = 1;
}