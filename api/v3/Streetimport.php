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
    $source_folder = rtrim($params['source_folder'], DIRECTORY_SEPARATOR);
  } else {
    $source_folder = rtrim($config->getImportFileLocation(), DIRECTORY_SEPARATOR);
  }

  if (isset($params['processing_folder'])) {
    $processing_folder = rtrim($params['processing_folder'], DIRECTORY_SEPARATOR);
  } else {
    $processing_folder = rtrim($config->getProcessingFileLocation(), DIRECTORY_SEPARATOR);
  }

  if (isset($params['archive_folder'])) {
    $archive_folder = rtrim($params['archive_folder'], DIRECTORY_SEPARATOR);
  } else {
    $archive_folder = rtrim($config->getProcessedFileLocation(), DIRECTORY_SEPARATOR);
  }

  if (isset($params['failed_folder'])) {
    $failed_folder = rtrim($params['failed_folder'], DIRECTORY_SEPARATOR);
  } else {
    $failed_folder = rtrim($config->getFailFileLocation(), DIRECTORY_SEPARATOR);
  }

  $source_file = NULL;
  if (!empty($params['filepath'])) {
    // filepath is given, import that file
    $files[] = $params['filepath'];

  } else {
    // check folders
    foreach (array($source_folder, $processing_folder, $archive_folder, $failed_folder) as $folder) {
      if (empty($folder)) {
        return civicrm_api3_create_error("Please configure your folders in the settings.");
      }
      if (!is_writable($folder)) {
        return civicrm_api3_create_error("All folders need to be writeable. Please configure your folders in the settings.");
      }
    }

    // find the files to process
    $files = glob($source_folder . DIRECTORY_SEPARATOR . "*.csv");
    $files += glob($source_folder . DIRECTORY_SEPARATOR . "*.CSV");

    // make sure it's sorted
    sort($files);
  }

  // now run the actual import
  foreach ($files as $source_file) {
    $result->setLogFile(NULL);
    $processing_file = $completed_file = NULL;

    if (!file_exists($source_file)) {
      // file has probably been moved since we started processing
      continue;
    }

    if (!$source_file) {
      // I'm not sure who this could happen... but it was there before
      $result->logMessage($config->translate("No source files found"));
    }

    // START PROCESSING:
    try {
      // STEP 0: sort out files
      $processing_file = $processing_folder . DIRECTORY_SEPARATOR . basename($source_file);
      $log_file_path = dirname($processing_file) . DIRECTORY_SEPARATOR . basename($source_file) . '-' . date('YmdHis') . '.log';
      $result->setLogFile($log_file_path);

      // STEP 1: try to move the file into the 'processing' location
      if (!rename($source_file, $processing_file)) {
        $result->logMessage("Couldn't start working on file '{$source_file}'. Possible interference with another process.", NULL, BE_AIVL_STREETIMPORT_ERROR);
        continue;
      }
      $result->logMessage("Moved file '{$source_file}' to '{$processing_file}' for processing.", NULL, BE_AIVL_STREETIMPORT_ERROR);

      // STEP 2: process the file
      $dataSource = new CRM_Streetimport_FileCsvDataSource($processing_file, $result);
      CRM_Streetimport_RecordHandler::processDataSource($dataSource);

      // STEP 3: move file + log to completed folder
      $completed_file = $archive_folder . DIRECTORY_SEPARATOR . basename($processing_file);
      if (rename($processing_file, $completed_file)) {
        $result->logMessage("Moved file '{$processing_file}' to '{$completed_file}' after completion.", NULL, BE_AIVL_STREETIMPORT_ERROR);
          // move log file
        $log_file_path = $result->getLogFile();
        $log_file_new  = $archive_folder . DIRECTORY_SEPARATOR . basename($log_file_path);
        rename($log_file_path, $log_file_new);
      } else {
        $result->logMessage("Couldn't move file '{$processing_file}' to '{$completed_file}'. Possible interference with another process.", NULL, BE_AIVL_STREETIMPORT_ERROR);
      }


    // ERROR DURING PROCESSING
    } catch (Exception $ex) {
      $result->logMessage("Exception was: " . $ex->getMessage(), NULL, BE_AIVL_STREETIMPORT_ERROR);
      $result->logMessage("Stacktrace:\n" . $ex->getTraceAsString(), NULL, BE_AIVL_STREETIMPORT_ERROR);

      // ERR-STEP 1: try to move the file to FAILED
      $failed_file = $failed_folder . DIRECTORY_SEPARATOR . basename($processing_file);
      if (rename($processing_file, $failed_file)) {
        $result->logMessage("Moved file '{$processing_file}' to '{$failed_file}' after failure.", NULL, BE_AIVL_STREETIMPORT_ERROR);
        // move the log file to the same location
        $log_file_path = $result->getLogFile();
        $log_file_new  = $failed_folder . DIRECTORY_SEPARATOR . basename($log_file_path);
        rename($log_file_path, $log_file_new);
      } else {
        $result->logMessage("Couldn't move file '{$processing_file}' to '{$completed_file}'. Possible interference with another process.", NULL, BE_AIVL_STREETIMPORT_ERROR);
      }
    }
  }

  // CLEANUP AND RETURN
  $result->setLogFile(NULL);
  return $result->toAPIResult();
}

/**
 * simple metadata for import_csv_file
 */
function _civicrm_api3_streetimport_importcsvfile(&$params) {
  $params['filepath']['api.required'] = 1;
}

/**
 * @todo What is this? A clone of Streetimport:importcsvfile?
 * @author ?Erik Hommel?
 * @deprecated this can go, right?
 */
function civicrm_api3_streetimport_tm($params) {
  $config = CRM_Streetimport_Config::singleton();
  $result = new CRM_Streetimport_ImportResult();

  // first, get the parameters sorted out
  if (isset($params['source_folder'])) {
    $source_folder = rtrim($params['source_folder'], DIRECTORY_SEPARATOR);
  } else {
    $source_folder = rtrim($config->getImportFileLocation(), DIRECTORY_SEPARATOR);
  }

  if (isset($params['archive_folder'])) {
    $archive_folder = rtrim($params['archive_folder'], DIRECTORY_SEPARATOR);
  } else {
    $archive_folder = rtrim($config->getProcessedFileLocation(), DIRECTORY_SEPARATOR);
  }

  if (isset($params['failed_folder'])) {
    $failed_folder = rtrim($params['failed_folder'], DIRECTORY_SEPARATOR);
  } else {
    $failed_folder = rtrim($config->getFailFileLocation(), DIRECTORY_SEPARATOR);
  }

  $source_file = NULL;
  if (!empty($params['filepath'])) {
    // filepath is given, import that file
    $files[] = $params['filepath'];

  } else {
    // NO filepath given, get one from the directory
    $files = glob($source_folder . DIRECTORY_SEPARATOR . "*.csv");
    // make sure it's sorted
    sort($files);
  }

  // now run the actual import
  foreach ($files as $source_file) {
    try {
      if (!$source_file) {
        $result->logMessage($config->translate("No source files found"));
      } else {
        // set log file first
        $log_file_path = dirname($source_file) . DIRECTORY_SEPARATOR . basename($source_file) . '-' . date('YmdHis') . '.log';
        $result->setLogFile($log_file_path);

        //TODO The mapping is currently defined at the data source level, but we want to use a different mapping per handler.

        $dataSource = new CRM_Streetimport_FileCsvDataSource($source_file, $result);

        CRM_Streetimport_RecordHandler::processDataSource($dataSource);
        exit;
        // finally: move the file to the failed folder, along with the .log file
        if (!empty($archive_folder)) {
          $processed_file = $archive_folder . DIRECTORY_SEPARATOR . basename($source_file);
          $success = rename($source_file, $processed_file);
          if ($success) {
            $result->logMessage($config->translate("Moved file")." ".$source_file." ".$config->translate("to")." ".$processed_file);

            // move the log file to the same location
            $log_file_path = $result->getLogFile();
            $log_file_new  = $archive_folder . DIRECTORY_SEPARATOR . basename($log_file_path);
            $result->setLogFile(NULL); // do we need to close the log file before moving?
            rename($log_file_path, $log_file_new);
          } else {
            $result->abort($config->translate("FAILED to move file")." ".$source_file.$config->translate("to")." ".$processed_file);
          }
        }
      }
    } catch (Exception $ex) {
      // whole import was aborted...
      $result->logMessage("Exception was: " . $ex->getMessage(), NULL, BE_AIVL_STREETIMPORT_ERROR);
      $result->logMessage("Stacktrace:\n" . $ex->getTraceAsString(), NULL, BE_AIVL_STREETIMPORT_ERROR);

      // move the failed file to the failed folder
      if (!empty($failed_folder)) {
        $failed_file = $failed_folder . DIRECTORY_SEPARATOR . basename($source_file);
        $success = rename($source_file, $failed_file);
        if ($success) {
          $result->logMessage($config->translate("Moved failed file")." ".$source_file." ".$config->translate("to")." ".$failed_file);

          // move the log file to the same location
          $log_file_path = $result->getLogFile();
          $log_file_new  = $failed_folder . DIRECTORY_SEPARATOR . basename($log_file_path);
          $result->setLogFile(NULL); // do we need to close the log file before moving?
          rename($log_file_path, $log_file_new);

        } else {
          $result->abort($config->translate("FAILED to move failed file")." ".$source_file.$config->translate("to")." ".$failed_file);
        }
      }
    }
  }
  return $result->toAPIResult();
}
