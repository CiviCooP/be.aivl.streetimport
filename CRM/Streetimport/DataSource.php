<?php
/**
 * Abstract streetimport data source
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
abstract class CRM_Streetimport_DataSource {

  /**
   * stores the result/logging object
   */ 
  public $logger = NULL;

  /**
   * this array holds an array,
   *  mapping the source's attributes to the internally understood ones
   */
  protected $mapping = NULL;

  /**
   * the URI identifies each data source
   */
  protected $uri = NULL;

  public function __construct($uri, $logger, $mapping = NULL) {
    $this->logger = $logger;
    $this->uri = $uri;
    if ($mapping == NULL) {
      // load default mapping
      $config = CRM_Streetimport_Config::singleton();
      $resourcesPath = $config->getResourcesPath();
      $mappings_path = $resourcesPath.'default_mapping.json';
      $mappings_content = file_get_contents($mappings_path);
      $mapping = json_decode($mappings_content, true);
    }
    $this->mapping = $mapping;
  }

  /**
   * Will reset the status of the data source
   */
  public abstract function reset();

  /**
   * Check if there is (more) records available
   *
   * @return true if there is more records available via next()
   */
  public abstract function hasNext();

  /**
   * Get the next record
   *
   * @return array containing the record
   */
  public abstract function next();

  /**
   * transforms the given record's keys accorting to $this->mapping
   * missing keys in the mapping will be treated according to the $restrict parameter
   * mappings to null or '' will be removed in any case
   *
   * @param $record    an array with the data
   * @param $restrict  if true, only the fields specified in the mapping will be copied
   *
   * @return array|bool a new array with the transformed keys
   */
  protected function applyMapping($record, $restrict=false) {
    $new_record = array();
    foreach ($record as $key => $value) {
      if (isset($this->mapping[$key])) {
        $new_key = $this->mapping[$key];
      } else {
        if ($restrict) {
          continue;
        } else {
          $new_key = $key;
        }
      }
      $new_record[$new_key] = $value;
    }
    // check if we need to do specific and weird date formatting because the incoming file has strange stuff
    $new_record = $this->checkDateFormatting($new_record);
    if ($new_record) {
      return $new_record;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Method to fix the date formatting issues at central import level
   *
   * @param $record
   * @return array|bool
   */
  private function checkDateFormatting($record) {
    $datesInRecord = ['Birth date', 'Recruitment Date', 'Import Date', 'Start Date', 'End Date'];
    foreach ($datesInRecord as $dateFieldName) {
      if (isset($record[$dateFieldName]) && !empty($record[$dateFieldName])) {
        $timeToBeFixed = NULL;
        $inParts = explode(' ', $record[$dateFieldName]);
        $dateToBeFixed = $inParts[0];
        if (isset($inParts[1])) {
          $timeToBeFixed = $inParts[1];
        }
        $dateToBeFixed = str_replace('/', '-', $dateToBeFixed);
        $dateToBeFixed = str_replace('.', '-', $dateToBeFixed);
        $dateToBeFixed = str_replace('_', '-', $dateToBeFixed);
        $dateToBeFixed = str_replace(':', '-', $dateToBeFixed);
        $dateToBeFixed = str_replace(',', '-', $dateToBeFixed);
        // assuming last part is year and if it is not 4 digits, put 4 digits!!!!!
        $dateParts = explode('-', $dateToBeFixed);
        if (isset($dateParts[2]) && strlen($dateParts[2]) == 2 && $dateFieldName != 'Birth date') {
          $dateToBeFixed = $dateParts[0] . '-' . $dateParts[1] . '-20' . $dateParts[2];
          $this->logger->logWarning(CRM_Streetimport_Config::singleton()->translate("Incoming date from streetrecruitment with 2 digit year rather than 4 digit year, assumed format is dd-mm-jj, inserted 20 before year. Incoming date was " . $record[$dateFieldName] . ' translated to ' . $dateToBeFixed), $record);
        }
        if (isset($dateParts[2]) && strlen($dateParts[2]) == 2 && $dateFieldName == 'Birth date') {
          $this->logger->logFatal(CRM_Streetimport_Config::singleton()->translate("Birth date from streetrecruitment with 2 digit year rather than 4 digit year, needs manual correction!!!! Incoming date was " . $record[$dateFieldName]), $record, 'Birth date wrong format');
          //return FALSE;
        }
        if (!empty($timeToBeFixed)) {
          $record[$dateFieldName] = $dateToBeFixed . ' ' . $timeToBeFixed;
        }
        else {
          $record[$dateFieldName] = $dateToBeFixed;
        }
      }
    }
    return $record;
  }

}