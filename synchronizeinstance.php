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
 * Synchronize enrolment instance.
 *
 * @package     enrol_arlo
 * @author      Mathew May
 * @copyright   2017 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
global $DB, $OUTPUT, $PAGE;

$id         = required_param('id', PARAM_INT); // course id
$instanceid = optional_param('instance', 0, PARAM_INT);

$course = $DB->get_record('course', array('id'=>$id), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
require_capability('moodle/course:enrolreview', $context);

$canconfig = has_capability('enrol/arlo:synchronizeinstance', $context);

$PAGE->set_url('/enrol/instances.php', array('id'=>$course->id));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('enrolmentinstances', 'enrol'));
$PAGE->set_heading($course->fullname);

$instance = $DB->get_record('enrol', array('id' => $instanceid,'courseid'=>$id, 'enrol'=>'arlo'), '*', MUST_EXIST);
$plugins   = enrol_get_plugins(false);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('enrol/arlo:synchronizeinstance', 'enrol_arlo'));

if ($canconfig and confirm_sesskey()) {
    $trace = new html_list_progress_trace();
    $manager = new enrol_arlo\manager($trace);
    $manager->process_instance_registrations($instance, true);
} else {
    echo print_error('nopermissions', 'error', '', 'please ensure you are signed in and have permission');
}
echo $OUTPUT->footer();