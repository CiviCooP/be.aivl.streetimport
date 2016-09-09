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
        CRM_Utils_System::setTitle(ts('Updating batch'));
        $this->assign('elementNames', $this->getRenderableElementNames());
        $this->assign('importBatchId', $this->get('importBatchId'));

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
      // The _elements list includes some items which should not be
    // auto-rendered in the loop -- such as "qfKey" and "buttons".  These
    // items don't have labels.  We'll identify renderable by filtering on
    // the 'label'.
    $elementNames = array();
      foreach ($this->_elements as $element) {
          /* @var HTML_QuickForm_Element $element */
      $label = $element->getLabel();
          if (!empty($label)) {
              $elementNames[] = $element->getName();
          }
      }

      return $elementNames;
  }
}
