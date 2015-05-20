<?php
/**
 * This importer will take a file and 
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
class CRM_Streetimport_FileCsvDataSource extends CRM_Streetimport_CsvDataSource {

  protected $default_delimiter = ';';
  protected $default_encoding  = 'UTF8';
  
  /** this will hold the open file */
  protected $reader = NULL;

  /** this will hold the open file */
  protected $header = NULL;

  /** this will hold the record to be delivered next */
  protected $next   = NULL;

  /**
   * Will reset the status of the data source
   */
  public function reset() {
    // try loading the given file
    $this->reader = fopen($this->url, 'r');

    // read header
    $this->header = fgetcsv($this->reader, 0, $this->default_delimiter);
    error_log(print_r($this->header, 1));
    // foreach ($line as $item) {
    //   array_push($decoded_line, mb_convert_encoding($item, mb_internal_encoding(), $this->default_encoding));
    // }
  }

  /**
   * Check if there is (more) records available
   *
   * @return true if there is more records available via next()
   */
  public function hasNext() {
    return ($next != NULL);
  }

  /**
   * Get the next record
   *
   * @return array containing the record
   */
  public function next() {
    if ($this->hasNext()) {
      $record = $this->next;
      $this->loadNext();
      return $record;
    } else {
      return NULL;
    }
  }

  protected function loadNext() {
    $this->next = NULL;
  }
}