<?php
/**
 * Abstract streetimport data source
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
abstract class CRM_Streetimport_DataSource {

  /**
   * this array holds an array,
   *  mapping the source's attributes to the internally understood ones
   */
  protected $mapping = NULL;

  /**
   * the URI identifies each data source
   */
  protected $uri = NULL;

  public function __construct($uri, $mapping=NULL) {
    $this->uri = $uri;
    if ($mapping==NULL) {
      // TODO: LOAD default mapping
      $mapping = 
    }
  }

}