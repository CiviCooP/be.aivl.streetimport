<?php
/**
 * Abstract class bundle common street import functions
 *
 * @author Björn Endres (SYSTOPIA) <endres@systopia.de>
 * @license AGPL-3.0
 */
abstract class CRM_Streetimport_StreetimportRecordHandler extends CRM_Streetimport_RecordHandler{

  protected function getConfigValue($key) {
    // TODO: replace with config/ts lookup
    if ($key=='Recruiter') {
      return 'Werver';
    } elseif ($key=='Unknown Recruiter') {
      return 'Unknown Werver';
    } else {
      return 'LOOKUP-ERROR';
    }
  }


  /** 
   * look up the recruiting organisation
   */
  protected function getRecruitingOrganisation() {
    if (empty($record['Recruiting organization ID'])) {
      $this->logger->logFatal("Recruiting organization ID not given.", true);
      return NULL;
    }
    $recruiting_organisation = $this->getContact((int) $record['Recruiting organization ID'], true);
    if ($recruiting_organisation==NULL) {
      $this->logger->logFatal("Recruiting organization [{$record['Recruiting organization ID']}] not found.", true);
      return NULL;
    }
    return $recruiting_organisation;
  }

  /**
   * this method will lookup or create the recruiter
   *
   * @return $recruiter array with contact entity
   */
  protected function processRecruiter($record) {
    $recruiter = NULL;
    if (!empty($record['Recruiter ID'])) {
      $recruiter = $this->getContact($record['Recruiter ID']);
    }

    if ($recruiter==NULL) {
      // "If the contact is not known, a contact of the contact subtype ' Werver' is to be created"
      $recruiter_data = array(
        'contact_type'     => 'Individual',
        'contact_sub_type' => $this->getConfigValue('Recruiter'),
        'first_name'       => 'Recruiter First Name',
        'last_name'        => 'Recruiter Last Name',
        'prefix'           => 'Recruiter Prefix',
      );
      
      // "If the first name and last name are empty, the values 'Unknown Werver' 
      //  "and 'Organization name of recruiting org' will be used as first and last name."
      if (empty($record['Recruiter First Name']) && empty($record['Recruiter Last Name'])) {
        $recruiter_data['first_name'] = $this->getConfigValue('Unknown Recruiter');
        $recruiter_data['last_name']  = $recruiting_organisation['organization_name'];
      }

      // create the recruiter...
      $recruiter = $this->createContact($recruiter_data, true);
      if (!$recruiter) {
        $this->logger->logFatal("Recruiter could not be created.", true);
      }

      // ..."with a relationship 'Werver' to the recruiting organization."
      $this->createRelationship('Werver', $recruiter['id'], $recruiting_organisation['id']);

      // "In all cases where the contact is not known, an activity of the type 'Incompleet werver contact' 
      //     will be generated  and assigned to the admin ID entered as a param"
      $this->createActivity(array(
                              'type'     => 'Incompleet werver contact',
                              'title'    => 'Incompleet werver contact',
                              'assignee' => (int) $record['Admin ID'],
                              'target'   => (int) $recruiter['id'],
                              ));
    }

    return $recruiter;
  }
}