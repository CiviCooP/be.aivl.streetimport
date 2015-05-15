<?php
/**
 * Class following Singleton pattern for specific extension configuration
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 30 April 2015
 * @license AGPL-3.0
 */

class CRM_Streetimport_Config {
  private static $_singleton;
  protected $streetRecruitActType = array();
  protected $welcomeCallActType = array();
  protected $donorErrorActType = array();
  protected $streetRecruitErrorActType = array();
  protected $welcomeCallErrorActType = array();

  /**
   * Constructor method
   */
  function __construct() {
    $this->setActTypes();
  }

  /**
   * Singleton method
   *
   * @return CRM_Streetimport_Config
   * @access public
   * @static
   */
  public static function singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Streetimport_Config();
    }
    return self::$_singleton;
  }


}