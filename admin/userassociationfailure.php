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
 * User association failure report.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

$id = required_param('id', PARAM_INT);
admin_externalpage_setup('enrolsettingsarlouserassociationfailure',
    null, ['id' => $id], '/enrol/arlo/admin/userassociationfailure.php');
$registration = new \enrol_arlo\local\persistent\registration_persistent($id);
$contact = $registration->get_contact();
$event = $registration->get_event();
$onlineactivity = $registration->get_online_activity();
$code = ($event) ? $event->get('code') : $onlineactivity->get('code');
$returnurl = new moodle_url('/enrol/arlo/admin/unsuccessfulenrolments.php');
$output = $PAGE->get_renderer('enrol_arlo');
echo $OUTPUT->header();
$params = [
    'fullname' => $contact->get('firstname') . ' ' . $contact->get('lastname'),
    'code' => $code
];
$heading = get_string('unsuccessfulenrolmentof', 'enrol_arlo', $params);
echo $OUTPUT->heading(format_string($heading), 3);
// Check for duplicate matches.
$duplicatescount = \enrol_arlo\local\job\memberships_job::match_user_from_contact($contact);
if (is_numeric($duplicatescount) && $duplicatescount > 1) {
    $table = new flexible_table('enrolarloduplicatematches');
    $renderfields = ['firstname', 'lastname', 'email', 'idnumber'];
    $columns = [
        'id',
        'firstname',
        'lastname',
        'email',
        'idnumber',
        'browse'
    ];
    $headers = [
        '',
        get_string('firstname'),
        get_string('lastname'),
        get_string('email'),
        get_string('idnumber'),
        ''
    ];
    $table->define_columns($columns);
    $table->define_headers($headers);
    $table->is_collapsible = false;
    $table->sortable(false);
    $table->define_baseurl('/enrol/arlo/admin/userassociationfailure.php');
    $table->setup();
    $matches = \enrol_arlo\local\user_matcher::get_matches_based_on_preference($contact);
    foreach ($matches as $match) {
        $url = new moodle_url('/user/profile.php', ['id' => $match->id]);
        $link = $OUTPUT->action_link($url, get_string('browseassociateduser', 'enrol_arlo'), null, null);
        $row = [
            $match->id,
            $match->firstname,
            $match->lastname,
            $match->email,
            $match->idnumber,
            $link
        ];
        $table->add_data($row);
    }
    $heading = get_string('morethanonemoodleuserfound', 'enrol_arlo');
    echo $OUTPUT->heading(format_string($heading), 3);
    $table->finish_output();
} else {
    redirect($returnurl);
}
echo html_writer::start_div('row float-right');
echo html_writer::start_tag('h4');
echo $OUTPUT->action_link($returnurl,
    get_string('returntounsucessfulenrolments', 'enrol_arlo'));
echo html_writer::end_tag('h4');
echo html_writer::end_div();
echo $OUTPUT->footer();
