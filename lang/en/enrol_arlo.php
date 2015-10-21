<?php
/**
 * @package     Arlo Moodle Integration
 * @subpackage  enrol_arlo
 * @author 		Corey Davis
 * @copyright   2015 LearningWorks Ltd <http://www.learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Arlo enrolment';
$string['pluginname_desc'] = '<p>These enrolments are managed by local_arlo</p>';

$string['customchar1'] = 'Arlo Template Code';
$string['customchar1_help'] = 'Set this value to the Template Code in Arlo, Warning: Changing this value once set will remove groups associated with the old template.';

$string['notemplatesavali'] = 'There are currently no unassigned templates available to be added to this course.';

$string['welcometocourse'] = 'Welcome to {$a}';
$string['welcometocoursetext'] = 'Welcome to {$a->coursename}!

You may access this course at the following url:

  {$a->courseurl}';

$string['assignedgroup'] = 'Assigned group';
$string['assignrole'] = 'Assign role';
$string['arlo:config'] = 'Configure Arlo instances';
$string['arlo:manage'] = 'Manage Arlo instances';
$string['arlo:unenrol'] = 'Unenrol suspended users';
$string['defaultgroupnametext'] = '{$a->name} Arlo {$a->increment}';
$string['instanceexists'] = 'Arlo is already synchronised with selected role';
$string['status'] = 'Active';
$string['creategroup'] = 'Create new group';

$string['customwelcomemessage'] = 'Custom welcome message';
$string['customwelcomemessage_help'] = 'A custom welcome message may be added as plain text or Moodle-auto format, including HTML tags and multi-lang tags.

The following placeholders may be included in the message:

* Course name {$a->coursename}
* Course url {$a->courseurl}
* User fullname {$a->fullname}';

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
