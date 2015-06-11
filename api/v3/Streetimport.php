<?php
/**
 * Basic API for street importer
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */

/**
 * import a CSV file, either by name, or from a given path
 * 
 * @param $params['filepath']       local CSV file to import
 * @param $params['source_folder']  local file path where to look for the next file
 *                                   if $params['filepath'] is set, this will be ignored
 *                                   if not set, defaults to CRM_Streetimport_Config->getImportFileLocation()
 * @param $params['archive_folder'] local file path of a folder to store the processed files
 *                                   if not set, defaults to CRM_Streetimport_Config->getProcessedFileLocation()
 * @param path  file path to a csv file
 *
 * @return array API result array
 * @access public
 */
function civicrm_api3_streetimport_importcsvfile($params) {
  $config = CRM_Streetimport_Config::singleton();
  $result = new CRM_Streetimport_ImportResult();

  // first, get the parameters sorted out
  if (isset($params['source_folder'])) {
    $source_folder = $params['source_folder'];
  } else {
    $source_folder = $config->getImportFileLocation();
  }

  if (isset($params['archive_folder'])) {
    $archive_folder = $params['archive_folder'];
  } else {
    $archive_folder = $config->getProcessedFileLocation();
  }

  $source_file = NULL;
  if (!empty($params['filepath'])) {
    // filepath is given, import that file
    $source_file = $params['filepath'];

  } else {
    // NO filepath given, get one from the directory
    $files = glob($source_folder . "/*.csv");
    
    // make sure it's sorted
    sort($files);
    
    // and take the first one
    if (!empty($files)) {
      $source_file = $files[0];
    }
  }
  // now run the actual import
  try {
    if (!$source_file) {
      $result->logMessage("No source files found.");
    } else {
      $dataSource = new CRM_Streetimport_FileCsvDataSource($source_file, $result);
      CRM_Streetimport_RecordHandler::processDataSource($dataSource);
      
      // finally: archive the file (if requested)
      if (!empty($archive_folder)) {
        $archive_file = $archive_folder . DIRECTORY_SEPARATOR . basename($source_file);
        $success = rename($source_file, $archive_file);
        if ($success) {
          $result->logMessage("Moved file '$source_file' to '$archive_file'.");
        } else {
          $result->abort("FAILED to move file '$source_file' to '$archive_file'!");
        }
      }
    }
  } catch (Exception $ex) {
    // whole import was aborted...
    $result->logMessage("Exception was: " . $ex->getMessage());
  }
  return $result->toAPIResult();
}

/**
 * simple metadata for import_csv_file
 */
function _civicrm_api3_streetimport_importcsvfile(&$params) {
  $params['filepath']['api.required'] = 1;
}

