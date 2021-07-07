<?php
/*-------------------------------------------------------------+
| StreetImporter Record Handlers                               |
| Copyright (C) 2017 SYSTOPIA / CiviCooP                       |
| Author: Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>    |
|         B. Endres (SYSTOPIA) <endres@systopia.de>            |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Streetimport_ExtensionUtil as E;

class CRM_Admin_Page_StreetimportFiles extends CRM_Core_Page {

  public function run() {
    $config = CRM_Streetimport_Config::singleton();

    if (!$current_location = CRM_Utils_Request::retrieve('location', 'String', $this)) {
      $current_location = 'import';
    }
    $this->assign('current', $current_location);

    $locations = [
      'import' => [
        'title' => E::ts('Import'),
        'path' => $config->getImportFileLocation(),
      ],
      'processing' => [
        'title' => E::ts('Processing'),
        'path' => $config->getProcessingFileLocation(),
      ],
      'processed' => [
        'title' => E::ts('Processed'),
        'path' => $config->getProcessedFileLocation(),
      ],
      'failed' => [
        'title' => E::ts('Failed'),
        'path' => $config->getFailFileLocation(),
      ],
    ];
    foreach ($locations as $type => &$location) {
      $files = CRM_Utils_File::findFiles($location['path'], '*');
      $location['count'] = count($files);
      if ($type == $current_location) {
        // Sort by file date DESC.
        usort($files, function($a, $b) {
          return filemtime($b) - filemtime($a);
        });
        foreach ($files as $file) {
          // Format file size.
          $bytes = filesize($file);
          $sz = 'BKMGTP';
          $factor = floor((strlen($bytes) - 1) / 3);
          $filesize = sprintf("%.2f", $bytes / pow(1024, $factor)) . ' ' . @$sz[$factor];

          // Format URL (including encoded location and basename.
          $url = CRM_Utils_System::url(
            'civicrm/streetimport/file?file='
            . base64_encode($type . ',' . basename($file))
          );

          $location['files'][] = [
            'url' => $url,
            'name' => basename($file),
            'icon' =>CRM_Utils_File::getIconFromMimeType(mime_content_type($file)),
            'size' => $filesize,
            'date' => CRM_Utils_Date::customFormatTs(filemtime($file)),
          ];
        }
      }
    }
    $this->assign('locations', $locations);

    return parent::run();
  }

}
