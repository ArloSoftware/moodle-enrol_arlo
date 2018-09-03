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

use enrol_arlo\local\tablesql\apirequests;

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

admin_externalpage_setup('enrolsettingsarloapirequests');

$report = new apirequests('enrolsettingsarloapirequests');
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('apirequests', 'enrol_arlo'));
$report->out(apirequests::PAGINATION_MAX_LIMIT, false);
echo $OUTPUT->footer();