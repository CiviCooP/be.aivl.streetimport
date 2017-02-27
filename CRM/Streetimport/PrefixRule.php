<?php
/**
 * Class handling the prefix rules for streetimport
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 23 Feb 2017
 * @license AGPL-3.0
 */
class CRM_Streetimport_PrefixRule {

  private $_resourcePath = NULL;
  private $_prefixRules = array();
  private $_maleGenderId = NULL;
  private $_femaleGenderId = NULL;
  private $_unknownGenderId = NULL;

  /**
   * CRM_Streetimport_PrefixRule constructor.
   */
  function __construct() {
    // get gender option values
    try {
      $genders = civicrm_api3('OptionValue', 'get', array(
        'option_group_id' => 'gender',
        'options' => array('limit' => 0)));
      foreach ($genders['values'] as $gender) {
        switch ($gender['name']) {
          case "Male":
            $this->_maleGenderId = $gender['value'];
            break;
          case "Female":
            $this->_femaleGenderId = $gender['value'];
            break;
          case "Transgender":
            $this->_unknownGenderId = $gender['value'];
        }
      }
    } catch (CiviCRM_API3_Exception $ex) {}
    $settings = civicrm_api3('Setting', 'Getsingle', array());
    $this->_resourcePath = $settings['extensionsDir'].'/be.aivl.streetimport/resources/';
    $this->getFromJson();
  }

  /**
   * Method to get the prefix rules from the json file
   *
   * @throws Exception when json file not found
   */
  private function getFromJson() {
    $jsonFile = $this->_resourcePath.'prefix_rules.json';
    if (!file_exists($jsonFile)) {
      throw new Exception('Could not load prefix_rules configuration file for extension in '.__METHOD__.
        ', contact your system administrator!');
    }
    $prefixRulesJson = file_get_contents($jsonFile);
    $this->_prefixRules = json_decode($prefixRulesJson, true);
  }

  /**
   * Method to get the prefix rule with the import prefix
   *
   * @param $importPrefix
   * @return array|mixed
   */
  public function getWithImportPrefix($importPrefix) {
    $result = array();
    foreach ($this->_prefixRules as $prefixRuleId => $prefixRule) {
      if ($prefixRule['import_prefix'] == $importPrefix) {
        $result = $prefixRule;
      }
    }
    return $result;
  }

  /**
   * Method to get the prefix rule with the civicrm prefix
   *
   * @param $civicrmPrefix
   * @return array|mixed
   */
  public function getWithCiviCRMPrefix($civicrmPrefix) {
    $result = array();
    foreach ($this->_prefixRules as $prefixRuleId => $prefixRule) {
      if ($prefixRule['civicrm_prefix'] == $civicrmPrefix) {
        $result = $prefixRule;
      }
    }
    return $result;
  }

  /**
   * Method to get all the prefix rules
   * @return array
   */
  public function get() {
    return $this->_prefixRules;
  }

  /**
   * Method to add a new prefix rule
   *
   * @param $params
   * @return bool
   * @throws Exception when params
   */
  public function add($params) {
    if (empty($params)) {
      throw new Exception(ts('Params can not be empty when adding a prefix rule in').' '.__METHOD__.', '
        .ts('contact your system administrator'));
    }
    if (!isset($params['import_prefix']) || !isset($params['civicrm_prefix']) || !isset($params['gender'])) {
      throw new Exception(ts('Params import_prefix, civicrm_prefix and gender are mandatory when adding a prefix rule in')
        .' '.__METHOD__.', '.ts('contact your system administrator'));
    }
    $this->_prefixRules[] = array(
      'import_prefix' => $params['import_prefix'],
      'civicrm_prefix' => $params['civicrm_prefix'],
      'gender' => $params['gender']
    );
    // write to the json file
    $this->writeToJson();
    return TRUE;
  }

  /**
   * Method to delete prefix rule with id
   *
   * @param $prefixRuleId
   * @return bool
   */
  public function deleteById($prefixRuleId) {
    if (empty($prefixRuleId)) {
      return FALSE;
    }
    unset($this->_prefixRules[$prefixRuleId]);
    // write to the json file
    $this->writeToJson();
    return TRUE;
  }

  /**
   * Method to get the gender id with the import prefix
   *
   * @param $importPrefix
   * @return null
   */
  public function getGenderIdWithImportPrefix($importPrefix) {
    if (!empty($importPrefix)) {
      $prefixRule = $this->getWithImportPrefix($importPrefix);
      if (!empty($prefixRule)) {
        switch ($prefixRule['gender']) {
          case 'female':
            return $this->_femaleGenderId;
            break;
          case 'male':
            return $this->_maleGenderId;
            break;
          default:
            return $this->_unknownGenderId;
            break;
        }
      }

    }
    return $this->_unknownGenderId;
  }

  /**
   * Method to update the prefix rules with values from params
   *
   * @param $params
   */
  public function update($params) {
    if (!empty($params)) {
      foreach ($params as $key => $value) {
        if (isset($this->_prefixRules[$key])) {
          $this->_prefixRules[$key]['value'] = $value;
        }
      }
    }
    // write to the json file
    $this->writeToJson();
  }

  /**
   * Method to write the prefix rules to the json file
   *
   * @throws Exception if unable to write the json file
   */
  private function writeToJson() {
    $fileName = $this->_resourcePath . 'prefix_rules.json';
    try {
      $fh = fopen($fileName, 'w');
      fwrite($fh, json_encode($this->_prefixRules, JSON_PRETTY_PRINT));
      fclose($fh);
    } catch (Exception $ex) {
      throw new Exception('Could not open prefix_rules.json in '.__METHOD__
        .', contact your system administrator. Error reported: ' . $ex->getMessage());
    }
  }

  /**
   * Method to check if there is already a prefix rule for the import prefix
   *
   * @param string $importPrefix
   * @return bool
   */
  public function importPrefixExists($importPrefix) {
    foreach ($this->_prefixRules as $prefixRuleId => $prefixRule) {
      if ($prefixRule == $importPrefix) {
        return TRUE;
      }
    }
    return FALSE;
  }
}