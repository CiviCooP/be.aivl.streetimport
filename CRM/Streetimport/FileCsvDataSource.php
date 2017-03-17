<?php
/**
 * This importer will take a local csv file parse individual records
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
class CRM_Streetimport_FileCsvDataSource extends CRM_Streetimport_DataSource {

  protected $delimiter = ';';
  protected $encoding = 'UTF8';

  /** this will hold the open file */
  protected $reader  = NULL;

  /** this will hold the open file */
  protected $header  = NULL;

  /** this will hold the record to be delivered next */
  protected $next    = NULL;
  protected $line_nr = 0;

  public function __construct($uri, $logger, $mapping = NULL) {
    CRM_Streetimport_DataSource::__construct($uri, $logger, $mapping);
    $config = CRM_Streetimport_Config::singleton();
    $this->encoding = $config->getSetting('import_encoding', 'UTF8');
    $this->delimiter = $config->getSetting('import_delimiter', ';');
  }

  /**
   * Will reset the status of the data source
   */
  public function reset() {
    $config = CRM_Streetimport_Config::singleton();

    $this->validate_separator();
    $this->validate_encoding();
    // try loading the given file
    $this->reader  = fopen($this->uri, 'r');
    $this->header  = NULL;
    $this->next    = NULL;
    $this->line_nr = 0;

    if (empty($this->reader)) {
      // TODO: error handling
      $this->logger->abort($config->translate("Unable to read file")." ".$this->uri);
      $this->reader = NULL;
      return;
    }

    // read header
    $this->header = fgetcsv($this->reader, 0, $this->delimiter);
    $this->convert($this->header);
    if ($this->header == NULL) {
      // TODO: error handling
      $this->logger->abort($config->translate("File")." ".$this->uri." ".$config->translate("does not contain headers"));
      $this->reader = NULL;
      return;
    }
    else {
      // validate the header
      if ($config->validateHeaders($this->header, $this->uri) == FALSE) {
        return;
      }
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
    $data = fgetcsv($this->reader, 0, $this->delimiter);
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
      $this->convert($record);
      $this->next = $this->applyMapping($record);

      // add some meta data
      if (!isset($this->next['id']))      $this->next['id']      = $this->line_nr;
      if (!isset($this->next['line_nr'])) $this->next['line_nr'] = $this->line_nr;
      if (!isset($this->next['source']))  $this->next['source']  = $this->uri;
    }
  }
  /**
   * Function to validate which csv separator to use. Only ';' is allowed
   */
  function validate_separator() {
    $config = CRM_Streetimport_Config::singleton();
    $testSeparator = fopen($this->uri, 'r');
    if ($testRow = fgetcsv($testSeparator, 0, ';')) {
      if (!isset($testRow[1])) {
        $this->logger->abort($config->translate("File")." ".$this->uri." "
            .$config->translate("does not have '{$this->delimiter}' as a field delimiter and can not be processed"));
        $this->reader = NULL;
      } else {
        $this->delimiter = ";";
      }
    }
    fclose($testSeparator);
  }

  /**
   * check whether the input file complies with the given encoding
   */
  function validate_encoding() {
    $config = CRM_Streetimport_Config::singleton();
    if (!mb_check_encoding(file_get_contents($this->uri), $this->encoding)) {
      $this->logger->abort($config->translate("File")." ".$this->uri." "
        .$config->translate("is not encoded as {$this->encoding} and can not be processed"));
      $this->reader = NULL;
    }
  }

  /**
   * convert every string in the record (array) to the given encoding
   */
  public function convert(&$record) {
    $keys = array_keys($record);
    foreach ($keys as $key) {
      $record[$key] = mb_convert_encoding($record[$key], 'UTF8', $this->encoding);
    }
  }
}
