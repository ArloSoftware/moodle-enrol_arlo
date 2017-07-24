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
 * Arlo enrolment plugin settings and presets.
 *
 * Things that are accessable:
 *  - $ADMIN = $adminroot;
 *  - $plugininfo = The Arlo enrolment plugin class;
 *  - $enrol = The Arlo enrolment plugin class;
 *
 * @package     enrol_arlo
 * @author      Troy Williams
 * @author      Corey Davis
 * @copyright   2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once (dirname(__FILE__).'/adminlib.php');

if ($hassiteconfig) {

    $name = get_string('arloconnection', 'enrol_arlo');
    $settings = new admin_settingpage('enrolsettingsarlo', $name, 'moodle/site:config', $enrol->is_enabled() === false);

    $settings->add(new admin_setting_configarlostatus('apistatus', get_string('pluginstatus', 'enrol_arlo')));

    $name = get_string('platform', 'enrol_arlo');
    $description = get_string('platform_desc', 'enrol_arlo');
    $settings->add(new admin_setting_configlockedtext('enrol_arlo/platform', $name, $description, '', PARAM_HOST));

    $name = get_string('apiusername', 'enrol_arlo');
    $settings->add(new admin_setting_configtext('enrol_arlo/apiusername', $name, '', ''));

    $name = get_string('apipassword', 'enrol_arlo');
    $settings->add(new admin_setting_configpasswordunmask('enrol_arlo/apipassword', $name, '', ''));

    // Only display management category if plugin enabled.
    if ($enrol->is_enabled()) {
        $name = get_string('managearlo', 'enrol_arlo');
        $category = new admin_category('enrolsettingsarlomanage', $name);
        $ADMIN->add('enrolments', $category);

        $ADMIN->add('enrolsettingsarlomanage', new admin_externalpage('enrolsettingsarloconfiguration',
            $name = get_string('configuration', 'enrol_arlo'),
            new moodle_url('/enrol/arlo/admin/configuration.php')));

        $ADMIN->add('enrolsettingsarlomanage', new admin_externalpage('enrolsettingsarloapilog',
            $name = get_string('apilog', 'enrol_arlo'),
            new moodle_url('/enrol/arlo/admin/apilog.php')));

        $ADMIN->add('enrolsettingsarlomanage', new admin_externalpage('enrolsettingsarloemaillog',
            $name = get_string('emaillog', 'enrol_arlo'),
            new moodle_url('/enrol/arlo/admin/emaillog.php')));
    }

}
