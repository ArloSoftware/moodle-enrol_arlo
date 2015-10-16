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
 *
 * @author      Troy Williams
 * @package     enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright   2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Remove all Arlo instances for a course.
 *
 * @param progress_trace $trace
 * @param $courseid
 * @throws coding_exception
 */
function enrol_arlo_course_remove_all_instances(progress_trace $trace, $courseid) {
    global $DB;

    $plugin = enrol_get_plugin('arlo');
    $templateguid = $DB->get_field('local_arlo_course', 'arloguid', array('courseid' => $courseid), MUST_EXIST);
    $instances = $DB->get_records('enrol', array('enrol' => 'arlo', 'customchar1' => $templateguid));
    foreach ($instances as $instance) {
        if ($plugin->can_delete_instance($instance)) {
            $plugin->delete_instance($instance);
        }
    }
    $DB->delete_records('local_arlo_course', array('courseid' => $courseid));
}

/**
 * Adds or removes Arlo enrolment instances from a course and creates associated groups.
 *
 * Does not sync user enrolments that is handled by enrol_arlo_sync().
 *
 * @param progress_trace $trace
 * @param $courseid
 * @param null $templateguid
 * @throws Exception
 * @throws coding_exception
 * @throws dml_exception
 */
function enrol_arlo_sync_course_instances(progress_trace $trace, $courseid, $templateguid = null) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/group/lib.php');

    // Caches.
    static $templates = array();
    static $courses = array();
    $instances = array();


    $arloinstance = get_config('local_arlo', 'setting_arlo_orgname');
    $plugin = enrol_get_plugin('arlo');

    $student = get_archetype_roles('student');
    $student = reset($student);
    $defaultroleid = $plugin->get_config('roleid', $student->id);

    if (! isset($courses[$courseid])) {
        $courses[$courseid] = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    }

    if (is_null($templateguid)) {
        $templateguid = $DB->get_field('local_arlo_course', 'arloguid', array('courseid' => $courseid), '*', MUST_EXIST);
    }
    if (!isset($templates[$templateguid])) {
        $templates[$templateguid] = $DB->get_record('local_arlo_templates', array('templateguid' => $templateguid), '*', MUST_EXIST);
    }
    // Get current instances and load to cache array.
    $current = $DB->get_records('enrol', array('enrol' => 'arlo', 'customchar1' => $templateguid));
    foreach ($current as $c) {
        $instances[$c->customchar3] = $c;
    }
    // Process events.
    $events = $DB->get_records('local_arlo_events', array('templateguid' => $templateguid));
    foreach ($events as $event) {
        $course = $courses[$courseid];
        $name = $event->code . ' ' . $templates[$templateguid]->name;
        if (isset($instances[$event->eventguid])) {
            // Do we need to remove.
            if ($event->status == 'Cancelled') {
                $instance = $instances[$event->eventguid];
                if ($plugin->can_delete_instance($instance)) {
                    //$plugin->delete_instance($instance);
                    $trace->output("cancelled, remove enrol instance {$instance->name}", 1);
                }
            }
            continue;
        } else {
            $newinstance = array();
            $newinstance['name'] = $name;
            $newinstance['status'] = ENROL_INSTANCE_ENABLED;
            $newinstance['roleid'] = $defaultroleid;
            $newinstance['customint2'] = -1; // Group selected or none.
            $newinstance['customint3'] = ARLO_TYPE_EVENT; // Resource type.
            $newinstance['customchar1'] = $event->templateguid; // Template unique identifier.
            $newinstance['customchar2'] = $arloinstance; // Platform name.
            $newinstance['customchar3'] = $event->eventguid; // Resource unique identifier.
            $newinstance['customint8'] = 1;
            // Create a new group for the arlo if requested.
            if ($newinstance['customint2'] == ARLO_CREATE_GROUP) {
                $groupid = enrol_arlo_create_new_group($course->id, 'local_arlo_events', 'eventguid', $event->eventguid);
                $newinstance['customint2'] = $groupid;
            } else {
                $newinstance['customint2'] = 0;
            }
            $plugin->add_instance($course, $newinstance);
            $trace->output("adding enrol instance for event {$name}", 1);
        }
    }
    // Process online activities.
    $onlineactivities = $DB->get_records('local_arlo_onlineactivities', array('templateguid' => $templateguid));
    foreach ($onlineactivities as $onlineactivity) {
        $course = $courses[$courseid];
        $name = $onlineactivity->code . ' ' . $templates[$templateguid]->name;
        if (isset($instances[$onlineactivity->onlineactivityguid])) {
            // @TODO Do we need to do anything i.e remove statuses?
            continue;
        } else {
            $newinstance = array();
            $newinstance['name'] = $name;
            $newinstance['status'] = ENROL_INSTANCE_ENABLED;
            $newinstance['roleid'] = $defaultroleid;
            $newinstance['customint2'] = -1; // Group selected or none.
            $newinstance['customint3'] = ARLO_TYPE_ONLINEACTIVITY; // Resource type.
            $newinstance['customchar1'] = $onlineactivity->templateguid; // Template unique identifier.
            $newinstance['customchar2'] = $arloinstance; // Platform name.
            $newinstance['customchar3'] = $onlineactivity->onlineactivityguid; // Resource unique identifier.
            $newinstance['customint8'] = 1;
            // Create a new group for the arlo if requested.
            if ($newinstance['customint2'] == ARLO_CREATE_GROUP) {
                $groupid = enrol_arlo_create_new_group($course->id,
                    'local_arlo_onlineactivities', 'onlineactivityguid', $onlineactivity->onlineactivityguid);
                $newinstance['customint2'] = $groupid;
            } else {
                $newinstance['customint2'] = 0;
            }
            $plugin->add_instance($course, $newinstance);
            $trace->output("adding enrol instance for online activity {$name}", 1);
        }
    }
    return;
}
/**
 *
 * A lot of Code borrowed from Cohort enrolment plugin.
 *
 * @param progress_trace $trace
 * @param int $courseid one course, empty mean all
 * @return int 0 means ok, 1 means error, 2 means plugin disabled
 */
function enrol_arlo_sync(progress_trace $trace, $courseid = null) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/group/lib.php');

    // Purge all roles if Arlo sync disabled, those can be recreated later here by cron or CLI.
    if (! enrol_is_enabled('arlo')) {
        $trace->output('Arlo enrolment plugin is disabled, unassigning all plugin roles and stopping.');
        role_unassign_all(array('component' => 'enrol_arlo'));
        return 2;
    }

    // Unfortunately this may take a long time, this script can be interrupted without problems.
    core_php_time_limit::raise();
    raise_memory_limit(MEMORY_HUGE);

    $trace->output('Starting user enrolment synchronisation...');

    $instances = array(); // Cache.

    $plugin = enrol_get_plugin('arlo');

    $student = get_archetype_roles('student');
    $student = reset($student);
    $defaultroleid = $plugin->get_config('roleid', $student->id);
    $unenrolaction = $plugin->get_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);

    // Get mapped Templates and create associated enrolment instances.
    $params = array();
    if ($courseid) {
        $params['courseid'] = $courseid;
    }
    $rs = $DB->get_records('local_arlo_course', $params);
    foreach ($rs as $link) {
        enrol_arlo_sync_course_instances($trace, $link->courseid);
    }

    // Just the one or all. Possible use in enrol and unenrol.
    $onecourse = $courseid ? "AND e.courseid = :courseid" : "";

    // Iterate through all not enrolled yet users.
    $sql = "SELECT u.id AS userid, e.id AS enrolid, r.status AS arlostatus, ue.status
              FROM {user} u
              JOIN {user_info_data} AS uid
                ON (uid.userid = u.id AND u.deleted = 0)
              JOIN {user_info_field} AS uif
                ON (uid.fieldid = uif.id AND uif.shortname = 'arloguid')
              JOIN {local_arlo_registrations} r ON r.contactguid = uid.data
              JOIN {enrol} e ON ((e.customchar3 = r.eventguid OR e.customchar3 = r.onlineactivityguid)
               AND e.enrol = 'arlo' $onecourse)
         LEFT JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = u.id)
             WHERE ue.id IS NULL OR ue.status = :suspended";

    $params = array();
    $params['courseid'] = $courseid;
    $params['suspended'] = ENROL_USER_SUSPENDED;
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach($rs as $ue) {
        if (!isset($instances[$ue->enrolid])) {
            $instances[$ue->enrolid] = $DB->get_record('enrol', array('id' => $ue->enrolid));
        }
        $instance = $instances[$ue->enrolid];
        if ($ue->status == ENROL_USER_SUSPENDED) { // @TODO is this condition needed in Arlo Cancellded is remove?
            //$plugin->update_user_enrol($instance, $ue->userid, ENROL_USER_ACTIVE);
            //$trace->output("unsuspending: $ue->userid ==> $instance->courseid via arlo $instance->customint1", 1);
            //$trace->output('suspended', 1);
        } else {
            // Only people that have 'Approved' or 'Completed' Arlo status get enrolled.
            if ($ue->arlostatus == 'Approved' or $ue->arlostatus == 'Completed') {
                $plugin->enrol_user($instance, $ue->userid, $defaultroleid);
                $trace->output("enrolling: $ue->userid > $instance->courseid via Arlo with status > $ue->arlostatus", 1);
            }
        }
    }
    $rs->close();

    // Unenrol as necessary - Cancelled status.
    $sql = "SELECT ue.*, e.courseid, r.status AS arlostatus
              FROM {user_enrolments} ue
              JOIN {enrol} e
                ON (e.id = ue.enrolid AND e.enrol = 'arlo' $onecourse)
              JOIN {user} u
                ON (u.id = ue.userid)
              JOIN {user_info_data} AS uid
                ON (uid.userid = u.id AND u.deleted = 0)
              JOIN {user_info_field} AS uif
                ON (uid.fieldid = uif.id AND uif.shortname = 'arloguid')
         LEFT JOIN {local_arlo_registrations} r
                ON r.contactguid = uid.data
               AND (e.customchar3 = r.eventguid OR e.customchar3 = r.onlineactivityguid)
             WHERE r.status = :arlostatus ";

    $params = array();
    $params['courseid'] = $courseid;
    $params['arlostatus'] = 'Cancelled';

    $rs = $DB->get_recordset_sql($sql, $params);
    foreach($rs as $ue) {
        if (!isset($instances[$ue->enrolid])) {
            $instances[$ue->enrolid] = $DB->get_record('enrol', array('id'=>$ue->enrolid));
        }
        $instance = $instances[$ue->enrolid];
        if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
            // Remove enrolment together with group membership, grades, preferences, etc.
            $plugin->unenrol_user($instance, $ue->userid);
            $trace->output("unenrolling: $ue->userid > $instance->courseid via Arlo with status > $ue->arlostatus", 1);
        } else { // ENROL_EXT_REMOVED_SUSPENDNOROLES
            // Just disable and ignore any changes.
            if ($ue->status != ENROL_USER_SUSPENDED) {
                $plugin->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
                $context = context_course::instance($instance->courseid);
                $unenrolparams = array();
                $unenrolparams['userid'] = $ue->userid;
                $unenrolparams['contextid'] = $context->id;
                $unenrolparams['component'] = 'enrol_arlo';
                $unenrolparams['itemid'] = $instance->id;
                role_unassign_all($unenrolparams);
                $trace->output("suspending and unassigning all roles: $ue->userid > $instance->courseid", 1);
            }
        }
    }
    $rs->close();
    unset($instances);

    // Sync groups.
    $affectedusers = groups_sync_with_enrolment('arlo', $courseid);
    foreach ($affectedusers['removed'] as $gm) {
        $trace->output("removing user from group: $gm->userid > $gm->courseid - $gm->groupname", 1);
    }
    foreach ($affectedusers['added'] as $ue) {
        $trace->output("adding user to group: $ue->userid > $ue->courseid - $ue->groupname", 1);
    }

    $trace->output('User enrolment synchronisation finished.');

    return 0;
}
