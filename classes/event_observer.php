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
 * Event observer class.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2017 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo;

class event_observer {

    /**
     * Set updatesource field in enrol_arlo_registration table. Fired on cours module update and
     * user graded events. This will inform manager to update result information via registration patch.
     *
     * @param $courseid
     * @param $relateduserid
     */
    private static function set_update_source($courseid, $relateduserid) {
        global $DB;
        $sql = "SELECT ear.* 
                  FROM {enrol} e
                  JOIN {enrol_arlo_registration} ear ON ear.enrolid = e.id
                  JOIN {course} c ON c.id = e.courseid
                 WHERE e.enrol = :enrol AND e.status = :status
                   AND c.id = :courseid AND ear.userid = :relateduserid";
        $conditions = array(
            'enrol' => 'arlo',
            'status' => ENROL_INSTANCE_ENABLED,
            'courseid' => $courseid,
            'relateduserid' => $relateduserid
        );
        $record = $DB->get_record_sql($sql, $conditions);
        if ($record) {
            $DB->set_field('enrol_arlo_registration',
                'updatesource', 1, array('id' => $record->id));
        }
    }

    /**
     * Course module completion event handler. Used for updating results.
     *
     * @param $event
     */
    public static function course_module_completion_updated($event) {
        static::set_update_source($event->courseid, $event->relateduserid);
    }

    /**
     * Course viewed event handler. Update last activity in registration.
     *
     * @param $event
     */
    public static function course_viewed($event) {
        static::set_update_source($event->courseid, $event->relateduserid);
    }

    /**
     * User deleted event handler. Clean up, remove user from enrol_arlo_contact table.
     *
     * @param $event
     */
    public static function user_deleted($event) {
        global $DB;
        $DB->delete_records('enrol_arlo_contact', array('userid' => $event->relateduserid));
    }

    /**
     * User graded event handler. Used for updating results.
     *
     * @param $event
     */
    public static function user_graded($event) {
        static::set_update_source($event->courseid, $event->relateduserid);
    }

    /**
     * On Platform name change fire platform change function.
     *
     * @param $event
     */
    public static function fqdn_updated($event) {
        global $CFG;
        require_once($CFG->dirroot.'/enrol/arlo/locallib.php');
        enrol_arlo_change_platform($event->other['oldvalue'], $event->other['newvalue']);
    }

    /**
     * On created Event check if can add to course with associated Template.
     *
     * @param $event
     */
    public static function event_created($event) {
        global $CFG;
        require_once($CFG->dirroot.'/enrol/arlo/locallib.php');
        enrol_arlo_add_associated(\enrol_arlo_plugin::ARLO_TYPE_EVENT, $event->other);
    }

    /**
     * Handle a update to Event record.
     *
     * @param $event
     */
    public static function event_updated($event) {
        global $CFG;
        require_once($CFG->dirroot.'/enrol/arlo/locallib.php');
        enrol_arlo_handle_update(\enrol_arlo_plugin::ARLO_TYPE_EVENT, $event->other);
    }

    /**
     * On created Online Activity check if can add to course with associated Template.
     *
     * @param $event
     */
    public static function onlineactivity_created($event) {
        global $CFG;
        require_once($CFG->dirroot.'/enrol/arlo/locallib.php');
        enrol_arlo_add_associated(\enrol_arlo_plugin::ARLO_TYPE_ONLINEACTIVITY, $event->other);
    }

    /**
     * Handle a update to Online Activity record.
     *
     * @param $event
     */
    public static function onlineactivity_updated($event) {
        global $CFG;
        require_once($CFG->dirroot.'/enrol/arlo/locallib.php');
        enrol_arlo_handle_update(\enrol_arlo_plugin::ARLO_TYPE_ONLINEACTIVITY, $event->other);
    }
}
