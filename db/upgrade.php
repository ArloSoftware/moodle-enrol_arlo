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
 * @author      Troy Williams
 * @package     Frankenstyle {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright   2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

function xmldb_enrol_arlo_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    // Moodle v2.7.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v2.8.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v2.9.0 release upgrade line.
    // Put any upgrade step following this.

    // Migration step 2 - Setup link table.
    if ($oldversion < 2015101502) {
        // Conditionally install.
        if (!$dbman->table_exists('enrol_arlo_templatelink')) {
            $installfile = $CFG->dirroot . '/enrol/arlo/db/install.xml';
            $dbman->install_from_xmldb_file($installfile);
        }
        upgrade_plugin_savepoint(true, 2015101502, 'enrol', 'arlo');
    }

    return true;
}
