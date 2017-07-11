<?php

namespace enrol_arlo;

use stdClass;
use DOMDocument;

use DOMElement;
use completion_info;
use completion_completion;
use grade_item;
use enrol_arlo\Arlo\AuthAPI\Enum\RegistrationStatus;
use enrol_arlo\Arlo\AuthAPI\Enum\RegistrationOutcome;

require_once("$CFG->libdir/completionlib.php");
require_once("$CFG->libdir/gradelib.php");
require_once("$CFG->dirroot/grade/querylib.php");

class result {
    protected $courseid;
    protected $userid;
    protected $registrationrecord;
    protected static $coursecache = array();
    protected $grade;
    protected $outcome;
    protected $lastactivity;
    protected $progressstatus;
    protected $progresspercent;

    public function __construct($courseid, $registrationrecord) {
        $this->courseid             = $courseid;
        $this->registrationrecord   = $registrationrecord;
        $this->userid               = $registrationrecord->userid;
        self::set_completion_progress_information();
        self::set_course_grade_information();
        self::set_course_last_access();
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

    protected function set_course_grade_information() {
        global $CFG;

        $course = self::get_course($this->courseid);
        $userid = $this->userid;
        $defaultdisplaytype = isset($CFG->grade_displaytype) ? $CFG->grade_displaytype : 0;
        $displaytype = grade_get_setting($course->id, 'displaytype', $defaultdisplaytype);
        $defaultdecimalpoints = isset($CFG->grade_decimalpoints) ? $CFG->grade_decimalpoints : 0;
        $decimalpoints = grade_get_setting($course->id, 'decimalpoints', $defaultdecimalpoints);
        $coursegrade = grade_get_course_grade($userid, $course->id);
        if ($coursegrade) {
            // Get course grade item.
            $gradeitem = grade_item::fetch_course_item($course->id);
            // Required grade to pass course.
            if (!empty($gradeitem->gradepass)) {
                $graderequiredtopass = round($gradeitem->gradepass);
                // Real grade, needed to check if passed.
                $realgrade = grade_format_gradevalue($coursegrade->grade,
                    $gradeitem,
                    true,
                    GRADE_DISPLAY_TYPE_REAL,
                    $decimalpoints);
                // Display type of grade.
                $gradetodisplay = grade_format_gradevalue($coursegrade->grade,
                    $gradeitem,
                    true,
                    $displaytype,
                    $decimalpoints);
                // Check if any grade. - denotes no grade yet.
                if ($gradetodisplay != '-') {
                    $this->grade = $gradetodisplay;
                    if ($realgrade >= $graderequiredtopass) {
                        $this->outcome = get_string('pass', 'enrol_arlo');
                    } else {
                        $this->outcome = get_string('fail', 'enrol_arlo');
                    }
                }
            }
        }
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

    protected function set_course_last_access() {
        global $DB;
        $course = self::get_course($this->courseid);
        $conditions = array('courseid' => $course->id, 'userid' => $this->userid);
        $lastcourseaccess = $DB->get_field('user_lastaccess', 'timeaccess', $conditions);
        if ($lastcourseaccess) {
            $this->lastactivity = $lastcourseaccess;
        }
    }

    public function has_changed() {
        $fields = array('grade', 'outcome', 'lastactivity', 'progressstatus', 'progresspercent');
        foreach ($fields as $field) {
            if ($this->{$field} != $this->registrationrecord->{$field}) {
                return true;
            }
        }
        return false;
    }

    public function export_to_xml() {
        if (!self::has_changed()) {
            return '';
        }
        $registrationrecord = $this->registrationrecord;
        $dom = new DOMDocument();
        $root = $dom->appendChild(new DOMElement('diff'));
        if ($registrationrecord->grade != $this->grade) {
            $element = $dom->createElement('replace', $this->grade);
            $element->setAttribute("sel", "Registration/Grade/text()[1]");
            $root->appendChild($element);
        }
        if ($registrationrecord->outcome != $this->outcome) {
            $element = $dom->createElement('replace', $this->outcome);
            $element->setAttribute("sel", "Registration/Outcome/text()[1]");
            $root->appendChild($element);
        }
        if ($registrationrecord->lastactivity != $this->lastactivity) {
            $servertimezone = \core_date::get_server_timezone();
            $tz = new \DateTimeZone($servertimezone);
            $date = \DateTime::createFromFormat('U', $this->lastactivity, $tz);
            $element = $dom->createElement('replace', $date->format(DATE_ISO8601));
            $element->setAttribute("sel", "Registration/LastActivityDateTime/text()[1]");
            $root->appendChild($element);
        }
        if ($registrationrecord->progressstatus != $this->progressstatus) {
            $element = $dom->createElement('replace', $this->progressstatus);
            $element->setAttribute("sel", "Registration/ProgressStatus/text()[1]");
            $root->appendChild($element);
        }
        if ($registrationrecord->progresspercent != $this->progresspercent) {
            $element = $dom->createElement('replace', $this->progresspercent);
            $element->setAttribute("sel", "Registration/ProgressPercent/text()[1]");
            $root->appendChild($element);
        }
        return $dom->saveXML();
    }
}
