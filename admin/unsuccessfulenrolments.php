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
 * Enrolments that where unsuccessful.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_arlo\local\tablesql\unsuccessful_enrolments_table_sql;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

admin_externalpage_setup('enrolsettingsarlounsuccessfulenrolments');

$report = new unsuccessful_enrolments_table_sql('enrolsettingsarlounsuccessfulenrolments');
list($sql, $params) = $report->get_sql_and_params(true);
$count = $DB->count_records_sql($sql, $params);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('unsuccessfulenrolmentscount', 'enrol_arlo', $count));
$report->out(unsuccessful_enrolments_table_sql::PAGINATION_MAX_LIMIT, false);
echo $OUTPUT->footer();
