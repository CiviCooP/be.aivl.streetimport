<?php
/**
 * Generic configuration class
 *  following Singleton pattern for specific extension configuration
 *
 * @author B. Endres (SYSTOPIA)
 * @date 30 April 2015
 * @license AGPL-3.0
 */
class CRM_Streetimport_Config {

  /** singleton instance for config */
  private static $_singleton = NULL;
  private static $_domain = NULL;

  /** the setting domain */
  private $domain = NULL;

  /** this will be loading the domain specific settings */
  protected $settings = NULL;

  /** will store strings for translation */
  protected $translatedStrings;

  /** will store strings for translation */
  private $error_activity_id = NULL;

  /** caches */
  private $_activityCompleteStatusId  = NULL;
  private $_activityScheduledStatusId = NULL;

  /**
   * Singleton method
   *
   * @param string $context to determine if triggered from install hook
   * @return CRM_Streetimport_Config
   * @access public
   * @static
   */
  public static function singleton() {
    if (!self::$_singleton) {
      $config_class = self::getClassPrefix() . 'Config';
      if (class_exists($config_class)) {
        // class exists, use that
        self::$_singleton = new $config_class();
      } else {
        // custom config class doesn't exist, use this one
        self::$_singleton = new CRM_Streetimport_Config();
      }
    }
    return self::$_singleton;
  }

  /**
   * returns the mode/domain this extension is running in.
   * In order to allow different organisations to use this extension, each
   * will have to define their respective subsets of specifications and
   * Settings.
   *
   * @return the currently set domain
   */
  public static function getDomain() {
    $domain = CRM_Core_BAO_Setting::getItem('StreetImporter', 'streetimporter_domain');
    if ($domain) {
      return $domain;

    } else {
      // if there's only one domain, return that
      $domains = self::getDomains();
      if (count($domains) == 1) {
        return reset($domains);
      } else {
        return NULL;
      }
    }
  }

  /**
   * returns the mode/domain this extension is running in.
   * In order to allow different organisations to use this extension, each
   * will have to define their respective subsets of specifications and
   * Settings.
   *
   */
  public static function setDomain($domain) {
    CRM_Core_BAO_Setting::setItem($domain, 'StreetImporter', 'streetimporter_domain');
  }

  /**
   * returns the mode/domain this extension is running in.
   * In order to allow different organisations to use this extension, each
   * will have to define their respective subsets of specifications and
   * Settings.
   *
   * @return the
   */
  public static function getClassPrefix() {
    return 'CRM_Streetimport_' . self::getDomain() . '_';
  }








  /**
   * Constructor method
   *
   * @param string $context
   */
  function __construct() {
    // load core data
    $this->domain = self::getDomain();
    $this->settings = civicrm_api3('Setting', 'getvalue', array(
      'name'  => 'streetimporter_settings',
      'group' => 'StreetImporter'));
    if (!is_array($this->settings)) {
      // make sure it exists
      $this->settings = array();
    }
    if (!isset($this->settings[$this->domain])) {
      // make sure it exists for the current domain
      $this->settings[$this->domain] = array();
    }
    // error_log("SETTINGS AFTER INIT: " . json_encode($this->settings));
  }

  /**
   * save the current settings to the DB
   */
  public function storeSettings() {
    // the following does NOT work, since it doesn't set the group,
    //  and we end up with different settings entries, one with one without group
    // civicrm_api3('Setting', 'create', array('streetimporter_settings' => $this->settings));

    // use the core function instead
    CRM_Core_BAO_Setting::setItem($this->settings, 'StreetImporter', 'streetimporter_settings');
  }

  /**
   * Read a certain setting value
   *
   * @param $setting_name    setting name as a string, or an array of strings as a path
   * @param $default_value   default value to be returned if setting not found
   * @param $settings        only for internal recursive use
   */
  public function getSetting($setting_name, $default_value = NULL, $settings = NULL) {
    if ($settings == NULL) $settings = $this->settings[$this->domain];
    // error_log("LOOKING FOR {$setting_name} IN " .json_encode($settings));
    if (is_string($setting_name) && isset($settings[$setting_name])) {
      // error_log("FOUND ".$settings[$setting_name]);
      return $settings[$setting_name];
    } elseif (is_array($setting_name) && !empty($setting_name)) {
      $key = array_shift($setting_name);
      $value = $this->getSetting($key, $default_value, $settings);
      if (count($setting_name) <= 0) {
        return $value;
      } else {
        return $this->getSetting($setting_name, $default_value, $value);
      }
    } else {
      // error_log("NOT FOUND ".$setting_name);
      return $default_value;
    }
  }

  /**
   * Write a setting value
   *  Don't forget to call storeSettings() after!
   *
   * @param $setting_name    setting name as a string, or an array of strings as a path
   * @param $value           value, can be anything (serialisable)
   * @param $settings        only for internal recursive use
   */
  public function setSetting($setting_name, $value, &$settings = NULL) {
    if ($settings == NULL) $settings = &$this->settings[$this->domain];
    // error_log("SETTING {$setting_name} TO: " . json_encode($value));

    if (is_string($setting_name)) {
      $settings[$setting_name] = $value;
      // TODO: store HERE
    } elseif (is_array($setting_name) && !empty($setting_name)) {
      $key = array_shift($setting_name);
      if (count($setting_name) <= 0) {
        return $this->setSetting($key, $value, $settings);
      } else {
        if (!isset($settings[$key])) {
          $settings[$key] = array();
        }
        $sub_settings = &$settings[$key];
        return $this->setSetting($setting_name, $value, $sub_settings);
      }
    }
  }

  /**
   * get the default set of handlers
   *
   * @return an array of handler instances
   */
  public function getHandlers($logger) {
    // this should be overwritten
    return array();
  }

  /**
   * get the default the DataSourceClass to work with
   *
   * @return a subclass of CRM_Streetimport_DataSource
   */
  public function getDataSourceClass($logger) {
    // this can be overwritten to provide a customized DataSourceClass
    return 'CRM_Streetimport_FileCsvDataSource';
  }

  /**
   * this can be overwritte to reject certain files
   *
   * @return TRUE if all is well.
   */
  public function validateHeaders($headers, $uri) {
    // by default: no validation
    return TRUE;
  }

  /**
   * This allows you to add extra settings to the
   * settings form.
   *
   * @return an array of the settings keys to be processed
   */
  public function buildQuickFormSettings($form) {
    // this should be overwritten
    return array();
  }

  /**
   * You can return a .tpl file path to include that into the
   * settings panel
   */
  public function getDomainSettingTemplate($form) {
    // this should be overwritten
    return NULL;
  }

  /**
   * get a list (id => name) of the relevant employees
   */
  public function getEmployeeList() {
    // default implementation: every contact with a user account
    $employees = array();
    $query = CRM_Core_DAO::executeQuery("
        SELECT
          civicrm_contact.id AS contact_id,
          civicrm_contact.display_name AS display_name
        FROM civicrm_contact
        LEFT JOIN civicrm_uf_match ON civicrm_uf_match.contact_id = civicrm_contact.id
        WHERE civicrm_contact.is_deleted = 0
          AND civicrm_uf_match.uf_id IS NOT NULL;");
    while ($query->fetch()) {
      $employees[$query->contact_id] = $query->display_name;
    }

    return $employees;
  }


  /**
   * Method to retrieve legal name
   *
   * @return string
   * @access public
   */
  public function getOrgLegalName() {
    return $this->getSetting('organisations_legal_name');
  }

  /**
   * This method offers translation of strings, such as
   *  - activity subjects
   *  - ...
   *
   * @param string $string
   * @return string
   * @access public
   */
  public function translate($string) {
    if (isset($this->translatedStrings[$string])) {
      return $this->translatedStrings[$string];
    } else {
      return ts($string);
    }
  }

  /**
   * Method to get the default activity status for import error
   *
   * @return int
   * @access public
   */
  public function getImportErrorActivityStatusId() {
    return CRM_Streetimport_Utils::getActivityStatusIdWithName('Scheduled');
  }

  /**
   * Method to get the default activity status for import error
   *
   * @return int
   * @access public
   */
  public function getActivityCompleteStatusId() {
    if ($this->_activityCompleteStatusId == NULL) {
      $this->_activityCompleteStatusId = CRM_Streetimport_Utils::getActivityStatusIdWithName('Completed');
    }
    return $this->_activityCompleteStatusId;
  }

  /**
   * Method to get the default activity status for import error
   *
   * @return int
   * @access public
   */
  public function getActivityScheduledStatusId() {
    if ($this->_activityScheduledStatusId == NULL) {
      $this->_activityScheduledStatusId = CRM_Streetimport_Utils::getActivityStatusIdWithName('Scheduled');
    }
    return $this->_activityScheduledStatusId;
  }

  /**
   * Method to retrieve import error activity type data
   *
   * @param string $key
   * @return mixed
   * @access public
   */
  public function getImportErrorActivityType() {
    if ($this->error_activity_id == NULL) {
      $this->error_activity_id = CRM_Core_OptionGroup::getValue('activity_type', 'streetimport_error', 'name');
      if (empty($this->error_activity_id)) {
        // couldn't be found => create
        $activity = civicrm_api3('OptionValue', 'create', array(
          'option_group_id' => 'activity_type',
          'name'            => 'streetimport_error',
          'label'           => $this->translate('Import Error'),
          'is_active'       => 1
          ));
        $this->error_activity_id = $activity['id'];
      }
    }
    return $this->error_activity_id;
  }

  /**
   * Method to retrieve the csv date format
   *
   * @return int
   * @access public
   */
  public function getCsvDateFormat() {
    return $this->getSetting('csv_date_format', '');
  }

  /**
   * Retrieves the file location of the given type.
   *
   * @param string $type
   *   One of "import", "processing", "processed", and "failed".
   *
   * @return string | NULL
   *   The file location path, or NULL when an invalid type was given.
   */
  public function getFileLocation($type) {
    switch ($type) {
      case 'import':
        return $this->getImportFileLocation();
      case 'processing':
        return $this->getProcessingFileLocation();
      case 'processed':
        return $this->getProcessedFileLocation();
      case 'failed':
        return $this->getFailFileLocation();
      default:
        return NULL;
    }
  }

  /**
   * Method to retrieve import file location
   *
   * @return string
   * @access public
   */
  public function getImportFileLocation() {
    return $this->getSetting('import_location');
  }

  /**
   * Method to retrieve location files DURING processing
   *
   * @return string
   * @access public
   */
  public function getProcessingFileLocation() {
    return $this->getSetting('processing_location');
  }

  /**
   * Method to retrieve location for processed file
   *
   * @return string
   * @access public
   */
  public function getProcessedFileLocation() {
    return $this->getSetting('processed_location');
  }

  /**
   * Method to retrieve location for files where processing has failed
   *
   * @return string
   * @access public
   */
  public function getFailFileLocation() {
    return $this->getSetting('failed_location');
  }

  /**
   * Method to get the default location type
   *
   * @return int
   * @access public
   */
  public function getLocationTypeId($contact_type = 'Individual') {
    return $this->getSetting('location_type_id');
  }

  /**
   * Method to get the extra location type
   *
   * @return int
   * @access public
   */
  public function getOtherLocationTypeId($contact_type = 'Individual') {
    return $this->getSetting('other_location_type_id');
  }

  /**
   * Method to get the default country id
   *
   * @return int
   * @access public
   */
  public function getDefaultCountryId() {
    return $this->getSetting('default_country_id');
  }

  /**
   * Method to get the default financial_type id
   *
   * @return int
   * @access public
   */
  public function getDefaultFinancialTypeId() {
    return $this->getSetting('default_financial_type_id');
  }

  /**
   * Method to get the gender id for males
   *
   * @return mixed
   * @access public
   */
  public function getMaleGenderId() {
    return $this->getSetting('male_gender_id');
  }

  /**
   * Method to get the gender id for females
   *
   * @return mixed
   * @access public
   */
  public function getFemaleGenderId() {
    return $this->getSetting('female_gender_id');
  }

  /**
   * Method to get the gender id for unknown
   *
   * @return mixed
   * @access public
   */
  public function getUnknownGenderId() {
    return $this->getSetting('unknown_gender_id');
  }

  /**
   * Method to get prefixes for household
   *
   * @return array
   * @access public
   */
  public function getHouseholdPrefixIds() {
    return $this->getSetting('household_prefix_id');
  }

  /**
   * Method to get the phone type id of phone
   *
   * @return int
   * @access public
   */
  public function getPhonePhoneTypeId() {
    return $this->getSetting('phone_phone_type_id');
  }

  /**
   * Method to get the phone type id of mobile
   *
   * @return int
   * @access public
   */
  public function getMobilePhoneTypeId() {
    return $this->getSetting('mobile_phone_type_id');
  }

  /**
   * Method to retrieve a list of values,
   * that will be interpreted as TRUE/POSITIVE/YES
   *
   * @return array
   * @access public
   */
  public function getAcceptedYesValues() {
    $value_list = $this->getSetting('accepted_yes_values', '1,yes,Yes,YES,Y,X,x');
    return explode(',', $value_list);
  }

  /**
   * Method to retrieve the default fundraiser contact
   * (assignee of activities)
   *
   * @return integer
   * @access public
   */
  public function getFundraiserContactID() {
    return $this->getSetting('fundraiser_id');
  }

  /**
   * Method to retrieve the default admin handler contact
   * (assignee of activities)
   *
   * @return integer
   * @access public
   */
  public function getAdminContactID() {
    return $this->getSetting('admin_id');
  }

  /**
   * Method to retrieve the newsletter group id
   *
   * @return int
   * @access public
   */
  public function getNewsletterGroupID() {
    return $this->getSetting('newsletter_group_id');
  }

  /**
   * Method to get the import error custom group (whole array or specific element)
   *
   * @param null $key
   * @return mixed
   * @access public
   */
  public function getImportErrorCustomGroup($key = null) {
    // TODO
    if (empty($key)) {
      return $this->importErrorCustomGroup;
    } else {
      return $this->importErrorCustomGroup[$key];
    }
  }

  /**
   * Method to get the custom fields for error type (whole array or specific field array)
   *
   * @param null $key
   * @return array
   * @access public
   */
  public function getImportErrorCustomFields($key = null) {
    // TODO
    if (empty($key)) {
      return $this->importErrorCustomFields;
    } else {
      foreach ($this->importErrorCustomFields as $field_id => $customField) {
        if ($customField['name'] == $key || $field_id==$key) {
          return $customField;
        }
      }
      // no such field
      return NULL;
    }
  }

  /**
   * get the list of available domains
   */
  public static function getDomains() {
    $domains = [];

    // scan all extension for potential DOMAINs
    $extension_query = civicrm_api3('Extension', 'get', [
        'sequential'   => 1,
        'is_active'    => 1,
        'option.limit' => 0,
        'return'       => 'status,path,key'
    ]);
    foreach ($extension_query['values'] as $extension) {
      if ($extension['key'] == 'be.aivl.streetimport') {
        // exclude ourselves
        continue;
      }

      if ($extension['status'] == 'installed') {
        $potential_base = $extension['path'] . DIRECTORY_SEPARATOR . 'CRM/Streetimport';
        if (is_dir($potential_base)) {
          // CRM_Core_Error::debug_log_message("Checking $potential_base");
          foreach (scandir($potential_base) as $domain_candidate) {
            // investiate the domain candidate
            if ($domain_candidate == '.' || $domain_candidate == '..') {
              continue;
            }

            try {
              $domain_config_file_candidate = $potential_base . DIRECTORY_SEPARATOR . $domain_candidate . DIRECTORY_SEPARATOR . 'Config.php';
              // CRM_Core_Error::debug_log_message("Testing $domain_config_file_candidate");
              if (file_exists($domain_config_file_candidate)) {
                // define the class
                $domain_config_class_candidate = "CRM_Streetimport_{$domain_candidate}_Config";

                if (!class_exists($domain_config_class_candidate)) {
                  // try to import it
                  include $domain_config_file_candidate;
                }

                // try to instantiate the class
                // CRM_Core_Error::debug_log_message("class $domain_config_class_candidate");
                if (   class_exists($domain_config_class_candidate)
                    && is_subclass_of($domain_config_class_candidate, 'CRM_Streetimport_Config')) {

                  // this all seems alright:
                  $domains[$domain_candidate] = $domain_candidate;
                }
              }
            } catch (Exception $ex) {
              // TODO: error handling or just ignore?
              CRM_Core_Error::debug_log_message("Exception: " . $ex->getMessage());
            }
          }
        }
        $extension_paths[] = $extension['path'];
      }
    }

    return $domains;
  }

  /**
   * get the ID of the current user
   */
  public function getCurrentUserID() {
    $session = CRM_Core_Session::singleton();
    $user_id = $session->get('userID');
    if ($user_id) {
      return $user_id;
    } else {
      // FIXME: is there a fallback?
      return 1;
    }
  }

  /**
   * Should processing of the whole file stop if no handler
   * was found for a line?
   */
  public function stopProcessingIfNoHanderFound() {
    return FALSE;
  }

  /**
   * Should it be possible to allow the same line to be processed by
   *  multiple handlers? If NO it will be stopped after the first match.
   */
  public function allowProcessingByMultipleHandlers() {
    return FALSE;
  }
}
