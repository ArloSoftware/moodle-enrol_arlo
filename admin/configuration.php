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

use enrol_arlo\local\config\arlo_plugin_config;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

$action = optional_param('action', '', PARAM_ALPHA);
$usernameformat = optional_param('usernameformat', '', PARAM_ALPHA);

admin_externalpage_setup('enrolsettingsarloconfiguration');

$pageurl = clone($PAGE->url);
$pageurl->remove_all_params();
$pluginconfig = new enrol_arlo\local\config\arlo_plugin_config();
switch ($action) {
    case 'moveup':
        if (confirm_sesskey()) {
            if (empty($usernameformat)) {
                throw new moodle_exception('Empty username pattern');
            }
            $usernamegenerator = new enrol_arlo\local\generator\username_generator();
            if (!$usernamegenerator->has_format($usernameformat)) {
                throw new moodle_exception('Non existent username format');
            }
            $usernamegenerator->set_order($pluginconfig->get('usernameformatorder'));
            $neworder = $usernamegenerator->move_format_up_order($usernameformat);
            $pluginconfig->set('usernameformatorder', $neworder);
        }
        redirect($pageurl);
        break;
    case 'movedown':
        if (confirm_sesskey()) {
            if (empty($usernameformat)) {
                throw new moodle_exception('Empty username pattern');
            }
            $usernamegenerator = new enrol_arlo\local\generator\username_generator();
            if (!$usernamegenerator->has_format($usernameformat)) {
                throw new moodle_exception('Non existent username format');
            }
            $usernamegenerator->set_order($pluginconfig->get('usernameformatorder'));
            $neworder = $usernamegenerator->move_format_down_order($usernameformat);
            $pluginconfig->set('usernameformatorder', $neworder);
        }
        redirect($pageurl);
        break;
}

$form = new enrol_arlo\form\admin\configuration();
$data = $form->get_submitted_data();
if ($data) {
    $pluginconfig = new enrol_arlo\local\config\arlo_plugin_config();
    foreach (get_object_vars($data) as $property => $value) {
        if (enrol_arlo\local\config\arlo_plugin_config::has_property($property)) {
            $pluginconfig->set($property, $value);
        }
    }
    redirect($PAGE->url, get_string('changessaved', 'enrol_arlo'));
}
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('configuration'));
$form->display();
echo $OUTPUT->footer();
