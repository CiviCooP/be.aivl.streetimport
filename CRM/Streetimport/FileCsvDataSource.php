<?php
/**
 * This importer will take a local csv file parse individual records
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
class CRM_Streetimport_FileCsvDataSource extends CRM_Streetimport_DataSource {

  protected $default_delimiter = ',';
  protected $default_encoding  = 'UTF8';
  
  /** this will hold the open file */
  protected $reader  = NULL;

  /** this will hold the open file */
  protected $header  = NULL;

  /** this will hold the record to be delivered next */
  protected $next    = NULL;
  protected $line_nr = 0;

  /**
   * Will reset the status of the data source
   */
  public function reset() {
    // try loading the given file
    $this->reader  = fopen($this->uri, 'r');
    $this->header  = NULL;
    $this->next    = NULL;
    $this->line_nr = 0;

    if (empty($this->reader)) {
      // TODO: error handling
      $this->logger->abort("Unable to read file '{$this->uri}'.");
      $this->reader = NULL;
      return;
    }

    // read header
    $this->header = fgetcsv($this->reader, 0, $this->default_delimiter);
    if ($this->header == NULL) {
      // TODO: error handling
      $this->logger->abort("File '{$this->uri}' does not contain headers.");
      $this->reader = NULL;
      return;
    }

    // prepare the next record
    $this->loadNext();
  }

  /**
   * Check if there is (more) records available
   *
   * @return true if there is more records available via next()
   */
  public function hasNext() {
    return ($this->next != NULL);
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

  /**
   * will load the next data record from the file
   */
  protected function loadNext() {
    if ($this->reader == NULL) {
      // either not initialised or complete...
      return NULL;
    }

    // read next data blob
    $this->next = NULL;
    $this->line_nr += 1;
    $data = fgetcsv($this->reader, 0, $this->default_delimiter);
    if ($data == NULL) {
      // there is no more records => reset
      fclose($this->reader);
      $this->reader = NULL;
    } else {
      // data blob read, build record
      $record = array();
      foreach ($this->header as $index => $key) {
        if (isset($data[$index])) {
          $record[$key] = $data[$index];
        }
      }
      $this->next = $this->applyMapping($record);

      // set ID if not defined by file/mapping
      if (empty($this->next['__id'])) $this->next['__id'] = $this->line_nr;      
    }
  }
}