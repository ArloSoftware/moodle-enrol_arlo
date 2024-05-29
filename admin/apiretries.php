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

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

admin_externalpage_setup('enrolsettingsarloapiretries');
$action = optional_param('action', null, PARAM_ALPHA);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('apiretries', 'enrol_arlo'));

if ($action === 'enablecommunication') {
    $plugin = api::get_enrolment_plugin();
    $pluginconfig = $plugin->get_plugin_config();
    set_config('enablecommunication', 1, 'enrol_arlo');
    $pluginconfig->set('enablecommunication', get_config('enrol_arlo','enablecommunication'));
    set_config('redirectcount', 0, 'enrol_arlo');
    $pluginconfig->set('redirectcount', get_config('enrol_arlo','redirectcount'));
    echo $OUTPUT->notification(get_string('communication_enabled_message', 'enrol_arlo'),\core\output\notification::NOTIFY_SUCCESS);
}
$report = new apiretries('enrolsettingsarloapiretries');
$report->out(apiretries::PAGINATION_MAX_LIMIT, false);
$url = new moodle_url($PAGE->url, ['action'=>'resubmit']);

$url = new moodle_url($PAGE->url, ['action' => 'enablecommunication']);
echo $OUTPUT->single_button($url, "Enable communication", 'get');
echo $OUTPUT->footer();