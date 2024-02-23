<?php

class CRM_Streetimport_Updater{

  private $entity;
  private $type;

  function setEntity($entity, $type = null){

    //check to see if the entity exists
    $entities = civicrm_api3('Entity', 'get');
    if(!in_array($entity, $entities['values'])){
      throw new Exception("'$entity' is not an entity registered in the API.");
    }
// none of the following makes sense to me:
//    // $result = civicrm_api3($entity, 'getactions');
//    // exit;
//    // $availableActions=$result['values'];
//    // $necessaryActions = array('get', 'create', 'getfields');
//    // $missingActions = array_diff($necessaryActions, $availableActions);
//    if(0 && $missingActions){
//      $missingActionsString = implode($missingActions, ',');
//      throw new Exception("Cannot update entity '$entity' as it does not have the implement the following API actions: {$missingActionsString}");
//    } else {
//      $this->entity = $entity;
//    }
    $this->entity = $entity;
    if($type){
      $this->setType($type);
    }else{
      $this->type = $this->typeId = NULL;
    }
  }

  private function setType($type){
    if(!$this->entity){
      throw new Exception("Please set the entity before attempting to set the type", 1);
    }

    // Some types are stored in an associated [Entity]Type entity.
    // Others are defined with OptionGroup and OptionValue
    // We need to cater for both

    // Check to see if [Entity]Type is a registered Entity
    $entities = civicrm_api3('Entity', 'get');
    if(
      in_array("{$this->entity}Type", $entities['values']) &&
      $this->entity != 'Activity'  // Ensure that we don't use the depcrecated ActivityType API
    ){
      $result = civicrm_api3("{$this->entity}Type", 'get', array('name' => $type));
      if($result['count']==1){
        $this->type = $type;
        $this->typeId = $result['id'];
      }else{
        throw new Exception("'$type' is not a type of {$this->entity}.");
      }
    } else {
      // Else, check to see if 'entity_type' is an option_group
      $result = civicrm_api3("OptionGroup", 'get', array('name' => "{$this->entity}_type"));
      if($result['count']==1){
        $result = civicrm_api3("OptionValue", 'get', array('option_group_id' => $result['id'], 'name' => $type));
        if($result['count']==1){
          $this->type = $type;
          $this->typeId = $result['values'][$result['id']]['value'];
        }else{
          throw new Exception("'$type' is not a type of {$this->entity}.");
        }
      }
    }
  }

  function setEntityIds($entityIds){
    $this->entityIds = $entityIds;

  }

  function setUpdate($update){
    $this->update = $update;
  }

  function getEntity(){
    return $this->entity;
  }

  function getType(){
    return $this->type;
  }

  function getTypeId(){
    return $this->typeId;
  }

  function getUpdatableFields(){
    $result = civicrm_api3($this->getEntity(), 'getfields');
    // Filter custom fields and the ID field
    $fields = array_filter ($result['values'], function($k){return substr($k, 0, 7) != 'custom_' && $k != 'id';}, ARRAY_FILTER_USE_KEY);

    // Get custom field groups that have been defined for this entity
    $result = civicrm_api3('CustomGroup', 'get', array('extends' => $this->getEntity()));
    $customGroups=$result['values'];

    //  If this is a contact type or subtype, we need to manually add extra custom groups

    //  If the entity is Contact and a type has been defined
    if($this->getEntity() == 'Contact' && $this->getType()){
      if(in_array($this->getType(), array('Individual','Household','Organization'))){
        $result = civicrm_api3('CustomGroup', 'get', array('extends' => $this->getType()));
      }else{
        $result = civicrm_api3('ContactType', 'getsingle', array('name' => $this->getType()));
        $result = civicrm_api3('ContactType', 'getsingle', array('id' => $result['parent_id']));
        $result = civicrm_api3('CustomGroup', 'get', array('extends' => $result['name']));
      }
      $customGroups = $customGroups + $result['values'];
    }

    $customGroupsToAdd = [];
    foreach($customGroups as $customGroup){
      // If this is extending a specific type of entity
      if(isset($customGroup['extends_entity_column_value'])){
        // check that it is the one we are concerned with
        if(
          in_array($this->getTypeId(), $customGroup['extends_entity_column_value']) ||
          in_array($this->getType(), $customGroup['extends_entity_column_value'])
        ){
          $customGroupsToAdd[]=$customGroup['id'];
        }// only add if the type matches
      }else{
        // add since it applies to all activities
        $customGroupsToAdd[]=$customGroup['id'];
      }
    }

    foreach($customGroupsToAdd as $customGroup){
      foreach(civicrm_api3('customField', 'get', array('custom_group_id' => $customGroup))['values'] as $key => $field){
        $fields['custom_'.$key] = $field;
      }
    }

    return $fields;
  }

    // Get custom fields that have been defined for specific types of the entity


  function run(){
    foreach($this->entityIds as $id){
      $params = array_merge($this->update, array('id' => $id));
      var_dump($params);
      civicrm_api3($this->getEntity(), 'create', $params);
    }
  }

}
