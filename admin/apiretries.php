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
 * API retries error report page
 *
 * @package     enrol_arlo
 * @copyright   2023 Moodle US
 * @author      Nathan Hunt {nathan.hunt@moodle.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_arlo\api;
use enrol_arlo\local\tablesql\apiretries;
use enrol_arlo\adminsettings\configarlostatus;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/enrol/arlo/locallib.php');

admin_externalpage_setup('enrolsettingsarloapiretries');
$action = optional_param('action', null, PARAM_ALPHA);
$course = optional_param('course', null, PARAM_INT);
$regid = optional_param('regid', null, PARAM_INT);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('apiretries', 'enrol_arlo'));

[$arlostatus, $desc] = configarlostatus::api_status_render();
$plugin = api::get_enrolment_plugin();
$pluginconfig = $plugin->get_plugin_config();
echo '<div class="communication-buttons d-flex align-items-center mb-3">';
echo get_string('connectionstatus', 'enrol_arlo') . $arlostatus;

if (empty($pluginconfig->get('enablecommunication'))) {
    echo $OUTPUT->single_button(new moodle_url($PAGE->url, ['action' => 'enablecommunication']), get_string('enablecommunication', 'enrol_arlo'));
}

if (!empty($pluginconfig->get('redirectcount'))) {
    echo get_string('apifails', 'enrol_arlo') . $pluginconfig->get('redirectcount');
    echo $OUTPUT->single_button(new moodle_url($PAGE->url, ['action' => 'resetredirects']), get_string('resetredirects', 'enrol_arlo'));
}
echo '</div>';

if ($action === 'enablecommunication') {
    enrol_arlo_enablecommunication();
    echo $OUTPUT->notification(get_string('communication_enabled_message', 'enrol_arlo'),\core\output\notification::NOTIFY_SUCCESS);
}

if ($action === 'resetredirects') {
    enrol_arlo_reset_redirects($regid);
    echo $OUTPUT->notification(get_string('resetretries_message', 'enrol_arlo'),\core\output\notification::NOTIFY_SUCCESS);
}

// This will be hidden from the user interface, will only work if the params are set manually through the URL.
if ($action === 'updateall' && !empty($course)) {
    enrol_arlo_update_all_course_registrations($course);
}

$report = new apiretries('enrolsettingsarloapiretries');
$report->out(apiretries::PAGINATION_MAX_LIMIT, false);

echo $OUTPUT->footer();