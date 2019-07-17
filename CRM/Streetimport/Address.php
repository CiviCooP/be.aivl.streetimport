<?php
/**
 * Class handling the address processing for streetimport
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 25 Apr 2019
 * @license AGPL-3.0
 */
class CRM_Streetimport_Address {

  private $_defaultLocationTypeId = NULL;

  /**
   * CRM_Streetimport_Contact constructor.
   */
  function __construct() {
    $this->_defaultLocationTypeId = CRM_Streetimport_Config::singleton()->getDefaultLocationTypeId();
  }
  /**
   * Method to create address
   *
   * @param array $addressData
   * @return bool|array
   */
  public function create($addressData) {
    if ($this->isValidAddressData($addressData)) {
      // only if the incoming address does not already exist with the same data
      if (!$this->alreadyExists($addressData)) {
        if ($this->alreadyHasAddressOfLocationType($addressData['contact_id'], $addressData['location_type_id'])) {
          $this->deleteAddressOfLocationType($addressData['contact_id'], $addressData['location_type_id']);
        }
        try {
          $address = civicrm_api3('Address', 'create', $addressData);
          return $address;
        }
        catch (CiviCRM_API3_Exception $ex) {
          Civi::log()->error(ts('Error when saving address with API Address create in ') . __METHOD__
            . ts(', error message from API: ') . $ex->getMessage());
          return FALSE;
        }
      }
      else {
        return TRUE;
      }
    }
  }

  /**
   * Method to check if the address already exists based on a few relevant check fields
   *
   * @param $newAddressData
   * @return bool
   */
  private function alreadyExists($newAddressData) {
    $checkFields = ['street_address', 'city', 'postal_code'];
    // first get current addresses of the contact
    try {
      $currentAddresses = civicrm_api3('Address', 'get', [
        'contact_id' => $newAddressData['contact_id'],
        'options' => ['limit' => 1],
        'sequential' => 1,
      ]);
      foreach ($currentAddresses['values'] as $currentAddress) {
        $sameAddress = TRUE;
        foreach ($checkFields as $checkField) {
          // if either current or new is not defined, different
          if (!isset($currentAddress[$checkField]) || !isset($newAddressData[$checkField])) {
            $sameAddress = FALSE;
          }
          else {
            $currentValue = strtolower(trim($currentAddress[$checkField]));
            $newValue = strtolower(trim($newAddressData[$checkField]));
            if ($currentValue != $newValue) {
              $sameAddress = FALSE;
            }
          }
        }
        if ($sameAddress) {
          return TRUE;
        }
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
    }
    return FALSE;
  }

  /**
   * Method to delete an address of a contact and a specific location type
   *
   * @param $contactId
   * @param $locationTypeId
   */
  private function deleteAddressOfLocationType($contactId, $locationTypeId) {
    try {
      $id = civicrm_api3('Address', 'getvalue', [
        'return' => 'id',
        'contact_id' => $contactId,
        'location_type_id' => $locationTypeId,
        'options' => ['limit' => 1],
      ]);
      try {
        civicrm_api3('Address', 'delete', ['id' => $id]);

      }
      catch (CiviCRM_API3_Exception $ex) {
        Civi::log()->error(ts('Unexpected error from API Address delete in ') . __METHOD__ . ts(', error message from API: ') . $ex->getMessage());
      }

    }
    catch (CiviCRM_API3_Exception $ex) {
      Civi::log()->error(ts('Unexpected error from API Address getvalue in ') . __METHOD__ . ts(', error message from API: ') . $ex->getMessage());
    }
  }

  /**
   * Method to check if the contact already has an address with the same location type
   *
   * @param $contactId
   * @param $locationTypeId
   * @return bool
   */
  public function alreadyHasAddressOfLocationType($contactId, $locationTypeId) {
    try {
      $count = civicrm_api3('Address', 'getcount', [
        'contact_id' => $contactId,
        'location_type_id' => $locationTypeId,
      ]);
      if ($count > 0) {
        return TRUE;
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
      Civi::log()->error(ts('Unexpected error from API Address getcount in ') . __METHOD__ . ts(', error message from API: ') . $ex->getMessage());
    }
    return FALSE;
  }

  /**
   * Method to check if the address data is valid
   *
   * @param $addressData
   * @return bool
   */
  private function isValidAddressData(&$addressData) {
    // expects some data
    if (empty($addressData)) {
      Civi::log()->warning(ts('Empty address data array in ') . __METHOD__);
      return FALSE;
    }
    // contact id is required
    if (!isset($addressData['contact_id']) || empty($addressData['contact_id'])) {
      Civi::log()->warning(ts('No or empty contact_id found in address data in ') . __METHOD__);
      return FALSE;
    }
    // check if location type exists if present, remove when errors, will be set to default in next step
    if (isset($addressData['location_type_id']) && !empty($addressData['location_type_id'])) {
      try {
        $locCount = civicrm_api3('LocationType', 'getcount', ['id' => $addressData['location_type_id']]);
        if ($locCount == 0) {
          unset($addressData['location_type_id']);
        }
      }
      catch (CiviCRM_API3_Exception $ex) {
        unset($addressData['location_type_id']);
      }
    }
    // default location type if no location type set in params
    if (!isset($addressData['location_type_id']) || empty($addressData['location_type_id'])) {
      $addressData['location_type_id'] = $this->_defaultLocationTypeId;
    }
    // primary if no primary set in params
    if (!isset($addressData['is_primary'])) {
      $addressData['is_primary'] = 1;
    }
    // check if country exists if present, remove when errors
    if (isset($addressData['country_id']) && !empty($addressData['country_id'])) {
      try {
        $countryCount = civicrm_api3('Country', 'getcount', ['id' => $addressData['country_id']]);
        if ($countryCount == 0) {
          unset($addressData['country_id']);
        }
      }
      catch (CiviCRM_API3_Exception $ex) {
        unset($addressData['country_id']);
      }
    }
    return TRUE;
  }

}