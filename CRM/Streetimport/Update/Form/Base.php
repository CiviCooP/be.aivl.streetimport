<?php

require_once 'CRM/Core/Form.php';

/**
 * Base class for update form (might come in handy!).
 *
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC43/QuickForm+Reference
 */
class CRM_Streetimport_Update_Form_Base extends CRM_Core_Form
{
    public function buildQuickForm()
    {
        CRM_Utils_System::setTitle(ts('Update import'));
        $this->assign('elementNames', $this->getRenderableElementNames());
        $this->assign('batchId', $this->get('batch')->getId());

        parent::buildQuickForm();
    }

    public function postProcess()
    {
        parent::postProcess();
    }

  /**
   * Get the fields/elements defined in this form.
   *
   * @return array (string)
   */
  public function getRenderableElementNames()
  {
    $elementNames = array();
      foreach ($this->_elements as $element) {
      $label = $element->getLabel();
          if (!empty($label)) {
              $elementNames[] = $element->getName();
          }
      }

      return $elementNames;
  }
}
