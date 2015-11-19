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
 * Removes all Arlo enrolment instances for events that have been cancelled. If course
 * identifier is passed in it will remove all Arlo instances for that course and deletes
 * any template association - link.
 *
 * @param progress_trace $trace
 * @param null $courseid
 * @return bool
 * @throws coding_exception
 */
function enrol_arlo_remove_instances(progress_trace $trace, $courseid = null) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/group/lib.php');

    $plugin = enrol_get_plugin('arlo');

    // Remove all Arlo enrolment instances in a course.
    if (!is_null($courseid)) {
        $instances = $DB->get_records('enrol', array('enrol' => 'arlo', 'courseid' => $courseid));
        foreach ($instances as $instance) {
            if ($plugin->can_delete_instance($instance)) {
                $plugin->delete_instance($instance);
                groups_delete_group($instance->customint2);
                $trace->output("Removed Arlo enrolment instance: {$instance->name}");
            }
        }
        // Delete template link if any.
        $DB->delete_records('enrol_arlo_templatelink', array('courseid' => $courseid));
        return true;
    }
    // Remove events that have been cancelled.
    $sql = "SELECT en.*, ev.status AS eventstatus
              FROM {enrol} en
         LEFT JOIN {local_arlo_events} ev ON ev.eventguid = en.customchar3
             WHERE en.enrol = 'arlo' AND ev.status = :status";
    $params = array('status' => 'Cancelled');
    $instances = $DB->get_records_sql($sql, $params);
    foreach ($instances as $instance) {
        if ($plugin->can_delete_instance($instance)) {
            $plugin->delete_instance($instance);
            groups_delete_group($instance->customint2);
            $trace->output("Removed Arlo enrolment instance: {$instance->name}");
        }
    }
    return true;
}

/**
 * Create Arlo enrolment instances in a course that is linked to a Arlo template.
 *
 * @param progress_trace $trace
 * @param $courseid
 * @return bool
 * @throws Exception
 * @throws coding_exception
 * @throws dml_exception
 */
function enrol_arlo_create_instances_from_template(progress_trace $trace, $courseid) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/group/lib.php');

    $course = $DB->get_record('course', array('id' => $courseid));
    if (!$course) {
        return false;
    }

    $currentinstances = array();
    $templateassociations = array();

    $arloinstance = get_config('local_arlo', 'setting_arlo_orgname');
    $plugin = enrol_get_plugin('arlo');

    $student = get_archetype_roles('student');
    $student = reset($student);
    $defaultroleid = $plugin->get_config('roleid', $student->id);

    $templateguid = $DB->get_field('enrol_arlo_templatelink', 'templateguid', array('courseid' => $courseid));
    if (! $templateguid) {
        $this->trace("No Arlo template associated with Moodle course {$course->shortname}");
        return false;
    }
    // Get full template information.
    $template = $DB->get_record('local_arlo_templates', array('templateguid' => $templateguid), '*', MUST_EXIST);
    if ($template->status == 'Cancelled') {
        $trace->output("Template cancelled, don't add anything.", 1);
    }
    // Get all event associated with the template.
    $events = $DB->get_records('local_arlo_events', array('templateguid' => $templateguid));
    foreach ($events as $event) {
        $event->type = ARLO_TYPE_EVENT;
        $templateassociations[$event->eventguid] = $event;
    }
    // Get all online activities associated with the template.
    $onlineactivities = $DB->get_records('local_arlo_onlineactivities', array('templateguid' => $templateguid));
    foreach ($onlineactivities as $onlineactivity) {
        $onlineactivity->type = ARLO_TYPE_ONLINEACTIVITY;
        $templateassociations[$onlineactivity->onlineactivityguid] = $onlineactivity;
    }
    // Get current Arlo enrolment instances. Will use to check against later on.
    $enrolinstances = $DB->get_records('enrol', array('enrol' => 'arlo', 'courseid' => $course->id));
    foreach ($enrolinstances as $enrolinstance) {
        $currentinstances[$enrolinstance->customchar3] = $enrolinstance;
    }
    // Process template associations creating enrolment instances if missing.
    foreach ($templateassociations as $templateassociation) {
        $name = $templateassociation->code . ' ' . $template->name;
        // Arlo event.
        if ($templateassociation->type == ARLO_TYPE_EVENT) {
            $customint3 = ARLO_TYPE_EVENT;
            $customchar1 = $templateassociation->templateguid;
            $customchar3 = $templateassociation->eventguid;
            $table = 'local_arlo_events';
            $field = 'eventguid';
        }
        // Arlo online activity.
        if ($templateassociation->type == ARLO_TYPE_ONLINEACTIVITY) {
            $customint3 = ARLO_TYPE_ONLINEACTIVITY;
            $customchar1 = $templateassociation->templateguid;
            $customchar3 = $templateassociation->onlineactivityguid;
            $table = 'local_arlo_onlineactivities';
            $field = 'onlineactivityguid';
        }
        // Can we create.
        if (isset($currentinstances[$customchar3])) {
            // Enrolment instance already exists, do nothing.
            continue;
        } else {
            // Is already 'Cancelled' don't bother adding.
            if ($templateassociation->status == 'Cancelled') {
                $trace->output("cancelled, don't add event {$name}", 1);
                continue;
            }
            $newinstance = array();
            $newinstance['name'] = $name;
            $newinstance['status'] = ENROL_INSTANCE_ENABLED;
            $newinstance['roleid'] = $defaultroleid;
            $newinstance['customint2'] = -1; // Group selected or none.
            $newinstance['customint3'] = $customint3; // Resource type.
            $newinstance['customchar1'] = $customchar1; // Template unique identifier.
            $newinstance['customchar2'] = $arloinstance; // Platform name.
            $newinstance['customchar3'] = $customchar3; // Resource unique identifier.
            $newinstance['customint8'] = 1;
            // Create a new group for the arlo if requested.
            if ($newinstance['customint2'] == ARLO_CREATE_GROUP) {
                $groupid = enrol_arlo_create_new_group($course->id,
                    $table, $field, $customchar3);
                $newinstance['customint2'] = $groupid;
            } else {
                $newinstance['customint2'] = 0;
            }
            $plugin->add_instance($course, $newinstance);
            $trace->output("adding enrol instance for online activity {$name}", 1);
        }
    }
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

    raise_memory_limit(MEMORY_HUGE);

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

    // Caches.
    $instances = array();

    $plugin = enrol_get_plugin('arlo');

    $student = get_archetype_roles('student');
    $student = reset($student);
    $defaultroleid = $plugin->get_config('roleid', $student->id);
    $unenrolaction = $plugin->get_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);

    // Process enrolments.
    $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
    // Construct sql and params for enrolments, registrations with status approved or completed.
    list($insql, $inparams) = $DB->get_in_or_equal(array('Approved', 'Completed'), SQL_PARAMS_NAMED);
    // Enrolments sql.
    $sql = "SELECT u.id AS userid, e.id AS enrolid, ue.status
              FROM {user} u
              JOIN {user_info_data} AS uid
                ON (uid.userid = u.id AND u.deleted = 0)
              JOIN {user_info_field} AS uif
                ON (uid.fieldid = uif.id AND uif.shortname = 'arloguid')
              JOIN {local_arlo_registrations} r ON r.contactguid = uid.data
              JOIN {enrol} e ON ((e.customchar3 = r.eventguid OR e.customchar3 = r.onlineactivityguid)
               AND e.enrol = 'arlo' AND e.status = :instenabled $onecourse)
         LEFT JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = u.id)
             WHERE (ue.id IS NULL OR ue.status = :suspended)
               AND r.status $insql";
    //Enrolment params.
    $params = array();
    $params['courseid'] = $courseid;
    $params['instenabled'] = ENROL_INSTANCE_ENABLED;
    $params['suspended'] = ENROL_USER_SUSPENDED;
    $params = array_merge($params, $inparams);
    // Get records and iterate.
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach($rs as $ue) {
        // Fetch enrolment instance from cache or get from DB and save to cache.
        if (!isset($instances[$ue->enrolid])) {
            $instances[$ue->enrolid] = $DB->get_record('enrol', array('id' => $ue->enrolid));
        }
        // Get enrolment instance.
        $instance = $instances[$ue->enrolid];
        if ($ue->status == ENROL_USER_SUSPENDED) { // @TODO is this condition needed in Arlo Cancellded is remove?
            //$plugin->update_user_enrol($instance, $ue->userid, ENROL_USER_ACTIVE);
            //$trace->output("unsuspending: $ue->userid ==> $instance->courseid via arlo $instance->customint1", 1);
            //$trace->output('suspended', 1);
        } else {
            $plugin->enrol_user($instance, $ue->userid, $defaultroleid);
            $trace->output("enrolling: userid $ue->userid > courseid $instance->courseid", 1);
            $user = core_user::get_user($ue->userid);
            // Send welcome message.
            if ($instance->customint8) {
                $plugin->email_welcome_message($instance, $user);
            }
        }
    }
    $rs->close();

    // Process withdrawals.
    $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
    // Construct sql and params for withdrawals, registrations with status cancelled.
    list($insql, $inparams) = $DB->get_in_or_equal(array('Cancelled'), SQL_PARAMS_NAMED);
    // Withdrawals sql.
    $sql = "SELECT ue.*, e.courseid, r.flag
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
             WHERE r.status $insql";
    // Withdrawal params.
    $params = array();
    $params['courseid'] = $courseid;
    $params = array_merge($params, $inparams);
    // Get records and iterate.
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach($rs as $ue) {
        // Record been flagged then skip.
        if ($ue->flag) {
            continue;
        }
        if (!isset($instances[$ue->enrolid])) {
            $instances[$ue->enrolid] = $DB->get_record('enrol', array('id'=>$ue->enrolid));
        }
        $instance = $instances[$ue->enrolid];
        if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
            // Remove enrolment together with group membership, grades, preferences, etc.
            $plugin->unenrol_user($instance, $ue->userid);
            $trace->output("unenrolling: $ue->userid > $instance->courseid", 1);
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

    // Remove user enrolments that don't have registration (Change/transfers) of registrations.
    $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
    // Remove sql.
    $sql = "SELECT ue.*
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
                ON ((e.customchar3 = r.eventguid) OR (e.customchar3 = r.onlineactivityguid))
               AND uid.data = r.contactguid
             WHERE r.contactguid IS NULL";
    // Remove params.
    $params = array();
    $params['courseid'] = $courseid;
    // Get records and iterate.
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach($rs as $ue) {
        if (!isset($instances[$ue->enrolid])) {
            $instances[$ue->enrolid] = $DB->get_record('enrol', array('id'=>$ue->enrolid));
        }
        $instance = $instances[$ue->enrolid];
        // Remove enrolment together with group membership, grades, preferences, etc.
        $plugin->unenrol_user($instance, $ue->userid);
        $trace->output("unenrolling: $ue->userid > $instance->courseid", 1);
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
