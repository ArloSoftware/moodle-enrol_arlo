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

namespace enrol_arlo\local;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir  . '/gradelib.php');
require_once($CFG->dirroot . '/grade/querylib.php');

use grade_item;
use stdClass;

/**
 * Get a users course grade data.
 *
 * @package     enrol_arlo
 * @copyright   2019 Troy Williams <troy.williams@learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade {

    public static function get_users_course_grade_data(stdClass $course, stdClass $user) {
        global $CFG;

        //  Data object to return.
        $data = new stdClass();
        $data->course = $course;
        $data->user = $user;
        $data->rawgrade = null;
        $data->realgrade = null;
        $data->formattedgrade = null;
        $data->outcome = null;
        $data->dategraded = null;

        // Generate users course grade data.
        $coursegrade = grade_get_course_grade($user->id, $course->id);
        if ($coursegrade && isset($coursegrade->grade)) {
            // Get course grade item.
            $gradeitem = grade_item::fetch_course_item($course->id);
            $data->rawgrade = $coursegrade->grade;
            // Get grade configuration.
            $defaultdisplaytype = isset($CFG->grade_displaytype) ? $CFG->grade_displaytype : 0;
            $displaytype = grade_get_setting($course->id, 'displaytype', $defaultdisplaytype);
            $defaultdecimalpoints = isset($CFG->grade_decimalpoints) ? $CFG->grade_decimalpoints : 0;
            $decimalpoints = grade_get_setting($course->id, 'decimalpoints', $defaultdecimalpoints);
            // Real grade, needed to check if passed.
            $realgrade = grade_format_gradevalue(
                $coursegrade->grade,
                $gradeitem,
                true,
                GRADE_DISPLAY_TYPE_REAL,
                $decimalpoints
            );
            $data->realgrade = $realgrade;
            // Formatted grade to display.
            $formattedgrade = grade_format_gradevalue(
                $coursegrade->grade,
                $gradeitem,
                true,
                $displaytype,
                $decimalpoints
            );
            $data->formattedgrade = $formattedgrade;
            // Graded outcome.
            if ($realgrade >= $gradeitem->gradepass) {
                $data->outcome = get_string('pass', 'enrol_arlo');
            } else {
                $data->outcome = get_string('fail', 'enrol_arlo');
            }
            // Date graded - Epoch.
            if (isset($coursegrade->dategraded)) {
                $data->dategraded = $coursegrade->dategraded;
            }
        }
        return $data;
    }
}
