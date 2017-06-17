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
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2017 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_arlo\plugin_config;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('enrolsettingsarloconfiguration');

$form = new \enrol_arlo\form\admin\configuration();
$data = $form->get_submitted_data();
if ($data) {

    plugin_config::set('authplugin', $data->authplugin);
    plugin_config::set('matchuseraccountsby', $data->matchuseraccountsby);
    plugin_config::set('unenrolaction', $data->unenrolaction);
    plugin_config::set('expiredaction', $data->expiredaction);
    plugin_config::set('pushonlineactivityresults', $data->pushonlineactivityresults);
    plugin_config::set('pusheventresults', $data->pusheventresults);
    plugin_config::set('alertsiteadmins', $data->alertsiteadmins);

    redirect($PAGE->url, get_string('changessaved', 'enrol_arlo'));
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('configuration'));
$form->display();
echo $OUTPUT->footer();
