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

  public function __construct($uri, $logger, $mapping=NULL) {
    $this->logger = $logger;
    $this->uri = $uri;
    if ($mapping==NULL) {
      // load default mapping
      // TODO: move to config
      $settings = civicrm_api3('Setting', 'Getsingle', array());
      $mappings_path = $settings['extensionsDir'].'/be.aivl.streetimport/resources/default_mapping.json';
      $mappings_contenct = file_get_contents($mappings_path);
      $mapping = json_decode($mappings_contenct, true);
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
   * @return a new array with the transformed keys
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
    return $new_record;
  }
}