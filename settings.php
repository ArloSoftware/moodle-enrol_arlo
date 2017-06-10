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
 * @package     enrol_arlo
 * @author      Troy Williams
 * @author      Corey Davis
 * @copyright   2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    // Things that can be used.
    //$ADMIN = $adminroot; // May be used in settings.php.
    //$plugininfo = $this; // Also can be used inside settings.php.
    //$enrol = $this;      // Also can be used inside settings.php.
    //$settings->add(new admin_setting_heading('enrol_arlo_settings', '', get_string('pluginname_desc', 'enrol_arlo')));

    $name = get_string('arloconnection', 'enrol_arlo');
    $settings = new admin_settingpage('enrolsettingsarlo', $name, 'moodle/site:config', $enrol->is_enabled() === false);

    $name = get_string('platformname', 'enrol_arlo');
    $description = get_string('platformname_desc', 'enrol_arlo');
    $settings->add(new admin_setting_configtext('enrol_arlo/platformname', $name, $description, ''));

    $name = get_string('apiusername', 'enrol_arlo');
    $settings->add(new admin_setting_configtext('enrol_arlo/apiusername', $name, '', ''));

    $name = get_string('apipassword', 'enrol_arlo');
    $settings->add(new admin_setting_configpasswordunmask('enrol_arlo/apipassword', $name, '', ''));

    $name = get_string('managearlo', 'enrol_arlo');
    $category = new admin_category('enrolsettingsarlomanage', $name);
    $ADMIN->add('enrolments', $category);

    $ADMIN->add('enrolsettingsarlomanage', new admin_externalpage('enrolsettingsarlostatus',
        $name = new lang_string('pluginstatus', 'enrol_arlo'),
        new moodle_url('/enrol/arlo/admin/status.php')));

    $ADMIN->add('enrolsettingsarlomanage', new admin_externalpage('enrolsettingsarloconfiguration',
        $name = new lang_string('configuration', 'enrol_arlo'),
        new moodle_url('/enrol/arlo/admin/configuration.php')));

    $ADMIN->add('enrolsettingsarlomanage', new admin_externalpage('enrolsettingsarloapiresponselog',
        $name = new lang_string('apilog', 'enrol_arlo'),
        new moodle_url('/enrol/arlo/admin/apilog.php')));

    $ADMIN->add('enrolsettingsarlomanage', new admin_externalpage('enrolsettingsarloemaillog',
        $name = new lang_string('emaillog', 'enrol_arlo'),
        new moodle_url('/enrol/arlo/admin/emaillog.php')));
    
}
