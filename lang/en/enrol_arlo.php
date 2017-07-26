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
 * @author 		Corey Davis
 * @copyright   2015 LearningWorks Ltd <http://www.learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['arloconnection'] = 'Arlo connection';
$string['configuration'] = 'Configuration';
$string['pluginstatus'] = 'Status';
$string['emaillog'] = 'Email log';
$string['apilog'] = 'API log';
$string['managearlo'] = 'Manage Arlo';
$string['pluginname'] = 'Arlo enrolment';
$string['pluginname_desc'] = '<p>These enrolments are managed by local_arlo</p>';

$string['customchar1'] = 'Arlo Template Code';
$string['customchar1_help'] = 'Set this value to the Template Code in Arlo, Warning: Changing this value once set will remove groups associated with the old template.';

$string['notemplatesavali'] = 'There are currently no unassigned templates available to be added to this course.';



$string['assignedgroup'] = 'Assigned group';
$string['assignrole'] = 'Assign role';
$string['arlo:config'] = 'Configure Arlo enrolment instances';
$string['arlo:manage'] = 'Manage Arlo enrolment instances';
$string['arlo:unenrol'] = 'Unenrol suspended users';
$string['enrol/arlo:synchronizecore'] = 'Manually pull new core resources';
$string['enrol/arlo:synchronizeinstance'] = 'Manually pull new registrations and push new results for an enrolment instance';
$string['customwelcomemessage'] = 'Custom welcome message';
$string['customwelcomemessage_help'] = 'A custom welcome message may be added as plain text or Moodle-auto format, including HTML tags and multi-lang tags.

The following placeholders may be included in the message:

* Course name {$a->coursename}
* Course url {$a->courseurl}
* User fullname {$a->fullname}
* User username {$a->username}
* Forgot password url {$a->forgotpasswordurl}';

$string['defaultgroupnametext'] = '{$a->name} Arlo group';
$string['defaultperiod'] = 'Default enrolment duration';
$string['defaultperiod_desc'] = 'Default length of time that the enrolment is valid. If set to zero, the enrolment duration will be unlimited by default.';
$string['defaultperiod_help'] = 'Default length of time that the enrolment is valid, starting with the moment the user is enrolled. If disabled, the enrolment duration will be unlimited by default.';
$string['instanceexists'] = 'Arlo is already synchronised with selected role';
$string['status'] = 'Active';
$string['creategroup'] = 'Create new group';

$string['sendcoursewelcomemessage'] = 'Send course welcome message';
$string['sendcoursewelcomemessage_help'] = 'If enabled, users receive a welcome message via email when they are enrolled in a course.';


$string['event'] = 'Event';
$string['events'] = 'Events';
$string['onlineactivity'] = 'Online Activity';
$string['onlineactivities'] = 'Online Activities';

$string['type'] = 'Type of Event';

$string['linktemplatetocourse'] = 'Link Arlo template to this course';
$string['remove'] = 'Remove';
$string['template'] = 'Template';

$string['associatetemplatedanger'] =
'<div class="alert alert-danger" role="alert">
<strong>Important!</strong>
<p>This will associate every Event or Online Activity based off the Template. This does not include Events or Online Activities that have already been added to another course.</p>
</div>';
$string['removetemplatedanger'] =
'<div class="alert alert-danger" role="alert">
<strong>Important!</strong>
<p>Removing the template association will remove all people from the course that are in the following enrolment instances:</p>
{$a}
</div>';

$string['syncinstanceonadd'] = 'Synchronise instance on adding';
$string['syncinstanceonadd_help'] = '
This will synchronise enrolments for a instance immediately after adding. If not enabled enrolments will be synchronised each time cron is run.
<p><strong>Note:</strong> Enabling this option can degrade user experience due to the time it takes to execute.</p>';

$string['enrolusers'] = 'Enrol users';
$string['expiredaction'] = 'Enrolment expiration action';
$string['expiredaction_help'] = 'Select action to carry out when user enrolment expires. Please note that some user data and settings are purged from course during course unenrolment.';

$string['expirymessagesubject'] = 'Enrolment expiry notification';
$string['expirymessagetext'] = 'Dear {$a->user},

This is a notification that your enrolment in the course \'{$a->coursename}\' has expired.';

$string['expirynotify'] = 'Notify user of enrolment expiration';
$string['expirynotify_help'] = 'This setting determines whether enrolment expiry notification messages are sent.';

$string['welcometocourse'] = 'Welcome to {$a}';
$string['welcometocoursetext'] = 'Welcome to {$a->coursename}!

Your username: {$a->username}

Forgotten your password?

You can reset your password using following url:

  {$a->forgotpasswordurl}

You may access this course at the following url:

  {$a->courseurl}';

$string['associatearlotemplate'] = 'Associate Arlo template';
$string['platform'] = 'Arlo platform URL';
$string['platform_desc'] = 'This is the URL of your Arlo management platform e.g. "yourplatform.arlo.co"';
$string['apiusername'] = 'Arlo username';
$string['apiusername_desc'] = 'The Arlo username must have API enabled, <a title="{$a->title}" href="{$a->url}">learn more.</a>';
$string['apipassword'] = 'Arlo password';
$string['savechanges'] = 'Save changes';
$string['enrolment'] = 'Enrolment';
$string['usercreation'] = 'User creation';
$string['chooseauthmethod'] = 'Default authentication method';
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
$string['newuserdefaultemail'] = 'New user default email';
$string['newuserdefaultemail_help'] = 'New user default email';
$string['alert'] = 'Alert';
$string['resulting'] = 'Resulting';

$string['pushonlineactivityresults'] = 'Push OnlineActivity results';
$string['pushonlineactivityresults_help'] = 'Push result information from enrolment instances mapped to <strong>OnlineActivities</strong> back to Arlo';
$string['pusheventresults'] = 'Push Event results';
$string['pusheventresults_help'] = 'Push result information from enrolment instances mapped to <strong>Events</strong> back to Arlo';
$string['siteadmins'] = 'Site administrators';
$string['changessaved'] = 'Changes saved';
$string['synchronize'] = 'Synchronize';

$string['error_incorrectcontenttype_subject'] = '[enrol/arlo] Incorrect Content-Type';
$string['error_incorrectcontenttype_smallmessage'] = '[enrol/arlo] Incorrect Content-Type';
$string['error_incorrectcontenttype_full'] = '
Plugin detected Incorrect Content-Type in Response from Arlo API.

Response Content-type: {$a->contenttype}';
$string['error_incorrectcontenttype_fullhtml'] = '
<p>Plugin detected Incorrect Content-Type in Response from Arlo API.<p>
<br>
<p>Response Content-type: {$a->contenttype}</p>';


$string['enrolperiod'] = 'Enrolment duration';
$string['status_help'] = '';
$string['noeventsoractivitiesfound'] = 'No "Active" Events or Online Activities found.';

$string['errorselecttype'] = 'You must select an Type';
$string['errorselectevent'] = 'You must select an Event';
$string['errorselectonlineactvity'] = 'You must select an Online Activity';

$string['messageprovider:alerts'] = 'Alert integration problems';

$string['error_invalidcredentials_subject'] = 'Moodle cannot connect to Arlo API';
$string['error_invalidcredentials_smallmessage'] = 'Moodle cannot connect to Arlo API. Please check connection settings: {$a->url}';
$string['error_invalidcredentials_full'] = '
Moodle cannot connect to Arlo API

There is something wrong with your connection settings. Please check that the api username and api password are correct.
Moodle Arlo connection settings: {$a->url}
';
$string['error_invalidcredentials_fullhtml'] = '
<h3>Moodle cannot connect to Arlo API</h3>
<br>
<p>There is something wrong with your connection settings. Please check that the api username and api password are correct.</p>
<p>Moodle Arlo connection settings: <a href="{$a->url}">{$a->url}</a></p>';

$string['unlock'] = 'Unlock';
$string['pluginnotenabled'] = 'Plugin not enabled!';

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

$string['longtime'] = 'This process can take a long time';

$string['messagesent'] = 'Message sent';
$string['messagenotsent'] = 'Message not sent';
$string['completed'] = 'Completed';
$string['inprogress'] = 'In progress';
$string['notstarted'] = 'Not started';
$string['pass'] = 'Pass';
$string['fail'] = 'Fail';
$string['synctask'] = 'Synchronization of Arlo data';
$string['backtoenrolmentmethods'] = 'Back to enrolment methods';
$string['arlo:synchronizeinstance'] = 'Allow user to manually synchronize instance';

$string['eventcreated'] = 'Event created';
$string['eventupdated'] = 'Event updated';
$string['onlineactivitycreated'] = 'Online activity created';
$string['onlineactivityupdated'] = 'Online activity updated';
$string['associatetemplatewithcourse'] = 'Associate Arlo Template with Moodle course';
$string['errorselecttemplate'] = 'Please select a Event Template';
$string['ok'] = 'OK';
$string['notok'] = 'Not OK';
$string['pleasecheckrequestlog'] = 'Please check <a href="{$a}">request log</a>';
$string['locktimeout'] = 'The operation timed out while waiting for a lock';
$string['operationiscurrentlylocked'] = 'The operation is current locked by another process.';
$string['apistatusok'] = 'OK, last request was {$a}';
$string['apistatusclienterror'] = 'Client connection error!';
$string['apistatusservererror'] = 'Service currently unavailable';
$string['opennewtabtitle'] = 'To open in a new tab on Windows: CTRL + click on Mac: command + click';
