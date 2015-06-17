# be.aivl.streetimport
Native CiviCRM extension for Amnesty International Flanders (AIVL) to import street recruitment and welcome call csvfiles into CiviCRM.

## Basic functionality ##
AIVL use street recruitment to get new donors and SEPA Direct Debits (SDD). The actual street recruitment is done by a supplier, who follows up the recruitment with a welcoming call to the new donor within a week of recruitment.
Daily they will put a csv file with either street recruitment data or welcome call data on the server, which will be imported by this extension. The records will contain:
* data identifying the recruiting organization and the recruiter
* donor data (name, address, phones, email, birth date, gender, newsletter Y/N, become member, follow up call)
* SEPA mandate data (bank account, frequency, amount, mandate reference, start date, end date)

Depending on the configuration the street recruitment record import will automatically do the following:
* a new contact for the recruiter if it does not exist yet with the contact sub type 'recruiter' and an active relationship 'recruiter for' with the recruiting organziation
* a new contact created for the donor, also storing the donorID at the recruitment organization in a custom group
* the contact will be added to the newsletter group if appropriate
* a membership will be created for the contact if appropriate
* an activity of the type 'Street Recruitment' will be added, with the recruiter as the activity source and the donor as the target contact with status scheduled. In a custom group linked to this activity type all the imported data will be recorded as a snapshot of the street recruitment.
* an SDD is generated (a recurring contribution wtih SEPA mandate and a specific campaign, the mandate reference being generated by the recruiting organization)
* if appropriate, an activity of the type 'Follow Up Call' will be generated with the status scheduled, with the recruiter as the source contact, the donor as the target and the fundraiser in the settings (see section Import Settings) as the assignee contact
* if any error occurred in the process, the data will still be imported if possible but an activity of the type 'Import Error' will be created with the flagged problem, assigned to the error handling employee specified in the settings (see section Import Settings)

