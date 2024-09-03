<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package     Arlo Moodle Integration
 * @subpackage  enrol_arlo
 * @author      Corey Davis
 * @copyright   2015 LearningWorks Ltd <http://www.learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['alert'] = 'Alert';
$string['allowcompletedevents'] = 'Allow completed events';
$string['allowcompletedevents_text'] = 'Completed events can be linked.';
$string['allowcompletedevents_help'] = 'Completed events will be able to be selected and linked to an enrolment instance.';
$string['allowcompletedonlineactivities'] = 'Allow completed online activities';
$string['allowcompletedonlineactivities_text'] = 'Completed online activities can be linked.';
$string['allowcompletedonlineactivities_help'] = 'Completed online activitieswill be able to be selected and linked to an enrolment instance.';
$string['allowunenrolactiveenrolmentsui'] = 'Allow unenrol active enrolments';
$string['allowunenrolactiveenrolmentsui_text'] = 'Allow user with unenrol capability to manually unenrol user without suspending first.';
$string['allowunenrolactiveenrolmentsui_help'] = 'Adds the unenrol action icon on to users enrolment method.';
$string['allowunenrolaccessedui'] = 'Allow unenrol accessed';
$string['allowunenrolaccessedui_text'] = 'Allow ability to unenrol users who have accessed Arlo enrolled courses.';
$string['allowunenrolaccessedui_help'] = 'Adds a control to Unsuccessful enrolment page for handling Contact Merge Requests.';
$string['allowhiddencourses'] = 'Allow hidden courses';
$string['allowhiddencourses_text'] = 'Allow Arlo enrolment instances in hidden courses to be processed.';
$string['allowhiddencourses_help'] = ' Note: You will need to disable course welcome messages of adjust them accordingly.';
$string['allowportalintegration'] = 'Allow portal integration';
$string['allowportalintegration_text'] = 'Allow integration with Arlo portal.';
$string['allowportalintegration_help'] = 'Will push course homepage and enrolment instance URL\'s to Arlo for use in portal.';
$string['apicansendpatchrequests'] = "User can send PATCH requests";
$string['apirequests'] = 'API requests';
$string['apiretries'] = 'API retries';
$string['apiretryerrorpt1'] = 'User with id:';
$string['apiretryerrorpt2'] = 'has been redirected by the API too often';
$string['apistatusok'] = 'OK, last request was {$a}';
$string['apistatusclienterror'] = 'Client connection error!';
$string['apistatusservererror'] = 'Service currently unavailable';
$string['apiusername'] = 'Arlo username';
$string['apiusername_desc'] = 'Important: Your Arlo user account must be API enabled, <a title="{$a->title}" href="{$a->url}" target="_blank">learn more.</a>';
$string['apipassword'] = 'Arlo password';
$string['apipassword_desc'] = 'Forgot your password? <a title="{$a->title}" href="{$a->url}" target="_blank">Learn how to reset it.</a>';
$string['arloconnection'] = 'Arlo connection';
$string['arlosettings'] = 'Arlo settings';
$string['arlocontact'] = 'Arlo contact';
$string['arlocoursecode'] = 'Arlo course code';
$string['arloenrolment'] = 'Arlo enrolment';
$string['arlo:config'] = 'Configure Arlo enrolment instances';
$string['arlo:manage'] = 'Manage Arlo enrolment instances';
$string['arlo:unenrol'] = 'Manually unenrol a user';
$string['arlo:synchronizecore'] = 'Manually pull new core resources';
$string['arlo:synchronizeinstance'] = 'Manually pull new registrations and push new results for an enrolment instance';
$string['arlo:unenrol'] = 'Unenrol suspended users';
$string['assignedgroup'] = 'Assigned group';
$string['assignrole'] = 'Assign role';
$string['associateduser'] = 'Associated user';
$string['associatearlotemplate'] = 'Associate Arlo template';
$string['associatetemplatedanger'] = '<div class="alert alert-danger" role="alert">
<strong>Important!</strong>
<p>This will associate every Event or Online Activity based off the Template. This does not include Events or Online Activities that have already been added to another course.</p>
</div>';
$string['associatetemplatewithcourse'] = 'Associate Arlo Template with Moodle course';
$string['backtoenrolmentmethods'] = 'Back to enrolment methods';
$string['configuration'] = 'Configuration';
$string['changessaved'] = 'Changes saved';
$string['codeprimary'] = 'Code primary';
$string['contactmergerequestfailure'] = 'Failure to apply contact merge request';
$string['contactmergerequestfailures'] = 'Contact merge request failures';
$string['contactmergefailurereport'] = 'Contact merge report';
$string['communications'] = 'Communications';
$string['completed'] = 'Completed';
$string['courseid'] = 'Course ID';
$string['coursename'] = 'Course name';
$string['coursewelcome'] = 'Course welcome';
$string['creategroup'] = 'Create new group';
$string['customwelcomemessage'] = 'Custom welcome message';
$string['customwelcomemessage_help'] = 'A custom welcome message may be added as plain text or Moodle-auto format, including HTML tags and multi-lang tags.

The following placeholders may be included in the message:

* Course name {$a->coursename}
* Course url {$a->courseurl}
* User firstname {$a->firstname}
* User fullname {$a->fullname}
* User username {$a->username}
* Forgot password url {$a->forgotpasswordurl}';
$string['defaultgroupnametext'] = '{$a->name} Arlo group';
$string['defaultperiod'] = 'Default enrolment duration';
$string['defaultperiod_desc'] = 'Default length of time that the enrolment is valid. If set to zero, the enrolment duration will be unlimited by default.';
$string['defaultperiod_help'] = 'Default length of time that the enrolment is valid, starting with the moment the user is enrolled. If disabled, the enrolment duration will be unlimited by default.';
$string['defaultrole'] = 'Default role';
$string['defaultrole_help'] = 'The role which should be assigned to users during enrolment';
$string['delivered'] = 'Delivered';
$string['enrolment'] = 'Enrolment';
$string['enrolmentfailure'] = 'Enrolment failure has occurred';
$string['enrolmentinstances'] = 'Enrolment instances';
$string['enrolmentinstancescount'] = 'Enrolment instances: {$a}';
$string['enrolusers'] = 'Enrol users';
$string['enrolperiod'] = 'Enrolment duration';
$string['errorselecttype'] = 'You must select an Type';
$string['errorselectevent'] = 'You must select an Event';
$string['errorselectonlineactvity'] = 'You must select an Online Activity';
$string['errorselecttemplate'] = 'Please select a Event Template';
$string['failures'] = 'Failures';
$string['error_duplicateusers_subject'] = 'Duplicate users found in Moodle when matching Arlo Contact';
$string['error_duplicateusers_smallmessage'] = 'Duplicate users found in Moodle when matching Arlo Contact';
$string['error_duplicateusers_full'] = '
Duplicate users were found in Moodle when attempting to match against an Arlo Contact.

Found {$a->count} Moodle accounts with following details:
First name: {$a->firstname}
Last name:  {$a->lastname}
Email:      {$a->email}
IDNumber:   {$a->idnumber}

A new account has been created for this user.
';
$string['error_duplicateusers_fullhtml'] = '
<p>Duplicate users were found in Moodle when attempting to match against an Arlo Contact.</p>
<br>
<p>Found {$a->count} Moodle accounts with following details:</p>
<br>
<pre>
First name: {$a->firstname}
Last name:  {$a->lastname}
Email:      {$a->email}
IDNumber:   {$a->idnumber}
</pre>
<br>
<p>A new account has been created for this user.</p>';

$string['expiredaction'] = 'Enrolment expiration action';
$string['expirynotify'] = 'Notify user of enrolment expiration';
$string['expirynotify_help'] = 'This setting determines whether enrolment expiry notification messages are sent.';
$string['expiredaction_help'] = 'Select action to carry out when user enrolment expires. Please note that some user data and settings are purged from course during course unenrolment.';
$string['expirymessagesubject'] = 'Enrolment expiry notification';
$string['expirymessagetext'] = 'Dear {$a->user},

This is a notification that your enrolment in the course \'{$a->coursename}\' has expired.';
$string['event'] = 'Event';
$string['eventcreated'] = 'Event created';
$string['eventupdated'] = 'Event updated';
$string['events'] = 'Events';
$string['extra'] = 'Extra';
$string['fail'] = 'Fail';
$string['failed'] = 'Failed';
$string['fullname'] = 'Full name';
$string['inprogress'] = 'In progress';
$string['instanceexists'] = 'Arlo is already synchronised with selected role';
$string['longtime'] = 'This process can take a long time';
$string['manualsynchronisenotice'] = 'This process can take a long time. Do not close you browser window. You will be redirected to enrolment methods page once the process has completed.';
$string['locktimeout'] = 'The operation timed out while waiting for a lock';
$string['messagenotsent'] = 'Message not sent';
$string['messageprovider:administratornotification'] = 'Administrator notifications of integration issues';
$string['messagesent'] = 'Message sent';
$string['managearlo'] = 'Manage Arlo';
$string['matchbyarlocodeprimary'] = 'Arlo Contact CodePrimary';
$string['matchbyarlouserdetails'] = 'Arlo Contact FirstName, LastName and Email';
$string['matchbyauto'] = 'Arlo Contact FirstName, LastName and Email then Arlo Contact CodePrimary';
$string['matchuseraccountsby'] = 'Match user accounts by';
$string['matchuseraccountsby_help'] = '
<p>
Before creating a new Moodle account the plugin will attempt to match against an Arlo contact record. The options for matching are:
</p>
<p>
<strong>Method 1</strong>
<br>
Arlo FirstName, LastName and Email - This will attempt a match between the group of fields (Arlo Contact FirstName, LastName and Email) and (Moodle user firstname, lastname and email).
</p>
<p>
<strong>Method 2</strong>
<br>
Arlo CodePrimary - This will attempt a match between fields Arlo Contact CodePrimary and Moodle user idnumber.
</p>
<p>
<strong>Method 3</strong>
<br>
Will try <strong>Method 1</strong> if no match will then try <strong>Method 2</strong>.
</p>
<p>
If no match is found using any of the Methods a Moodle user account will be created based on Arlo Contact details.
</p>';
$string['retriesperrecord'] = 'Maxiumum retries per record';
$string['retriesperrecord_desc'] = 'Maximum number of retries per record allowed before halting API communication for that record.';
$string['maxretries'] = 'Maxiumum retries permitted on the API';
$string['maxretries_desc'] = 'Maximum number of retries your connection is allowed before halting API communication.';
$string['maxretires_email'] = 'Maximum retries error email';
$string['maxretires_email_desc'] = 'The email that error reports are sent to when your connection reaches the maximum retries.';
$string['maxretries_exception'] = 'Too many failed attempts, contact your admin.';
$string['newaccountdetails'] = 'New account details';
$string['newuserdefaultemail'] = 'New user default email';
$string['newuserdefaultemail_help'] = 'New user default email';
$string['newaccountsubject'] = 'New user account';
$string['newaccountfullmessage'] = 'Hi {$a->firstname},

A new account has been created for you at \'{$a->sitename}\'
and you have been issued with a new temporary password.

Your current login information is now:
   username: {$a->username}
   password: {$a->newpassword}
             (you will have to change your password
              when you login for the first time)

To start using \'{$a->sitename}\', login at
   {$a->link}

In most mail programs, this should appear as a blue link
which you can just click on.  If that doesn\'t work,
then cut and paste the address into the address
line at the top of your web browser window.

Cheers from the \'{$a->sitename}\' administrator,
{$a->signoff}';
$string['notifyexpiry'] = 'Notify expiry';
$string['notok'] = 'Not OK';
$string['notstarted'] = 'Not started';
$string['noeventsoractivitiesfound'] = 'No "Active" Events or Online Activities found.';
$string['ok'] = 'OK';
$string['onlineactivity'] = 'Online Activity';
$string['onlineactivities'] = 'Online Activities';
$string['onlineactivitycreated'] = 'Online activity created';
$string['onlineactivityupdated'] = 'Online activity updated';
$string['outboundemaildelivery'] = 'Outbound email delivery';
$string['divertedto'] = 'Diverted to {$a}';
$string['disabled'] = 'Disabled';
$string['synchroniseoperationiscurrentlylocked'] = 'The Arlo enrolment synchronise operation for this instance is currently locked by another running process.';
$string['opennewtabtitle'] = 'To open in a new tab on Windows: CTRL + click on Mac: command + click';
$string['pass'] = 'Pass';
$string['pleasecheckrequestlog'] = 'Please check <a href="{$a}">request log</a>';
$string['pluginnotenabled'] = 'Plugin not enabled!';
$string['platform'] = 'Arlo platform URL';
$string['platform_desc'] = 'URL of your Arlo management platform \'yourplatform.arlo.co\' (excluding https:// and /). No Arlo platform? <a title="{$a->title}" href="{$a->url}" target="_blank">Create a free trial.</a>';
$string['platform_bad_input_domain'] = 'Your input was changed to remove one of the following from the domain name: https://, http://, and/or /';
$string['platform_bad_input_domain_small'] = 'There was a problem with your Arlo platform URL';
$string['platform_bad_input_domain_subject'] = 'Changes were made to your Arlo platform URL';
$string['pushonlineactivityresults'] = 'Push Online Activity results';
$string['pushonlineactivityresults_help'] = 'Push result information from enrolment instances mapped to <strong>OnlineActivities</strong> back to Arlo';
$string['pusheventresults'] = 'Push Event results';
$string['pusheventresults_help'] = 'Push result information from enrolment instances mapped to <strong>Events</strong> back to Arlo';
$string['pluginname'] = 'Arlo enrolment';
$string['pluginname_desc'] = '<p>These enrolments are managed by local_arlo</p>';
$string['pluginstatus'] = 'Status';
$string['queued'] = 'Queued';
$string['reattemptenrolment'] = 'Re-attempt enrolment';
$string['reattemptenrolmentconfirm'] = 'Please ensure you have actioned all issues found in the failure reports before re-attempting the enrolment or the enrolment will fail again. Re-attempt enrolment?';
$string['registrationstatus'] = 'Registration status';
$string['remove'] = 'Remove';
$string['removetemplatedanger'] = '
<div class="alert alert-danger" role="alert">
<strong>Important!</strong>
<p>Removing the template association will remove all people from the course that are in the following enrolment instances:</p>
{$a}
</div>';
$string['resulting'] = 'Resulting';
$string['savechanges'] = 'Save changes';
$string['sendcoursewelcomemessage'] = 'Send course welcome message';
$string['sendcoursewelcomemessage_help'] = 'If enabled, users receive a welcome message via email when they are enrolled in a course.';
$string['status'] = 'Active';
$string['status_help'] = '';
$string['synchronize'] = 'Synchronize';
$string['synchronizeinstance'] = 'Manually pull new registrations and push new results for an enrolment instance';
$string['synchroniseinstancefor'] = 'Manually synchronise Arlo enrolments and outcomes for {$a}';
$string['enrolmentstask'] = 'Create and update enrolments based off Arlo registration information';
$string['dailytask'] = 'Daily Arlo task for more intensive jobs';
$string['outcomestask'] = 'Push outcome and process information to Arlo registrations';
$string['coretask'] = 'Sync Arlo core information';
$string['updatecontactstask'] = 'Update Moodle user information based on updated Arlo contact information';
$string['template'] = 'Template';
$string['timelogged'] = 'Time logged';
$string['timemodified'] = 'Time modified';
$string['type'] = 'Type';
$string['typeofevent'] = 'Type of Event';
$string['unknown'] = 'Unknown';
$string['unlock'] = 'Unlock';
$string['uri'] = 'URI';
$string['userid'] = 'User ID';
$string['userassociationfailurereport'] = 'User association report';
$string['usercreation'] = 'User creation';
$string['welcometocourse'] = 'Welcome to {$a}';
$string['welcometocoursetext'] = 'Welcome to {$a->coursename}!

Your username: {$a->username}

Forgotten your password?

You can reset your password using following url:

  {$a->forgotpasswordurl}

You may access this course at the following url:

  {$a->courseurl}';

$string['httpstatus:200'] = 'OK';
$string['httpstatus:201'] = 'Created';
$string['httpstatus:302'] = 'Found';
$string['httpstatus:400'] = 'Bad Request';
$string['httpstatus:401'] = 'Unauthorized';
$string['httpstatus:403'] = 'Forbidden';
$string['httpstatus:404'] = 'Not Found';
$string['httpstatus:406'] = 'Unacceptable';
$string['httpstatus:409'] = 'Conflict';
$string['httpstatus:415'] = 'Unsupported Media Type';
$string['httpstatus:500'] = 'Internal Server Error';
$string['httpstatus:503'] = 'Service Unavailable';
$string['cleanup'] = 'Clean up';
$string['requestlogcleanup'] = 'Request log clean up';
$string['requestlogcleanup_help'] = 'Remove request log entries that are older than the selected time.';
$string['outcomepushingdisabled'] = 'Outcome pushing is disabled at site configuration.';
$string['eventresultpushingdisabled'] = 'Event result pushing disabled at site configuration.';
$string['onlineactivityresultpushingdisabled'] = 'Online activity result pushing disabled at site configuration.';
$string['nomatchingenrolmentinstance'] = 'No matching enrolment instance.';
$string['enrolmentinstancedisabled'] = 'Enrolment instance disabled.';
$string['allowhiddencoursesdiabled'] = 'Allow hidden courses is disabled at site configuration.';
$string['contactresourcemissing'] = 'Contact resource missing from Registration.';
$string['contactrecordmissing'] = 'Contact record missing.';
$string['noassociateduser'] = 'No associated Moodle user account.';
$string['unsuccessfulenrolment'] = 'Unsuccessful enrolment';
$string['unsuccessfulenrolments'] = 'Unsuccessful enrolments';
$string['unsuccessfulenrolmentscount'] = 'Unsuccessful enrolments: {$a}';
$string['unsuccessfulenrolmentof'] = 'Unsuccessful enrolment of {$a->fullname} into {$a->code}';
$string['report'] = 'Report';
$string['viewreport'] = 'View report';
$string['sourcecontact'] = 'Source contact';
$string['destinationcontact'] = 'Destination contact';
$string['browseassociateduser'] = 'Browse associated user account';
$string['returntounsucessfulenrolments'] = 'Return to unsucessful enrolments';

$string['unsuccessfulenrolment_subject'] = 'Unsuccessful enrolment of Arlo contact';
$string['unsuccessfulenrolment_fullmessage'] = '
Unsuccessful enrolment of Arlo contact, this may be due to duplicate matching Moodle user accounts or Moodle account could not be created due to site configuration settings.

Please check report {$a->report} for more information.';
$string['unsuccessfulenrolment_fullmessagehtml'] = '
<p>Unsuccessful enrolment of Arlo contact, this may be due to duplicate matching Moodle user accounts or Moodle account could not be created due to site configuration settings.</p>
<br>
<p>Please check report <a href="{$a->report}">{$a->report}</a> for more information.</p>';
$string['unsuccessfulenrolment_smallmessage'] = 'Unsuccessful enrolment of Arlo contact, please check report {$a->report}';
$string['invalidcredentials_subject'] = 'Moodle cannot connect to Arlo API';
$string['invalidcredentials_smallmessage'] = 'Moodle cannot connect to Arlo API. Please check connection settings: {$a->url}';
$string['invalidcredentials_fullmessage'] = '
Moodle cannot connect to Arlo API

There is something wrong with your connection settings. Please check that the api username and api password are correct.
Moodle Arlo connection settings: {$a->url}
';
$string['invalidcredentials_fullmessagehtml'] = '
<h3>Moodle cannot connect to Arlo API</h3>
<br>
<p>There is something wrong with your connection settings. Please check that the api username and api password are correct.</p>
<p>Moodle Arlo connection settings: <a href="{$a->url}">{$a->url}</a></p>';
$string['morethanonematch'] = 'More than one match found';
$string['moodleusern'] = 'Moodle user {$a}';
$string['outcomespushingdisabled'] = 'Outcomes pushing is disabled';
$string['accessed'] = 'Accessed';
$string['unenrolfromarlocourses'] = 'Unenrol from Arlo linked courses';
$string['contactrecordinformation'] = 'Contact record information';
$string['userrecordinformation'] = 'User record information';
$string['sourcecontact'] = 'Source contact';
$string['destinationcontact'] = 'Destination contact';
$string['unenrolcontact'] = 'Unenrol contact';
$string['removeallarloenrolmentsquestion'] = 'Are you sure you want to remove all Arlo linked enrolments for {$a}?';
$string['enrolmentwillbeattemptedagain'] = 'The enrolment with be attempted again via the standard scheduled task. You can also try to resolve via a manual syncronisation.';
$string['morethanonemoodleuserfound'] = 'More than one Moodle user found with same details';
$string['suspendeduser_subject'] = 'Plugin has suspended a user account';
$string['suspendeduser_fullmessage'] = '
Arlo enrolment plugin has suspended a user account while resolving a contact merge request.

The user account did not have any courses enrolments and was linked to an Arlo contact.

Please review user profile {$a->profileurl}';
$string['suspendeduser_fullmessagehtml'] = '
<p>Arlo enrolment plugin has suspended a user account while resolving a contact merge request.</p>
<br>
<p>The user account did not have any courses enrolments and was linked to an Arlo contact.</p>
<br>
<p>Please review user profile <a href="{$a->profileurl}">{$a->profileurl}</a></p>';
$string['suspendeduser_smallmessage'] = 'A user account has been suspended. Please review user profile {$a->profileurl}';
$string['browseuserprofile'] = 'Browse user profile';
$string['privacy:metadata:core_group'] = 'Plugin can create a new group or use an existing group to add members from the enrolment instance.';
$string['metadata:enrol_arlo_contact'] = 'Arlo contact';
$string['privacy:metadata:enrol_arlo_contact'] = 'Information about the Arlo contact linked to a Moodle user account.';
$string['privacy:metadata:enrol_arlo_contact:userid'] = 'The ID of the Moodle user associated with the Arlo contact.';
$string['privacy:metadata:enrol_arlo_contact:sourceid'] = 'The ID of the Arlo contact.';
$string['privacy:metadata:enrol_arlo_contact:sourceguid'] = 'The GUID of the Arlo contact.';
$string['privacy:metadata:enrol_arlo_contact:firstname'] = 'The first name of the Arlo contact.';
$string['privacy:metadata:enrol_arlo_contact:lastname'] = 'The last name of the Arlo contact';
$string['privacy:metadata:enrol_arlo_contact:email'] = 'The email of the Arlo contact.';
$string['privacy:metadata:enrol_arlo_contact:codeprimary'] = 'The code primary of the Arlo contact.';
$string['privacy:metadata:enrol_arlo_contact:phonework'] = 'The work phone number of the Arlo contact.';
$string['privacy:metadata:enrol_arlo_contact:phonemobile'] = 'The mobile phone number of the Arlo contact.';
$string['metadata:enrol_arlo_emailqueue'] = 'Communications';
$string['privacy:metadata:enrol_arlo_emailqueue'] = 'Information about Arlo email communications';
$string['privacy:metadata:enrol_arlo_emailqueue:area'] = 'Site or enrolment.';
$string['privacy:metadata:enrol_arlo_emailqueue:instanceid'] = 'The instance identifier of area.';
$string['privacy:metadata:enrol_arlo_emailqueue:userid'] = 'The ID of the Moodle user associated with the Arlo contact.';
$string['privacy:metadata:enrol_arlo_emailqueue:type'] = 'New user or course welcome.';
$string['privacy:metadata:enrol_arlo_emailqueue:status'] = 'Queued, sent or failed.';
$string['privacy:metadata:enrol_arlo_emailqueue:extra'] = 'Any extra information needed in body of email.';
$string['metadata:enrol_arlo_registration'] = 'Arlo registration';
$string['privacy:metadata:enrol_arlo_registration'] = 'Information about the Arlo registration linked to a Moodle user enrolment.';
$string['privacy:metadata:enrol_arlo_registration:enrolid'] = 'The ID of the associated enrolment instance.';
$string['privacy:metadata:enrol_arlo_registration:userid'] = 'The ID of the Moodle user associated with the Arlo contact.';
$string['privacy:metadata:enrol_arlo_registration:sourceid'] = 'The ID of the Arlo registration.';
$string['privacy:metadata:enrol_arlo_registration:sourceguid'] = 'The GUID of the Arlo registration.';
$string['privacy:metadata:enrol_arlo_registration:grade'] = 'The grade associated with Arlo registration.';
$string['privacy:metadata:enrol_arlo_registration:outcome'] = 'The outcome (pass/fail) associated with Arlo registration.';
$string['privacy:metadata:enrol_arlo_registration:lastactivity'] = 'The time when there was some activity in an external system associated with Arlo registration.';
$string['privacy:metadata:enrol_arlo_registration:progressstatus'] = 'The progress status associated with Arlo registration.';
$string['privacy:metadata:enrol_arlo_registration:progresspercent'] = 'The decimal value representing a progress associated with Arlo registration.';
$string['privacy:metadata:enrol_arlo_registration:sourcecontactid'] = 'The ID of the Arlo contact.';
$string['privacy:metadata:enrol_arlo_registration:sourcecontactguid'] = 'The GUID of the Arlo contact.';
$string['privacy:metadata:field:usermodified'] = 'The ID of user who modified the record.';
$string['privacy:metadata:enrol_arlo_templateassociate'] = 'Arlo template associate';

$string['firstnamelastnamerandomnumber'] = 'First name, last name and random number';
$string['firstnamelastnamerandomnumber_desc'] = 'Use first 3 letters of firstname + first 3 letters of lastname + random number';
$string['emaillocalpart'] = 'Email local part';
$string['emaillocalpart_desc'] = 'Use email username address before @ symbol';
$string['emaillocalpartrandomnumber'] = 'Email local part and random number';
$string['emaillocalpartrandomnumber_desc'] = 'Use email username address before @ symbol + random number';
$string['email'] = 'Email';
$string['email_desc'] = 'Use full email address';
$string['emailrandomnumber'] = 'Email and random number';
$string['emailrandomnumber_desc'] = 'Use full email address + random number';
$string['useraccountmatching'] = 'User account matching';
$string['useraccountcreation'] = 'User account creation';
$string['courseenrolment'] = 'Course enrolment';
$string['cleanup'] = 'Cleanup';
$string['usernamegeneration_desc'] = '<p>The order in which different username formats are used to generate a username for a new Moodle user account. If existing account found with same username as generated, the next format in order will be tried.</p><br>';
$string['webhooksecret'] = 'Arlo webhook secret';
$string['webhooksecret_desc'] = 'Arlo webhook secret for validating webhook requests.';
$string['webhookapiurl'] = 'Arlo webhook API URL';
$string['webhookapiurl_desc'] = 'Arlo webhook API URL for webhook requests.';
$string['enablewebhook'] = 'Enable webhook';
$string['enablewebhook_desc'] = ' Webhooks allows real-time synchronisation between Arlo and Moodle.';
$string['useadhoctask'] = 'Use webhook adhoc task';
$string['useadhoctask_desc'] = 'Enable this if you are planning to perform a large synchronisation between Arlo and Moodle (e.g. 100+ records at once).';
$string['onlyactive'] = 'Only active events';
$string['onlyactive_desc'] = 'Only process registrations for active Arlo courses and online activities.';
$string['disableskip'] = 'Disable registration skip';
$string['disableskip_desc'] = 'Disables the ability to skip processing  old registrations (A sync will run for all registrations even the ones already processed).';
$string['enable_multisync'] = 'Enable multisync';
$string['enable_multisync_desc'] = 'When the webhook is enabled the sync is done only trough the webhooks. This setting allows to run the sync through the scheduled task as well.';
$string['technicalcontact'] = 'Contact email';
$string['regcreated'] = 'Registration created';
$string['regupdated'] = 'Registration updated';
$string['eventtypes'] = 'Event types';
$string['createwebhook'] = 'Create webhook';
$string['xml'] = 'XML';
$string['json'] = 'JSON';
$string['format'] = 'FORMAT';
$string['webhookenabled'] = '<div class="alert alert-success" role="alert">Webhook is configured and active.</div>';
$string['webhookinactive'] = '<div class="alert alert-warning" role="alert">Webhook not configured or inactive.</div>';
$string['webhookdisabled'] = '<div class="alert alert-danger" role="alert">Webhook is disabled.</div>';
$string['webhookstatus'] = 'Webhook status';
$string['errorhandling'] = 'Error handling';
$string['syncsettings'] = 'Sync settings';
$string['webhooks'] = 'Webhooks';
$string['nomembershipjobfound'] = 'No membership job found for enrolment instance {$a}';
$string['nooutcomejobfound'] = 'No outcome job found for enrolment instance {$a}';
$string['api_retry_notification'] = 'API Retry Log Notification';
$string['messageprovider:arlo_retry_log_notification'] = 'API Retry Log Notification';
$string['arlo_retry_log_subject'] = 'Arlo API Retry Log Notification';
$string['arlo_retry_log_message'] = '<div><p>New entries have been detected in the Arlo API retry log. Please review the log for details.</p> <br><p>To view the API retry log, click on the following link:</p><a href="{$a}">{$a}</a></div>';
$string['redirectcountmaxlimit'] = 'The maximum redirect limit for API requests has been reached. Further attempts are currently restricted. Manual review is required. To re-enable communication see Arlo settings.';
$string['communication_enabled_message'] = 'Communication has been successfully enabled.';
$string['communication_disabled_message'] = 'Communication has been disabled.';
$string['emailsubject'] = 'Alert: Maximum Retries Reached for PATCH Requests on {$a}';
$string['emailbody'] = 'Dear {$a->fullname},<br>' .
        'The site "{$a->shortname}" {$a->url} has reached the maximum number of retries {$a->maxretries} for registration PATCH requests.<br>' .
        'Please review the issue as soon as possible.<br>' .
        'Report: {$a->reportlink}<br>' .
        'Regards,<br>Moodle System';
$string['notifymaxredirects'] = 'Notify on maximum redirects';
$string['enablecommunication'] = 'Re-stablish communication';
$string['resetredirects'] = 'Reset all fail counters';
$string['resetretries_message'] = 'The retries for registration PATCH requests have been reset.';
$string['retry_sync'] = 'Retry sync';
$string['connectionstatus'] = '<span>Connection Status: </span>';
$string['apifails'] = '<span>Global API fails: </span>';

