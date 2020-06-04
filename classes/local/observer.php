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

use core\event\course_completed;
use core\event\course_viewed;
use enrol_arlo\local\enum\arlo_type;

/**
 * Main Event API Observer class.
 *
 * @package     enrol_arlo
 * @author      Troy Williams
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Handle a course completed event.
     *
     * @param course_completed $event
     * @throws \dml_exception
     */
    public static function course_completed(course_completed $event) {
        $courseid = $event->courseid;
        $userid   = $event->relateduserid;
        static::set_update_source($courseid, $userid);
    }

    /**
     * Set updatesource field in enrol_arlo_registration table. Fired on cours module update and
     * user graded events. This will inform manager to update result information via registration patch.
     *
     * @param $courseid
     * @param $userid
     * @throws \dml_exception
     */
    private static function set_update_source($courseid, $userid) {
        global $DB;
        $sql = "SELECT ear.*
                  FROM {enrol} e
                  JOIN {enrol_arlo_registration} ear ON ear.enrolid = e.id
                  JOIN {course} c ON c.id = e.courseid
                 WHERE e.enrol = :enrol
                   AND c.id = :courseid AND ear.userid = :userid";
        $conditions = [
            'enrol' => 'arlo',
            'courseid' => $courseid,
            'userid' => $userid
        ];
        $registrations = $DB->get_records_sql($sql, $conditions);
        if ($registrations) {
            foreach ($registrations as $registration) {
                $result = $DB->set_field('enrol_arlo_registration',
                    'updatesource', 1, ['id' => $registration->id]);
            }
        }
    }

    /**
     * Course module completion event handler. Used for updating results.
     *
     * @param $event
     * @throws \dml_exception
     */
    public static function course_module_completion_updated($event) {
        $courseid = $event->courseid;
        $userid   = $event->relateduserid;
        static::set_update_source($courseid, $userid);
    }

    /**
     * Course viewed event handler. Update last activity in registration.
     *
     * @param $event
     * @throws \dml_exception
     */
    public static function course_viewed(course_viewed $event) {
        $courseid = $event->courseid;
        $userid   = $event->userid;
        static::set_update_source($courseid, $userid);
    }

    /**
     * User deleted event handler. Clean up, remove user from enrol_arlo_contact table.
     *
     * @param $event
     * @throws \dml_exception
     */
    public static function user_deleted($event) {
        global $DB;
        $DB->delete_records('enrol_arlo_contact', ['userid' => $event->relateduserid]);
        $DB->delete_records('enrol_arlo_registration', ['userid' => $event->relateduserid]);
        $DB->delete_records('enrol_arlo_emailqueue', ['userid' => $event->relateduserid]);
    }

    /**
     * User graded event handler. Used for updating results.
     *
     * @param $event
     * @throws \dml_exception
     */
    public static function user_graded($event) {
        $courseid = $event->courseid;
        $userid   = $event->relateduserid;
        static::set_update_source($courseid, $userid);
    }

    /**
     * On created Event check if can add to course with associated Template.
     *
     * @param $event
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function event_created($event) {
        global $CFG;
        require_once($CFG->dirroot . '/enrol/arlo/locallib.php');
        enrol_arlo_add_associated(arlo_type::EVENT, $event->other);
    }

    /**
     * On Platform name change fire platform change function.
     *
     * @param $event
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function fqdn_updated($event) {
        global $CFG;
        require_once($CFG->dirroot . '/enrol/arlo/locallib.php');
        $oldvalue = $event->other['oldvalue'];
        $newvalue = $event->other['newvalue'];
        enrol_arlo_change_platform($oldvalue, $newvalue);
    }

    /**
     * On created Online Activity check if can add to course with associated Template.
     *
     * @param $event
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public static function onlineactivity_created($event) {
        global $CFG;
        require_once($CFG->dirroot . '/enrol/arlo/locallib.php');
        enrol_arlo_add_associated(arlo_type::ONLINEACTIVITY, $event->other);
    }

}
