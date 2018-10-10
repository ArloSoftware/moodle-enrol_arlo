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
 * Unenrol contact from all Arlo enrolment instances.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$id      = required_param('id', PARAM_INT); // ID of contact record.
$confirm = optional_param('confirm', false, PARAM_BOOL);

admin_externalpage_setup('enrolsettingsarlounenrolcontact',
    null, ['id' => $id], '/enrol/arlo/admin/unenrolcontact.php');

if (!enrol_is_enabled('arlo')) {
    throw new moodle_exception('plugindisabled');
}
$returnurl = new moodle_url('/enrol/arlo/admin/unsuccessfulenrolments.php');
$plugin = enrol_arlo\api::get_enrolment_plugin();
$contact = new enrol_arlo\local\persistent\contact_persistent($id);
$contactoutput = new enrol_arlo\output\contact($contact);
$user = $contact->get_associated_user();
$userrecord = $user->to_record();
if ($confirm && confirm_sesskey()) {
    // Arlo enrolments instances the user belongs to.
    $sql = "SELECT e.*
              FROM {enrol} e
              JOIN {user_enrolments} ue ON ue.enrolid = e.id
             WHERE e.enrol = 'arlo' AND ue.userid = :userid";
    $params = ['userid' => $userrecord->id];
    $instances = $DB->get_records_sql($sql, $params);
    foreach ($instances as $instance) {
        if ($plugin->allow_unenrol($instance)) {
            $plugin->unenrol($instance, $userrecord, ENROL_EXT_REMOVED_UNENROL);
        }
    }
    redirect($returnurl, get_string('enrolmentwillbeattemptedagain', 'enrol_arlo'), 5);
}
$confirmurl = new moodle_url($PAGE->url, ['confirm' => 1, 'sesskey' => sesskey()]);
$output = $PAGE->get_renderer('enrol_arlo');
$title = get_string('unenrol', 'core_enrol');
$PAGE->set_title($title);
$PAGE->set_heading($title);
echo $OUTPUT->header();
$unenrolconfirm = get_string('removeallarloenrolmentsquestion', 'enrol_arlo', fullname($userrecord));
echo $OUTPUT->confirm($unenrolconfirm, $confirmurl, $returnurl);
echo $OUTPUT->footer();
