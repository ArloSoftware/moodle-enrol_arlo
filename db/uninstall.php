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
 *  Arlo enrolment plugin uninstall.
 *
 * @author    Troy Williams
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

function xmldb_enrol_arlo_uninstall() {
    global $CFG, $DB;

    $plugin = enrol_get_plugin('arlo');
    $rs = $DB->get_recordset('enrol', array('enrol' => 'arlo'));
    foreach ($rs as $instance) {
        $plugin->delete_instance($instance);
    }
    $rs->close();

    role_unassign_all(array('component' => 'enrol_arlo'));

    // Clear any create password flags.
    $DB->delete_records('user_preferences', array('name' => 'enrol_arlo_createpassword'));

    return true;
}
