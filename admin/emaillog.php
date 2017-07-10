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

require_once(__DIR__ . '/../../../config.php');

require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

admin_externalpage_setup('enrolsettingsarloemaillog');

$download = optional_param('download', '', PARAM_ALPHA);

$table = new \enrol_arlo\reports\builder('uniqueid',  array('timelogged', 'type', 'userid', 'delivered', 'extra'));
$table->is_downloading($download, 'emaillog');

// Work out the sql for the table.
$table->set_sql('ael.*, u.firstname, u.lastname', "{enrol_arlo_emaillog} ael JOIN {user} u ON ael.userid = u.id", true);
$table->sort_default_order = SORT_DESC;
$table->define_baseurl("$CFG->wwwroot/enrol/arlo/admin/emaillog.php");


if (!$table->is_downloading()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('emaillog', 'enrol_arlo'));
}

$table->out(10, true);

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}