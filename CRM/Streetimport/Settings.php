<?php

/**
 * Created by PhpStorm.
 * User: erik
 * Date: 6-1-16
 * Time: 15:55
 */
class CRM_Streetimport_Settings {

  private $_genericSettings = array();
  private $_loadTypeSettings = array();
  private $_resourcesPath = NULL;

  /**
   * CRM_Streetimport_Settings constructor.
   */
  public function __construct() {
    $civicrmSettings = civicrm_api3('Setting', 'Getsingle', array());
    $this->_resourcesPath = $civicrmSettings['extensionsDir'].'/be.aivl.streetimport/resources/';
    $this->setGenericSettings();
    $this->setLoadTypeSettings();
  }

  /**
   * Getter for settings: generic if load type = 0, for load type if value
   *
   * @param int $loadType
   * @return array
   * @access public
   */
  public function get($loadType = 0) {
    if (empty($loadType)) {
      return $this->_genericSettings;
    } else {
     return $this->_loadTypeSettings[$loadType];
    }
  }

  /**
   * Method to save the generic settings
   *
   * @param array $params
   * @return array
   * @access public
   */
  public function saveGeneric($params) {
    // TODO: implement
    if ($this->validateSettingsParams($params) == TRUE) {
      return $this->get();
    }
  }

  /**
   * Method to save the load type settings
   *
   * @param array $params
   * @return array
   * @throws Exception when $params has no key load_type
   * @access public
   */
  public function saveLoadType($params) {
    // TODO: implement
    if ($this->validateSettingsParams($params) == TRUE) {
      if (!isset($params['load_type'])) {
        throw new Exception('No load type in parameters when saving load type settings, not able to save settings');
      }
      return $this->get($params['load_type']);
    }
  }

  /**
   * Method to validate the params array with settings
   *
   * @param array $params
   * @return bool
   * @throws Exception when $params is not an array, empty or does not contain load_type
   * @access private
   */
  private function validateSettingsParams($params) {
    if (!is_array($params)) {
      throw new Exception('Parameters has to be array in function to save settings, not able to save settings');
    }
    if (empty($params)) {
      throw new Exception('Parameters can not be empty in function to  save settings, not able to save settings');
    }
    return TRUE;
  }

  /**
   * Method to set the generic settings upon object instantiation
   *
   * @access private
   * @throws Exception if json could not be loaded
   */
  private function setGenericSettings() {
    $jsonFile = $this->_resourcesPath.'generic_import_settings.json';
    if (!file_exists($jsonFile)) {
      throw new Exception('Could not load generic_import_settings configuration file for extension, contact your system administrator!');
    }
    $genericSettingsJson = file_get_contents($jsonFile);
    $this->_genericSettings = json_decode($genericSettingsJson, true);
  }

  /**
   * Method to set the load type settings upon object instantiation
   *
   * @access private
   * @throws Exception if json could not be loaded
   */
  private function setLoadTypeSettings() {
    $jsonFile = $this->_resourcesPath.'load_type_import_settings.json';
    if (!file_exists($jsonFile)) {
      throw new Exception('Could not load load_type_import_settings configuration file for extension, contact your system administrator!');
    }
    $loadTypeSettingsJson = file_get_contents($jsonFile);
    $this->_loadTypeSettings = json_decode($loadTypeSettingsJson, true);
  }
}