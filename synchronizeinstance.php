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

require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

$instance = $DB->get_record('enrol', array('id' => $id, 'enrol'=>'arlo'), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $instance->courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
require_capability('enrol/arlo:synchronizeinstance', $context);

$PAGE->set_url('/enrol/instances.php', array('id' => $instance->id));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('enrolmentinstances', 'enrol'));
$PAGE->set_heading($course->fullname);

$returnurl = new moodle_url('/enrol/instances.php', array('id' => $course->id));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('enrol/arlo:synchronizeinstance', 'enrol_arlo'));

if (confirm_sesskey() and $confirm == true) {
    $trace = new html_list_progress_trace();
    $manager = new enrol_arlo\manager($trace);
    $manager->process_instance_registrations($instance, true);
    $manager->process_instance_results($instance, true);
    echo $OUTPUT->single_button($returnurl, get_string('backtoenrolmentmethods', 'enrol_arlo'));
} else if (confirm_sesskey()) {
    $confirmurl = new moodle_url('/enrol/arlo/synchronizeinstance.php', array('confirm' => true, 'sesskey' => sesskey(), 'id' => $instance->id));
    echo $OUTPUT->confirm(get_string('longtime', 'enrol_arlo'), $confirmurl, $returnurl);
} else {
    echo print_error('nopermissions', 'error', '', 'please ensure you are signed in and have permission');
}
echo $OUTPUT->footer();
