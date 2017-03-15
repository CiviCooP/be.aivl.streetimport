<?php
/*-------------------------------------------------------------+
| GP StreetImporter Record Handlers                            |
| Copyright (C) 2017 SYSTOPIA                                  |
| Author: M. McAndrew (michaelmcandrew@thirdsectordesign.org)  |
|         B. Endres (endres -at- systopia.de)                  |
| http://www.systopia.de/                                      |
+--------------------------------------------------------------*/

/*
* Settings metadata file
*/

return array(
  'streetimporter_domain' => array(
    'group_name' => 'StreetImporter',
    'group' => 'StreetImporter',
    'name' => 'streetimporter_domain',
    'type' => 'String',
    'default' => "GP",
    'add' => '4.6',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'StreetImporter domain',
    'help_text' => 'StreetImporter is only a framework. This settings defines wich specific implementation is currently active',
  ),
  'streetimporter_settings' => array(
    'group_name' => 'StreetImporter',
    'group' => 'StreetImporter',
    'name' => 'streetimporter_settings',
    'type' => 'Array',
    'default' => '',
    'add' => '4.6',
    'is_domain' => 1,
    'is_contact' => 0,
    'description' => 'StreetImporter domain',
    'help_text' => 'StreetImporter is only a framework. This settings defines wich specific implementation is currently active',
  )
 );