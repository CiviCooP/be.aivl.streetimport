<?php

class CRM_Streetimport_UpdatableFields{

  var $cache = array();

  function get($entity, $type){

    $actions = civicrm_api3($entity, 'getactions');
    var_dump($actions);
  }

}
