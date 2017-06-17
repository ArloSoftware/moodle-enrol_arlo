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
 *  Extra install actions.
 *
 * @author    Troy Williams
 * @author    Corey Davis
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_arlo\plugin_config;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

function xmldb_enrol_arlo_install() {
    global $CFG, $DB;

    plugin_config::set('apistatus', plugin_config::get_default('apistatus'));
    plugin_config::set('authplugin', plugin_config::get_default('authplugin'));
    plugin_config::set('matchuseraccountsby', plugin_config::get_default('matchuseraccountsby'));
    plugin_config::set('unenrolaction', plugin_config::get_default('unenrolaction'));
    plugin_config::set('expiredaction', plugin_config::get_default('expiredaction'));
    plugin_config::set('pushonlineactivityresults', plugin_config::get_default('pushonlineactivityresults'));
    plugin_config::set('pusheventresults', plugin_config::get_default('pusheventresults'));
    plugin_config::set('alertsiteadmins', plugin_config::get_default('alertsiteadmins'));

    return true;
}
