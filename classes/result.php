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

namespace enrol_arlo;

use stdClass;
use DOMDocument;

use DOMElement;
use completion_info;
use completion_completion;
use grade_item;
use enrol_arlo\Arlo\AuthAPI\Enum\RegistrationStatus;
use enrol_arlo\Arlo\AuthAPI\Enum\RegistrationOutcome;

defined('MOODLE_INTERNAL') || die();

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
            $graderequiredtopass = $gradeitem->gradepass;
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

    protected function set_completion_progress_information() {
        $course = self::get_course($this->courseid);
        $info = new completion_info($course);
        if ($info->has_criteria() && $info->is_tracked_user($this->userid)) {
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
            } else if (empty($ccompletion->timestarted)) {
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

    public function get_changed() {
        $record = new stdClass();
        $fields = array('grade', 'outcome', 'lastactivity', 'progressstatus', 'progresspercent');
        foreach ($fields as $field) {
            if (!is_null($this->{$field}) && $this->{$field} != $this->registrationrecord->{$field}) {
                $record->{$field} = $this->{$field};
            }
        }
        return $record;
    }

    public function has_changed() {
        $fields = array('grade', 'outcome', 'lastactivity', 'progressstatus', 'progresspercent');
        foreach ($fields as $field) {
            if (!is_null($this->{$field}) && $this->{$field} != $this->registrationrecord->{$field}) {
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
        // Setup root element.
        $dom = new DOMDocument('1.0', 'utf-8');
        $root = $dom->appendChild(new DOMElement('diff'));
        // Add or replace Grade element.
        if (empty($registrationrecord->grade) && !empty($this->grade)) {
            $add = $dom->createElement("add");
            $add->setAttribute("sel", "Registration");
            $element = $dom->createElement('Grade', $this->grade);
            $add->appendChild($element);
            $root->appendChild($add);
        } else if ($registrationrecord->grade != $this->grade && !empty($this->grade)) {
            $element = $dom->createElement('replace', $this->grade);
            $element->setAttribute("sel", "Registration/Grade/text()[1]");
            $root->appendChild($element);
        }
        // Add or replace Outcome element.
        if (empty($registrationrecord->outcome) && !empty($this->outcome)) {
            $add = $dom->createElement("add");
            $add->setAttribute("sel", "Registration");
            $element = $dom->createElement('Outcome', $this->outcome);
            $add->appendChild($element);
            $root->appendChild($add);
        } else if ($registrationrecord->outcome != $this->outcome && !empty($this->outcome)) {
            $element = $dom->createElement('replace', $this->outcome);
            $element->setAttribute("sel", "Registration/Outcome/text()[1]");
            $root->appendChild($element);
        }
        // Add or replace LastActivityDateTime element.
        // Must use format: Y-m-d\TH:i:s.0000000+00:00
        // See https://developer.arlo.co/doc/api/2012-02-01/auth/datetimeformats#datetimeoffset.
        if (empty($registrationrecord->lastactivity) && !empty($this->lastactivity)) {
            $lastactivitydate = date('Y-m-d\TH:i:s.0000000+00:00', $this->lastactivity);
            $add = $dom->createElement("add");
            $add->setAttribute("sel", "Registration");
            $element = $dom->createElement('LastActivityDateTime', $lastactivitydate);
            $add->appendChild($element);
            $root->appendChild($add);
        } else if ($registrationrecord->lastactivity != $this->lastactivity && !empty($this->lastactivity)) {
            $lastactivitydate = date('Y-m-d\TH:i:s.0000000+00:00', $this->lastactivity);
            $replace = $dom->createElement('replace', $lastactivitydate);
            $replace->setAttribute("sel", "Registration/LastActivityDateTime/text()[1]");
            $root->appendChild($replace);
        }
        // Add or replace ProgressStatus element.
        if (empty($registrationrecord->progressstatus) && !empty($this->progressstatus)) {
            $add = $dom->createElement("add");
            $add->setAttribute("sel", "Registration");
            $element = $dom->createElement('ProgressStatus', $this->progressstatus);
            $add->appendChild($element);
            $root->appendChild($add);
        } else if ($registrationrecord->progressstatus != $this->progressstatus && !empty($this->progressstatus)) {
            $replace = $dom->createElement('replace', $this->progressstatus);
            $replace->setAttribute("sel", "Registration/ProgressStatus/text()[1]");
            $root->appendChild($replace);
        }
        // Add or replace ProgressPercent element.
        if (empty($registrationrecord->progresspercent) && !empty($this->progresspercent)) {
            $add = $dom->createElement("add");
            $add->setAttribute("sel", "Registration");
            $element = $dom->createElement('ProgressPercent', $this->progresspercent);
            $add->appendChild($element);
            $root->appendChild($add);
        } else if ($registrationrecord->progresspercent != $this->progresspercent && ($this->progresspercent != 0)) {
            $replace = $dom->createElement('replace', $this->progresspercent);
            $replace->setAttribute("sel", "Registration/ProgressPercent/text()[1]");
            $root->appendChild($replace);
        }
        return $dom->saveXML();
    }
}
