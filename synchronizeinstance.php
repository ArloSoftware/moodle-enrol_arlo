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
$full = optional_param('full', 0, PARAM_INT);

$instance = $DB->get_record('enrol', array('id' => $id, 'enrol' => 'arlo'), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $instance->courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
require_capability('enrol/arlo:synchronizeinstance', $context);

$PAGE->set_url('/enrol/arlo/synchronizeinstance.php', array('id' => $instance->id, 'sesskey' => sesskey()));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('enrolmentinstances', 'enrol'));
$PAGE->set_heading($course->fullname);

$returnurl = new moodle_url('/enrol/instances.php', array('id' => $course->id));
$returnstring = get_string('backtoenrolmentmethods', 'enrol_arlo');

if (confirm_sesskey() and $confirm == true) {
    try {
        // Run enrolment job.
        $membershipsjobpersistent = enrol_arlo\local\persistent\job_persistent::get_record(
            [
                'area' => 'enrolment',
                'type' => 'memberships',
                'instanceid' => $instance->id
            ]
        );
        if ($full) {
            $membershipsjobpersistent->set('lastsourceid', 0);
            $membershipsjobpersistent->set('lastsourcetimemodified', '1970-01-01T00:00:00Z');
            $membershipsjobpersistent->set('timelastrequest', 0);
            $membershipsjobpersistent->save();
            $DB->set_field('enrol_arlo_registration', 'updatesource', 1, ['enrolid' => $instance->id]);
        }
        $membershipsjob = enrol_arlo\local\factory\job_factory::create_from_persistent($membershipsjobpersistent);
        $status = $membershipsjob->run();
        // Run outcomes job.
        $outcomesjobpersistent = enrol_arlo\local\persistent\job_persistent::get_record(
            [
                'area' => 'enrolment',
                'type' => 'outcomes',
                'instanceid' => $instance->id
            ]
        );
        $outcomesjob = enrol_arlo\local\factory\job_factory::create_from_persistent($outcomesjobpersistent);
        $status = $outcomesjob->run();
    } catch (moodle_exception $exception) {
        if ($exception->getMessage() == 'error/locktimeout') {
            redirect($returnurl, get_string('synchroniseoperationiscurrentlylocked', 'enrol_arlo'), 1);
        }
    }
    redirect($returnurl);
} else if (confirm_sesskey()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('synchroniseinstancefor', 'enrol_arlo', $instance->name));
    $params = array('confirm' => true, 'full' => $full,'sesskey' => sesskey(), 'id' => $instance->id);
    $confirmurl = new moodle_url('/enrol/arlo/synchronizeinstance.php', $params);
    echo $OUTPUT->notification(
        get_string('manualsynchronisenotice', 'enrol_arlo'),
        'warning'
    );
    echo $OUTPUT->confirm(get_string('manualsynchronisenotice', 'enrol_arlo'), $confirmurl, $returnurl);
} else {
    echo $OUTPUT->header();
    echo print_error('nopermissions', 'error', '', 'please ensure you are signed in and have permission');
}
echo $OUTPUT->footer();
