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
 * Re-attempt enrolment on saved registration.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$id      = required_param('id', PARAM_INT); // ID of registration record.
$confirm = optional_param('confirm', false, PARAM_BOOL);

admin_externalpage_setup('enrolsettingsarloreattemptenrolment',
    null, ['id' => $id], '/enrol/arlo/admin/reattemptenrolment.php');
if (!enrol_is_enabled('arlo')) {
    throw new moodle_exception('plugindisabled');
}
$plugin = enrol_arlo\api::get_enrolment_plugin();
$registration = enrol_arlo\local\persistent\registration_persistent::get_record(['id' => $id]);
if (!$registration) {
    throw new moodle_exception('invalidrecord');
}
if (!$registration->get('enrolmentfailure')) {
    throw new moodle_exception('invalidrecord');
}
$enrolmentinstance = $plugin::get_instance_record($registration->get('enrolid'), MUST_EXIST);
$returnurl = new moodle_url('/enrol/arlo/admin/unsuccessfulenrolments.php');
if ($confirm && confirm_sesskey()) {
    $membershipsjobpersistent = enrol_arlo\local\persistent\job_persistent::get_record(
        [
            'area' => 'enrolment',
            'type' => 'memberships',
            'instanceid' => $enrolmentinstance->id
        ]
    );
    $membershipsjob = enrol_arlo\local\factory\job_factory::create_from_persistent($membershipsjobpersistent);
    if (!$membershipsjob->can_run()) {
        redirect($returnurl, implode('<br>', $membershipsjob->get_reasons()), 1);
    }
    $result = enrol_arlo\local\job\memberships_job::process_enrolment_registration(
        $enrolmentinstance,
        $registration
    );
    if ($result) {
        $message = get_string('success');
    } else {
        $message = get_string('failed', 'enrol_arlo');
    }
    redirect($returnurl, $message, 1);
}
$confirmurl = new moodle_url($PAGE->url, ['confirm' => 1, 'sesskey' => sesskey()]);
$output = $PAGE->get_renderer('enrol_arlo');
$title = get_string('enrol', 'core_enrol');
$PAGE->set_title($title);
$PAGE->set_heading($title);
echo $OUTPUT->header();
$confirmtext = get_string('reattemptenrolmentconfirm', 'enrol_arlo');
echo $OUTPUT->confirm($confirmtext, $confirmurl, $returnurl);
echo $OUTPUT->footer();
