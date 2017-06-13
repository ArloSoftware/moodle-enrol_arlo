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

$string['defaultgroupnametext'] = '{$a->name} Arlo {$a->increment}';
$string['defaultperiod'] = 'Default enrolment duration';
$string['defaultperiod_desc'] = 'Default length of time that the enrolment is valid. If set to zero, the enrolment duration will be unlimited by default.';
$string['defaultperiod_help'] = 'Default length of time that the enrolment is valid, starting with the moment the user is enrolled. If disabled, the enrolment duration will be unlimited by default.';
$string['instanceexists'] = 'Arlo is already synchronised with selected role';
$string['status'] = 'Active';
$string['creategroup'] = 'Create new group';

$string['sendcoursewelcomemessage'] = 'Send course welcome message';
$string['sendcoursewelcomemessage_help'] = 'If enabled, users receive a welcome message via email when they are enrolled in a course.';

$string['defaultgroupnametext'] = '{$a->name} Arlo {$a->increment}';
$string['event'] = 'Event';
$string['events'] = 'Events';
$string['onlineactivity'] = 'Online Activity';
$string['onlineactivities'] = 'Online Activities';

$string['type'] = 'Type of Event';
$string['defaultgroupnametext'] = '{$a->name} Arlo {$a->increment}';
$string['linktemplatetocourse'] = 'Link Arlo template to this course';
$string['remove'] = 'Remove';
$string['template'] = 'Template';

$string['linktemplatenotice'] =
'<div class="alert alert-info" role="alert">
<strong>Notice</strong>
<p>This will add every event or online activity using the template as enrolment instances to this course.</p>
</div>';

$string['warningnotice'] =
'<div class="alert alert-warning" role="alert">
<strong>WARNING</strong>
<p>Removing the template link will remove all people from the course that are in the following enrolment instances:</p>
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
$string['platformname'] = 'Platform name';
$string['platformname_desc'] = 'This is name of platform on Arlo e.g https://{platform}.arlo.co';
$string['apiusername'] = 'API username';
$string['apipassword'] = 'API password';
$string['savechanges'] = 'Save changes';
$string['enrolment'] = 'Enrolment';
