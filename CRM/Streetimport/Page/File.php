<?php
/*-------------------------------------------------------------+
| StreetImporter Record Handlers                               |
| Copyright (C) 2017 SYSTOPIA / CiviCooP                       |
| Author: Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>    |
|         B. Endres (SYSTOPIA) <endres@systopia.de>            |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Streetimport_ExtensionUtil as E;

class CRM_Streetimport_Page_File extends CRM_Core_Page {

  public function run() {
    $file = explode(',', base64_decode(
      CRM_Utils_Request::retrieve('file', 'String', $this)
    ));
    // Filter for Streetimport file locations.
    if (!$location = CRM_Streetimport_Config::singleton()->getFileLocation($file[0])) {
      throw new Exception(E::ts('Invalid Streetimport file location.'));
    }
    $path = $location . DIRECTORY_SEPARATOR . basename($file[1]);

    $buffer = file_get_contents($path);
    CRM_Utils_System::download(
      CRM_Utils_File::cleanFileName(basename($path)),
      mime_content_type($path),
      $buffer,
      NULL,
      TRUE,
      'download'
    );
  }

}
