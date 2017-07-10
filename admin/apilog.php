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
 * @author    Mathew May
 * @copyright 2017 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


use enrol_arlo\plugin_config;

require_once(__DIR__ . '/../../../config.php');

global $CFG,$PAGE,$OUTPUT;

require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

admin_externalpage_setup('enrolsettingsarloapilog');

$download = optional_param('download', '', PARAM_ALPHA);

$table = new \enrol_arlo\reports\report_builder('uniqueid',  array('timelogged', 'platform', 'uri', 'status', 'extra'));
$table->is_downloading($download, 'apilog');

// Work out the sql for the table.
$table->set_sql('*', "{enrol_arlo_requestlog}", '1');
$table->sort_default_order = SORT_DESC;

$table->define_baseurl("$CFG->wwwroot/enrol/arlo/admin/apilog.php");

if (!$table->is_downloading()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('apilog', 'enrol_arlo'));
}

$table->out(10, true);

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}