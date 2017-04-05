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

        $dataSource = new CRM_Streetimport_FileCsvDataSource($source_file, $result);
        CRM_Streetimport_RecordHandler::processDataSource($dataSource);

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
      $result->logMessage("Exception was: " . $ex->getMessage());

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
      $result->logMessage("Exception was: " . $ex->getMessage());

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
