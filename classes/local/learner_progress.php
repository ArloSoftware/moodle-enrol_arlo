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
require_once($CFG->libdir  . '/completionlib.php');
require_once($CFG->libdir  . '/gradelib.php');
require_once($CFG->dirroot . '/grade/querylib.php');

use coding_exception;
use completion_completion;
use completion_info;
use core_date;
use DateTime;
use grade_item;
use stdClass;

/**
 * Class for retriving grade, access and completion data used to guage a learners
 * progress in a course.
 *
 * @package     enrol_arlo
 * @copyright   2019 Troy Williams <troy.williams@learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class learner_progress {

    /** @var int CALCULATE_USING_COURSE_COMPLETION Uses course completion setup. */
    const CALCULATE_USING_COURSE_COMPLETION = 1;

    /** @var int CALCULATE_USING_ALL_ACTIVITIES Uses all activities in course that have completion set. */
    const CALCULATE_USING_ALL_ACTIVITIES    = 2;

    /** @var int $courseprogresscalculation The method used to calculate progress in the course. */
    private $courseprogresscalculation;

    /** @var stdClass $course Standard course object. */
    private $course;

    /** @var stdClass $user Standard user object. */
    private $user;

    /** @var int $rawgrade Grade in decimal format 0.00000. */
    private $rawgrade;

    /** @var int $realgrade Real number format. */
    private $realgrade;

    /** @var mixed $formattedgrade The grade formatted, based on configuration. */
    private $formattedgrade;

    /** @var string $outcome Pass/Fail string. */
    private $outcome;

    /** @var int $dategraded Date grade awarded as a epoch. */
    private $dategraded;

    /** @var int $dateenrolled Date learner was enrolled as a epoch. */
    private $dateenrolled;

    /** @var int $datestarted Date learner started activites as epoch. */
    private $datestarted;

    /** @var int $progresspercentage The percentage of activities/or course completed.*/
    private $progresspercentage;

    /** @var string $progressstatus */
    private $progressstatus;

    /** @var int $datecompleted The date learner completed course as a epoch. */
    private $datecompleted;

    /** @var int $datelastcourseaccess The date learer last accessed the course as a epoch. */
    private $datelastcourseaccess;

    /**
     * learner_progress constructor.
     *
     * @param stdClass $course
     * @param stdClass $user
     * @param null $progresscalculation
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function __construct(stdClass $course, stdClass $user, $progresscalculation = null) {
        $this->course = $course;
        $this->user   = $user;
        $availablecalculations =  [self::CALCULATE_USING_COURSE_COMPLETION, self::CALCULATE_USING_ALL_ACTIVITIES];
        if (in_array($progresscalculation, $availablecalculations)) {
            $this->courseprogresscalculation = $progresscalculation;
        } else {
            $this->courseprogresscalculation = self::CALCULATE_USING_COURSE_COMPLETION;
        }
        $this->load_data();
    }

    /**
     * Loads all relevant completion data where available.
     *
     * @throws coding_exception
     */
    protected function load_completion_data() {
        $completion = new completion_info($this->course);
        if ($completion->is_enabled()) {
            if ($completion->is_tracked_user($this->user->id)) {
                $params = [
                    'course' => $this->course->id,
                    'userid' => $this->user->id
                ];
                $coursecompletion = new completion_completion($params);
                if ($coursecompletion->is_complete()) {
                    $this->progresspercentage = 100;
                    $this->dateenrolled = $coursecompletion->timeenrolled;
                    $this->datestarted = $coursecompletion->timestarted;
                    $this->datecompleted = $coursecompletion->timecompleted;
                    $this->progressstatus = get_string('completed', 'enrol_arlo');
                } else {
                    switch ($this->courseprogresscalculation) {
                        case self::CALCULATE_USING_COURSE_COMPLETION:
                            $modules = $completion->get_completions($this->user->id);
                            break;
                        case self::CALCULATE_USING_ALL_ACTIVITIES:
                            $modules = $completion->get_activities();
                            break;
                        default:
                            throw new coding_exception('Unsupported progress cacluation');
                    }
                    $count = count($modules);
                    if ($count) {
                        $completed = 0;
                        foreach ($modules as $module) {
                            $modulecompletiondata = $completion->get_data($module, true, $this->user->id);
                            $completed += $modulecompletiondata->completionstate == COMPLETION_INCOMPLETE ? 0 : 1;
                        }
                        $this->progresspercentage =  ($completed / $count) * 100;
                        if ($coursecompletion->timestarted) {
                            $this->dateenrolled = $coursecompletion->timeenrolled;
                            $this->datestarted = $coursecompletion->timestarted;
                            $this->progressstatus = get_string('inprogress', 'enrol_arlo');
                        } else {
                            $this->progressstatus = get_string('notstarted', 'enrol_arlo');
                        }
                    }
                }
            }
        }
    }

    /**
     * Helper method that calls all data loading methods.
     *
     * @throws \dml_exception
     * @throws coding_exception
     */
    protected function load_data() {
        $this->load_completion_data();
        $this->load_grade_data();
        $this->load_last_access_data();
    }

    /**
     * Loads all relevant grade data where available.
     *
     * @throws coding_exception
     */
    protected function load_grade_data() {
        global $CFG;
        $coursegrade = grade_get_course_grade($this->user->id, $this->course->id);
        if ($coursegrade && isset($coursegrade->grade)) {
            // Get course grade item.
            $gradeitem = grade_item::fetch_course_item($this->course->id);
            $this->rawgrade = $coursegrade->grade;
            // Get grade configuration.
            $defaultdisplaytype = isset($CFG->grade_displaytype) ? $CFG->grade_displaytype : 0;
            $displaytype = grade_get_setting($this->course->id, 'displaytype', $defaultdisplaytype);
            $defaultdecimalpoints = isset($CFG->grade_decimalpoints) ? $CFG->grade_decimalpoints : 0;
            $decimalpoints = grade_get_setting($this->course->id, 'decimalpoints', $defaultdecimalpoints);
            // Real grade, needed to check if passed.
            $realgrade = grade_format_gradevalue(
                $coursegrade->grade,
                $gradeitem,
                true,
                GRADE_DISPLAY_TYPE_REAL,
                $decimalpoints
            );
            $this->realgrade = $realgrade;
            // Formatted grade to display.
            $formattedgrade = grade_format_gradevalue(
                $coursegrade->grade,
                $gradeitem,
                true,
                $displaytype,
                $decimalpoints
            );
            $this->formattedgrade = $formattedgrade;
            // Graded outcome.
            if ($realgrade >= $gradeitem->gradepass) {
                $this->outcome = get_string('pass', 'enrol_arlo');
            } else {
                $this->outcome = get_string('fail', 'enrol_arlo');
            }
            // Date graded - Epoch.
            if (isset($coursegrade->dategraded)) {
                $this->dategraded = $coursegrade->dategraded;
            }
        }
    }

    /**
     * Loads the learners last acccess epoch if available.
     *
     * @throws \dml_exception
     */
    protected function load_last_access_data() {
        global $DB;
        $conditions = ['userid' => $this->user->id, 'courseid' => $this->course->id];
        $lastcourseaccess = $DB->get_field('user_lastaccess', 'timeaccess', $conditions);
        if ($lastcourseaccess) {
            $this->datelastcourseaccess = $lastcourseaccess;
        }
    }

    public function get_course() {
        return $this->course;
    }

    public function get_user() {
        return $this->user;
    }

    public function get_rawgrade() {
        return $this->rawgrade;
    }

    public function get_realgrade() {
        return $this->realgrade;
    }

    public function get_formattedgrade() {
        return $this->formattedgrade;
    }

    public function get_outcome() {
        return $this->outcome;
    }

    public function get_dategraded() {
        return $this->dategraded;
    }

    public function get_dateenrolled() {
        return $this->dateenrolled;
    }

    public function get_datestarted() {
        return $this->datestarted;
    }

    public function get_progresspercentage() {
        return $this->progresspercentage;
    }

    public function get_progressstatus() {
        return $this->progressstatus;
    }

    public function get_datecompleted() {
        return $this->datecompleted;
    }

    public function get_datelastcourseaccess() {
        return $this->datelastcourseaccess;
    }

    /**
     * Helper method to get key/value data to use for Arlo Registration.
     *
     * @return array
     */
    public function get_keyed_data_for_arlo() {
        $data = [];
        $tz = core_date::get_user_timezone_object();
        if ($this->get_datelastcourseaccess()) {
            $lastactivitydate = new DateTime(null, $tz);
            $lastactivitydate->setTimestamp($this->get_datelastcourseaccess());
            $data['LastActivityDateTime'] = $lastactivitydate->format(ENROL_ARLO_DATETIME_OFFSET_FORMAT);
        }
        if ($this->get_progressstatus()) {
            $data['ProgressStatus'] = $this->get_progressstatus();
        }
        if ($this->get_outcome()) {
            $data['Outcome'] = $this->get_outcome();
        }
        if ($this->get_formattedgrade()) {
            $data['Grade'] = $this->get_formattedgrade();
        }
        if ($this->get_progresspercentage()) {
            $data['ProgressPercent'] = $this->get_progresspercentage();
        }
        if ($this->get_progressstatus()) {
            $data['ProgressStatus'] = $this->get_progressstatus();
        }
        if ($this->get_datecompleted()) {
            $completedatetime = new DateTime(null, $tz);
            $completedatetime->setTimestamp($this->get_datecompleted());
            $data['CompletedDateTime'] = $completedatetime->format(ENROL_ARLO_DATETIME_OFFSET_FORMAT);
        }
        return $data;
    }

}
