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
 * Contact merge failure report.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
admin_externalpage_setup(
    'enrolsettingsarlocontactmergefailure',
    null,
    ['id' => $id],
    '/enrol/arlo/admin/contactmergefailure.php'
);
$registration = new \enrol_arlo\local\persistent\registration_persistent($id);
$contact = $registration->get_contact();
$event = $registration->get_event();
$onlineactivity = $registration->get_online_activity();
$code = ($event) ? $event->get('code') : $onlineactivity->get('code');
$returnurl = new moodle_url('/enrol/arlo/admin/unsuccessfulenrolments.php');
$output = $PAGE->get_renderer('enrol_arlo');
// Check for failed contact merge requests first.
$contactmergerequests = \enrol_arlo\local\persistent\contact_merge_request_persistent::get_records(
    ['destinationcontactid' => $contact->get('sourceid'), 'mergefailed' => 1]
);
if (!$contactmergerequests) {
    redirect($returnurl);
}
// Just deal with first.
$contactmergerequest = reset($contactmergerequests);
$sourcecontact = $contactmergerequest->get_source_contact();
$destinationcontact = $contactmergerequest->get_destination_contact();
// When both contacts resolve to the same Moodle user there is nothing to merge,
// so the merge request can be safely marked as complete.
$sameuser = $sourcecontact && $destinationcontact
    && $sourcecontact->get('userid') > 0
    && $sourcecontact->get('userid') == $destinationcontact->get('userid');
if ($action === 'markcomplete' && confirm_sesskey()) {
    require_capability('moodle/site:config', context_system::instance());
    if (!$sameuser || !enrol_is_enabled('arlo')) {
        throw new moodle_exception('invalidrecord');
    }
    // Mark the merge request as applied.
    $contactmergerequest->set('active', 0);
    $contactmergerequest->set('mergefailed', 0);
    $contactmergerequest->update();
    // Re-attempt the enrolment now that the merge request is resolved.
    $plugin = \enrol_arlo\api::get_enrolment_plugin();
    $enrolmentinstance = $plugin::get_instance_record($registration->get('enrolid'), MUST_EXIST);
    $result = \enrol_arlo\local\job\memberships_job::process_enrolment_registration(
        $enrolmentinstance,
        $registration
    );
    $message = ($result) ? get_string('success') : get_string('failed', 'enrol_arlo');
    redirect($returnurl, $message, 1);
}
echo $OUTPUT->header();
$params = [
    'fullname' => $contact->get('firstname') . ' ' . $contact->get('lastname'),
    'code' => $code,
];
$heading = get_string('unsuccessfulenrolmentof', 'enrol_arlo', $params);
echo $OUTPUT->heading(format_string($heading), 3);
$sourcecontactoutput = new \enrol_arlo\output\contact($sourcecontact, 'source');
$destinationcontactoutput = new \enrol_arlo\output\contact($destinationcontact, 'destination');
echo $OUTPUT->heading(get_string('contactmergerequestfailure', 'enrol_arlo'), 3);
echo html_writer::start_div('container');
echo html_writer::start_div('row');
echo html_writer::start_div('col-sm-6');
echo $output->render($sourcecontactoutput);
echo html_writer::end_div();
echo html_writer::start_div('col-sm-6');
echo $output->render($destinationcontactoutput);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
if ($sameuser) {
    echo $OUTPUT->notification(get_string('contactmergerequestsameuser', 'enrol_arlo'), 'info');
    $markcompleteurl = new moodle_url($PAGE->url, ['action' => 'markcomplete']);
    $markcompletebutton = new single_button($markcompleteurl, get_string('markmergecomplete', 'enrol_arlo'), 'post');
    $markcompletebutton->add_confirm_action(get_string('markmergecompleteconfirm', 'enrol_arlo'));
    echo $OUTPUT->render($markcompletebutton);
}
echo html_writer::start_div('row float-right');
echo html_writer::start_tag('h4');
echo $OUTPUT->action_link(
    $returnurl,
    get_string('returntounsucessfulenrolments', 'enrol_arlo')
);
echo html_writer::end_tag('h4');
echo html_writer::end_div();
echo $OUTPUT->footer();
