<?php

/**
 * Collection of upgrade steps.
 */
class CRM_Streetimport_Upgrader extends CRM_Streetimport_Upgrader_Base {

  public function install() {
    $jsonFile = file_get_contents(dirname(__FILE__) . '/resources/activity_types.json');
    $activityTypes = json_decode($jsonFile, TRUE);
    foreach ($activityTypes as $name => $label) {
      CRM_Streetimport_Utils::createActivityType(array('name' => $name, 'label' => $label));
    }
  }
}
