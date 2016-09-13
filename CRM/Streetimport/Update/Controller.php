<?php
class CRM_Streetimport_Update_Controller extends CRM_Core_Controller {
  public function __construct($title = NULL, $action = CRM_Core_Action::NONE, $modal = TRUE) {
        parent::__construct($title, $modal);

    $stateMachine = new CRM_Core_StateMachine($this);
    $this->setStateMachine($stateMachine);
    $pages = array(
      'CRM_Streetimport_Update_Form_Define' => NULL,
      'CRM_Streetimport_Update_Form_PartTwo' => NULL,
      // 'CRM_Streetimport_Update_Form_PartThree' => NULL,
    );

    $stateMachine->addSequentialPages($pages, $action);
    $this->addPages($stateMachine, $action);
    $this->addActions();

  }
}
