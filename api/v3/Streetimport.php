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
 * @param $params['failed_folder']  local file path of a folder to store the files for which processing has failed
 *                                   if not set, defaults to CRM_Streetimport_Config->getFailFileLocation()
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

  if (isset($params['failed_folder'])) {
    $failed_folder = $params['failed_folder'];
  } else {
    $failed_folder = $config->getFailFileLocation();
  }

  $source_file = NULL;
  if (!empty($params['filepath'])) {
    // filepath is given, import that file
    $files[] = $params['filepath'];

  } else {
    // NO filepath given, get one from the directory
    $files = glob($source_folder . "/*.csv");
    
    // make sure it's sorted
    sort($files);    
  }

  // now run the actual import
  foreach ($files as $source_file) {
    try {
      if (!$source_file) {
        $result->logMessage($config->translate("No source files found"));
      } else {
        $dataSource = new CRM_Streetimport_FileCsvDataSource($source_file, $result);
        CRM_Streetimport_RecordHandler::processDataSource($dataSource);

        // finally: move the file to the failed folder
        if (!empty($archive_folder)) {
          $processed_file = $archive_folder . DIRECTORY_SEPARATOR . basename($source_file);
          $success = rename($source_file, $processed_file);
          if ($success) {
            $result->logMessage($config->translate("Moved file")." ".$source_file." ".$config->translate("to")." ".$processed_file);
          } else {
            $result->abort($config->translate("FAILED to move file")." ".$source_file.$config->translate("to")." ".$processed_file);
          }
        }
      }
    } catch (Exception $ex) {
      // whole import was aborted...
      $result->logMessage("Exception was: " . $ex->getMessage());

      // move the failed file to the failed folder
      if (!empty($failed_folder)) {
        $failed_file = $failed_folder . DIRECTORY_SEPARATOR . basename($source_file);
        $success = rename($source_file, $failed_file);
        if ($success) {
          $result->logMessage($config->translate("Moved failed file")." ".$source_file." ".$config->translate("to")." ".$failed_file);
        } else {
          $result->abort($config->translate("FAILED to move failed file")." ".$source_file.$config->translate("to")." ".$failed_file);
        }
      }
    }
  }
  return $result->toAPIResult();
}

/**
 * simple metadata for import_csv_file
 */
function _civicrm_api3_streetimport_importcsvfile(&$params) {
  $params['filepath']['api.required'] = 1;
}

