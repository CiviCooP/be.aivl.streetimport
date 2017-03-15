<?php
/*-------------------------------------------------------------+
| GP StreetImporter Record Handlers                            |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/**
 * Class following Singleton pattern for specific extension configuration
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 30 April 2015
 * @license AGPL-3.0
 */
class CRM_Streetimport_GP_Config extends CRM_Streetimport_Config {

  /**
   * Constructor method
   *
   * @param string $context
   */
  function __construct() {
    CRM_Streetimport_Config::__construct();

  }

  /**
   * get the default set of handlers
   *
   * @return an array of handler instances
   */
  public function getHandlers($logger) {
    return array(
      new CRM_Streetimport_GP_Handler_TEDITelephoneRecordHandler($logger),
      new CRM_Streetimport_GP_Handler_TEDIContactRecordHandler($logger),
    );
  }



}
