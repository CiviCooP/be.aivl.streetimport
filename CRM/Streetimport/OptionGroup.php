<?php
/*-------------------------------------------------------+
| SYSTOPIA - LEGACY CODE INLINE-REPLACEMENTS             |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

/**
 * This class offers in-line code replacements for deprecated/dropped functions
 *  of the CRM_Core_OptionGroup class
 */
class CRM_Streetimport_OptionGroup {
  /**
   * Get an option value from an option group
   *
   * This function was specifically introduced as 1:1 replacement
   *  for the deprecated CRM_Core_OptionGroup::getValue function
   *
   * @param string $groupName
   *   name of the group
   *
   * @param $label
   *   label/name of the requested option value
   *
   * @param string $label_field
   *   field to look in for the label, e.g. 'label' or 'name'
   *
   * @param string $label_type
   *   *ignored*
   *
   * @param string $value_field
   *   *ignored*
   *
   * @return string
   *   value of the OptionValue entity if found
   *
   * @throws Exception
   */
  public static function getValue($group_name, $label, $label_field = 'label', $label_type = 'String', $value_field = 'value')
  {
    if (empty($label) || empty($group_name)) {
      return NULL;
    }

    // build/run API query
    $value = civicrm_api3('OptionValue', 'getvalue', [
      'option_group_id' => $group_name,
      $label_field => $label,
      'return' => $value_field
    ]);

    // anything else to do here?
    return (string) $value;
  }

  /**
   * Get an option label from an option value
   *
   * This function was specifically introduced as 1:1 replacement
   *  for the deprecated CRM_Core_OptionGroup::getLabel function
   *
   * @param string $group_name
   *   name of the group
   *
   * @param string|int $value
   *   option value
   *
   * @param bool $onlyActiveValue
   *   should only active values be returned?
   *
   * @return string|null
   *   label of the queried option value
   */
  public static function getLabel($group_name, $value, $onlyActiveValue = TRUE) {
    if (empty($groupName) || $value === '' || $value === null) {
      return NULL;
    }

    // build/run API query
    $api_query = [
      'option_group_id' => $group_name,
      'value' => $value,
      'return' => 'label'
    ];

    if ($onlyActiveValue) {
      $api_query['is_active'] = 1;
    }

    try {
      return civicrm_api3('OptionValue', 'getvalue', $api_query);
    } catch (Exception $ex) {
      Civi::log()->debug("CRM_Legacycode_OptionGroup::getLabel exception for value '{$value}' in group '{$group_name}': "
          . $ex->getMessage());
      return null;
    }
  }
}
