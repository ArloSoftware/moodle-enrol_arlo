<?php

namespace enrol_arlo;

use stdClass;
use completion_info;
use completion_completion;
use grade_item;
use enrol_arlo\Arlo\AuthAPI\Enum\RegistrationStatus;
use enrol_arlo\Arlo\AuthAPI\Enum\RegistrationOutcome;

class result {
    protected $courseid;
    protected $userid;
    protected static $coursecache = array();
    protected $grade;
    protected $outcome;
    protected $lastactivity;
    protected $progressstatus;
    protected $progresspercent;

    public function __construct($courseid, $userid) {
        $this->courseid = $courseid;
        $this->userid   = $userid;
        self::set_completion_progress_information();
    }

    protected function get_course($courseid) {
        global $DB;
        if (!isset(static::$coursecache[$courseid])) {
            $conditions = array('id' => $courseid);
            $course = $DB->get_record('course', $conditions, '*', MUST_EXIST);
            static::$coursecache[$courseid] = $course;
        }
        return static::$coursecache[$courseid];
    }

    protected function set_completion_progress_information() {
        $course = self::get_course($this->courseid);
        $info = new completion_info($course);
        if ($info->is_tracked_user($this->userid)) {
            $coursecomplete = $info->is_course_complete($this->userid);
            $criteriacomplete = $info->count_course_user_data($this->userid);
            // Load course completion.
            $ccompletion = new completion_completion(array(
                'userid' => $this->userid,
                'course' => $course->id
            ));
            if ($coursecomplete) {
                $this->progresspercent = 100;
                $this->progressstatus = get_string('completed', 'enrol_arlo');
            } else if (!$criteriacomplete && !$ccompletion->timestarted) {
                $this->progressstatus = get_string('notstarted', 'enrol_arlo');
            } else {
                $this->progressstatus = get_string('inprogress', 'enrol_arlo');
                $completions = $info->get_completions($this->userid);
                $totalcriteria = count($completions);
                if ($criteriacomplete) {
                    $this->progresspercent = round(($criteriacomplete / $totalcriteria) * 100, 0);
                }
            }
        }
    }

    protected function has_changed() {}
}