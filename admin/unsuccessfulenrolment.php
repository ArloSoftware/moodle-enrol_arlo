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
 * Enrolment that was unsuccessful.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

admin_externalpage_setup('enrolsettingsarlounsuccessfulenrolment');

$id = required_param('id', PARAM_INT);
$registration = new \enrol_arlo\local\persistent\registration_persistent($id);
if (!$registration) {
    throw new coding_exception('registrationnotfound');
}
$contact = $registration->get_contact();
if (!$contact) {
    throw new coding_exception('contactnotfound');
}
$event = $registration->get_event();
$onlineactivity = $registration->get_online_activity();
$code = ($event) ? $event->get('code') : $onlineactivity->get('code');
$PAGE->set_url('/enrol/arlo/admin/unsuccessfulenrolment.php', ['id' => $id]);
echo $OUTPUT->header();
$params = [
    'fullname' => $contact->get('firstname') . ' ' . $contact->get('lastname'),
    'code' => $code
];
$heading = get_string('unsuccessfulenrolmentof', 'enrol_arlo', $params);
echo $OUTPUT->heading(format_string($heading), 2);
if ($contact->get('userassociationfailure')) {
    $contactmergerequests = \enrol_arlo\local\contact_merge_requests_coordinator::get_active_requests_for_contact($contact);
    if ($contactmergerequests) {
        $renderfields = ['firstname', 'lastname', 'email', 'codeprimary'];
        foreach ($contactmergerequests as $contactmergerequest) {
            $sourcecontact = $contactmergerequest->get_source_contact();
            $destinationcontact = $contactmergerequest->get_destination_contact();
            $sourceuser = $sourcecontact->get_associated_user();
            $destinationuser = $destinationcontact->get_associated_user();
            $table = new html_table();
            $row = new html_table_row([
                'source' => get_string('sourcecontact', 'enrol_arlo'),
                'destination' => get_string('destinationcontact', 'enrol_arlo')]
            );
            $table->data[] = $row;
            $source = '';
            foreach ($renderfields as $renderfield) {
                $source .= $sourcecontact->get($renderfield) . '<br>';
            }
            $destination = '';
            foreach ($renderfields as $renderfield) {
                $destination .= $destinationcontact->get($renderfield) . '<br>';
            }
            $row = new html_table_row(['source' => $source, 'destination' => $destination]);
            $table->data[] = $row;

            $sourceaccessinfo = '';
            $associatedsourcelink = '';
            if ($sourceuser) {
                $url = new moodle_url('/user/profile.php', ['id' => $sourceuser->get('id')]);
                $associatedsourcelink = $OUTPUT->action_link($url, get_string('browseassociateduser', 'enrol_arlo'), null, null);
                $sourceaccessinfo .= ($sourceuser->has_accessed()) ? "Has accessed site<br>" : '';
                $sourceaccessinfo .= ($sourceuser->has_course_enrolments()) ? "Has course enrolments<br>" : '';
                $sourceaccessinfo .= ($sourceuser->has_accessed_courses()) ? "Has accessed enrolments<br>" : '';
            }
            $destinationaccessinfo = '';
            $destinationsourcelink = '';
            if ($sourceuser) {
                $url = new moodle_url('/user/profile.php', ['id' => $destinationuser->get('id')]);
                $destinationsourcelink = $OUTPUT->action_link($url, get_string('browseassociateduser', 'enrol_arlo'), null, null);
                $destinationaccessinfo .= ($destinationuser->has_accessed()) ? "Has accessed site<br>" : '';
                $destinationaccessinfo .= ($destinationuser->has_course_enrolments()) ? "Has course enrolments<br>" : '';
                $destinationaccessinfo .= ($destinationuser->has_accessed_courses()) ? "Has accessed courses<br>" : '';
            }
            $row = new html_table_row(['source' => $associatedsourcelink, 'destination' => $destinationsourcelink]);
            $table->data[] = $row;
            $row = new html_table_row(['source' => $sourceaccessinfo, 'destination' => $destinationaccessinfo]);
            $table->data[] = $row;
            echo html_writer::table($table);
        }
    }
}

echo $OUTPUT->single_button(new moodle_url('/enrol/arlo/admin/unsuccessfulenrolments.php'),
    get_string('returntounsucessfulenrolments', 'enrol_arlo'));
echo $OUTPUT->footer();
