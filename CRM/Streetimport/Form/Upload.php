<?php
/*-------------------------------------------------------------+
| StreetImporter Record Handlers                               |
| Copyright (C) 2018 SYSTOPIA / CiviCooP                       |
| Author: B. Endres (SYSTOPIA) <endres@systopia.de>            |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

use CRM_Streetimport_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://wiki.civicrm.org/confluence/display/CRMDOC/QuickForm+Reference
 */
class CRM_Streetimport_Form_Upload extends CRM_Core_Form {
  public function buildQuickForm() {

    // this is an import
    CRM_Utils_System::setTitle(E::ts("SI - Import CSV Files"));

    $this->addElement(
        'file',
        'import_files',
        E::ts('Select files to import'),
        'multiple');

    $this->addButtons(array(
        array(
            'type' => 'submit',
            'name' => E::ts('Import'),
            'isDefault' => TRUE,
        ),
    ));

    // TODO: Add checkbox for whether to immediately process the uploaded file.

    parent::buildQuickForm();
  }

  public function postProcess() {
    $values = $this->exportValues();
    try {
      // IMPORT a list of files
      if (!empty($_FILES['import_files'])) {
        // first: create a new folder
        $tmp_dir = tempnam(sys_get_temp_dir(), 'streetimport_');
        unlink($tmp_dir); // tempnam returns folder
        mkdir($tmp_dir, 0700);

        // then, move all files there
        for ($i = 0; $i < count($_FILES['import_files']['tmp_name']); $i++) {
          $tmp_name = $_FILES['import_files']['tmp_name'][$i];
          // TODO: Move to configured import directory.
          $new_name = $tmp_dir . DIRECTORY_SEPARATOR . $_FILES['import_files']['name'][$i];
          copy($tmp_name, $new_name);
        }

        // finally: run the import
        // TODO: Only process when requested (checkbox).
        $result = civicrm_api3('Streetimport', 'importcsvfile', array('source_folder' => $tmp_dir));
        CRM_Core_Session::setStatus($result['values'], E::ts('Done'));

      } else {
        CRM_Core_Session::setStatus(E::ts('No import files selected'), E::ts('Error'));
      }
    } catch (Exception $ex) {
      CRM_Core_Session::setStatus(E::ts('Import/update failed: %1', [1 => $ex->getMessage()]), E::ts('Failed'));
    }

    // go back to where we came from
    $back_url = CRM_Core_Session::singleton()->readUserContext();
    if (strstr($back_url, 'civicrm/contribute/search?force=1')) {
      // this is wrong...
      CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/dashboard'));
    } else {
      CRM_Utils_System::redirect($back_url);
    }
  }
}
