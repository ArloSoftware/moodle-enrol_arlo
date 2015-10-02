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

$string['addgroup'] = 'Add to group';
$string['assignrole'] = 'Assign role';
$string['arlo:config'] = 'Configure Arlo instances';
$string['arlo:manage'] = 'Manage Arlo instances';
$string['arlo:unenrol'] = 'Unenrol suspended users';
$string['defaultgroupnametext'] = '{$a->name} Arlo {$a->increment}';
$string['instanceexists'] = 'Arlo is already synchronised with selected role';
//$string['pluginname'] = 'Arlo sync';
//$string['pluginname_desc'] = 'Cohort enrolment plugin synchronises cohort members with course participants.';
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
