<?php
/**
 * Class handling the notes processing for streetimport
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 6 Mar 2019
 * @license AGPL-3.0
 */
class CRM_Streetimport_Notes {
  private $_companyNamePattern = NULL;
  private $_companyNumberPattern = NULL;
  private $_companyFunctionPattern = NULL;
  private $_firstDelimiter = NULL;
  private $_secondDelimiter = NULL;
  private $_emptyCompanyString = NULL;

  /**
   * CRM_Streetimport_Notes constructor.
   */
  public function __construct() {
    $this->_companyNamePattern = "companyName";
    $this->_companyFunctionPattern = "companyFunction";
    $this->_companyNumberPattern = "companyNumber";
    $this->_firstDelimiter = "/";
    $this->_secondDelimiter = ":";
    $this->_emptyCompanyString = "companyName: / companyNumber: / companyFunction: ";
  }

  /**
   * Getter for empty company string
   * @return string|null
   */
  public function getEmptyCompanyString() {
    return $this->_emptyCompanyString;
  }

  /**
   * Method to get the organization data from the import data (notes column)
   * https://civicoop.plan.io/issues/677
   *
   * @param string $importNote
   * @return array
   */
  public function getOrganizationDataFromImportData($importNote) {
    $result = [];
    // first check if we have company stuff in the remarks
    if ($this->hasOrganizationStuff($importNote)) {
      // then check if we have stuff before the organization stuff and add that to the result as ['note']
      $realNoteSplit = $this->splitRealNoteAndOrganization($importNote);
      $orgParts = explode($this->_firstDelimiter, $realNoteSplit['organization_bit']);
      if (!empty($orgParts)) {
        // split first element second delimiter, first part should be companyName and second contain name data
        $nameParts = explode($this->_secondDelimiter, trim($orgParts[0]));
        if (trim($nameParts[0]) == $this->_companyNamePattern && isset($nameParts[1]) && !empty($nameParts[1])) {
          $result['organization_name'] = trim($nameParts[1]);
        }
        // split second element second delimiter, second part should be companyNumber going to custom field organization number
        if (isset($orgParts[1])) {
          $orgNumberParts = explode($this->_secondDelimiter, trim($orgParts[1]));
          if (trim($orgNumberParts[0]) == $this->_companyNumberPattern && isset($orgNumberParts[1]) && !empty($orgNumberParts[1])) {
            $result['organization_number'] = trim($orgNumberParts[1]);
          }
        }
        // split third element on second delimiter, first part should be companyFunction and second job title data
        if (isset($orgParts[2])) {
          $jobTitleParts = explode($this->_secondDelimiter, trim($orgParts[2]));
          if (trim($jobTitleParts[0]) == $this->_companyFunctionPattern && isset($jobTitleParts[1]) && !empty($jobTitleParts[1])) {
            $result['job_title'] = trim($jobTitleParts[1]);
          }
        }
      }
    }
    return $result;
  }

  /**
   * Method to split the notes part and organization part if necessary
   *
   * @param $importNote
   * @return array
   */
  public function splitRealNoteAndOrganization($importNote) {
    $result = [];
    // check if there is any text before the companyName thing and if so, add that to the notes
    $beforeParts = explode($this->_companyNamePattern, $importNote);
    if (!empty($beforeParts[0])) {
      $result['notes_bit'] = $beforeParts[0];
      $result['organization_bit'] = $this->_companyNamePattern . $beforeParts[1];
    }
    else {
      $result['notes_bit'] = "";
      $result['organization_bit'] = $importNote;
    }
    return $result;
  }

  /**
   * Method to check if the full organizational pattern occurs in note
   *
   * @param $importNote
   * @return bool
   */
  public function hasOrganizationStuff($importNote) {
    $nameOccurs = FALSE;
    $numberOccurs = FALSE;
    $functionOccurs = FALSE;
    if (strpos($importNote, $this->_companyNamePattern) !== FALSE) {
      $nameOccurs = TRUE;
    }
    if (strpos($importNote, $this->_companyNumberPattern) !== FALSE) {
      $numberOccurs = TRUE;
    }
    if (strpos($importNote, $this->_companyFunctionPattern) !== FALSE) {
      $functionOccurs = TRUE;
    }
    if ($nameOccurs && $numberOccurs && $functionOccurs) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Method to check if the notes only contain organization headers
   * https://civicoop.plan.io/issues/3831
   *
   * @param string $importNote
   * @return bool
   */
  public function isNotesEmptyCompany($importNote) {
    $firstParts = explode($this->_firstDelimiter, $importNote);
    $emptyName = FALSE;
    $emptyNumber = FALSE;
    $emptyFunction = FALSE;
    if (!empty($firstParts)) {
      $nameParts = explode($this->_secondDelimiter, $firstParts[0]);
      if (trim($nameParts[0]) == $this->_companyNamePattern && isset($nameParts[1]) && empty($nameParts[1])) {
        $emptyName = TRUE;
      }
      if (isset($firstParts[1])) {
        $numberParts = explode($this->_secondDelimiter, $firstParts[1]);
        if (trim($numberParts[0]) == $this->_companyNumberPattern && isset($numberParts[1]) && empty($numberParts[1])) {
          $emptyNumber = TRUE;
        }
      }
      if (isset($firstParts[2])) {
        $functionParts = explode($this->_secondDelimiter, $firstParts[2]);
        if (trim($functionParts[0]) == $this->_companyFunctionPattern && isset($functionParts[1]) && empty($functionParts[1])) {
          $emptyFunction = TRUE;
        }
      }
    }
    if ($emptyName && $emptyNumber && $emptyFunction) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

}