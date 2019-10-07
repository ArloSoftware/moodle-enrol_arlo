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
require_once($CFG->libdir . '/completionlib.php');

use completion_completion;
use completion_info;
use stdClass;

/**
 * Library for getting required course completion data.
 *
 * @package     enrol_arlo
 * @copyright   2019 Troy Williams <troy.williams@learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion {

    /**
     * Get a users completion data for a course. Progress percentage is worked out based on all activities
     * completed in course or 100 if course completion criteria met. See source completion/classes/progress.
     *
     * @param stdClass $course
     * @param stdClass $user
     * @return stdClass
     * @throws \coding_exception
     */
    public static function get_users_course_completion_data(stdClass $course, stdClass $user) {

        //  Data object to return.
        $data = new stdClass();
        $data->course = $course;
        $data->user = $user;
        $data->timeenrolled = null;
        $data->timestarted = null;
        $data->progresspercentage = null;
        $data->progressstatus = get_string('notstarted', 'enrol_arlo');
        $data->timecompleted = null;

        // Harvest course completion data for the user.
        $completion = new completion_info($course);
        if ($completion->is_enabled()) {
            if ($completion->is_tracked_user($user->id)) {
                $params = [
                    'course' => $course->id,
                    'userid' => $user->id
                ];
                $coursecompletion = new completion_completion($params);
                $data->timeenrolled = $coursecompletion->timeenrolled;
                $data->timestarted = $coursecompletion->timestarted;

                if ($coursecompletion->is_complete()) {
                    $data->progresspercentage = 100;
                    $data->timecompleted = $coursecompletion->timecompleted;
                    $data->progressstatus = get_string('completed', 'enrol_arlo');
                } else {
                    $modules = $completion->get_activities();
                    $count = count($modules);
                    if ($count) {
                        $completed = 0;
                        foreach ($modules as $module) {
                            $modulecompletiondata = $completion->get_data($module, true, $user->id);
                            $completed += $modulecompletiondata->completionstate == COMPLETION_INCOMPLETE ? 0 : 1;
                        }
                        $data->progresspercentage =  ($completed / $count) * 100;
                        if ($data->timestarted) {
                            $data->progressstatus = get_string('inprogress', 'enrol_arlo');
                        }
                    }
                }
            }
        }
        return $data;
    }
}
