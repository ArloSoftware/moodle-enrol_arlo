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

    $arloinstance = get_config('enrol_arlo', 'platform');
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
    $template = $DB->get_record('enrol_arlo_template', array('sourceguid' => $templateguid), '*', MUST_EXIST);
    if ($template->sourcestatus == 'Cancelled') {
        $trace->output("Template cancelled, don't add anything.", 1);
    }
    // Get all event associated with the template.
    $events = $DB->get_records('enrol_arlo_event', array('sourcetemplateguid' => $templateguid));
    foreach ($events as $event) {
        $event->type = \enrol_arlo_plugin::ARLO_TYPE_EVENT;
        $templateassociations[$event->sourceguid] = $event;
    }
    // Get all online activities associated with the template.
    $onlineactivities = $DB->get_records('enrol_arlo_onlineactivity', array('sourcetemplateguid' => $templateguid));
    foreach ($onlineactivities as $onlineactivity) {
        $onlineactivity->type = \enrol_arlo_plugin::ARLO_TYPE_ONLINEACTIVITY;
        $templateassociations[$onlineactivity->sourceguid] = $onlineactivity;
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
        if ($templateassociation->type == \enrol_arlo_plugin::ARLO_TYPE_EVENT) {
            if (!empty($currentinstances) == true) {
                // Enrolment instance already exists, do nothing.
                continue;
            } else {
                if ($templateassociation->sourcestatus == 'Cancelled') {
                    $trace->output("cancelled, don't add event {$name}", 1);
                    continue;
                }
                $plugin = new \enrol_arlo_plugin();

                $newinstance = $plugin->get_instance_defaults();
                $newinstance['status'] = ENROL_INSTANCE_ENABLED;
                $newinstance['arlotype'] = \enrol_arlo_plugin::ARLO_TYPE_EVENT;
                $newinstance['arloevent'] = $templateassociation->sourceguid;
                $plugin->add_instance($course, $newinstance);
            }
        }
        // Arlo online activity.
        if ($templateassociation->type == \enrol_arlo_plugin::ARLO_TYPE_ONLINEACTIVITY) {
            if (!empty($currentinstances) == true) {
                // Enrolment instance already exists, do nothing.
                continue;
            } else {
                if ($templateassociation->sourcestatus == 'Cancelled') {
                    $trace->output("cancelled, don't add event {$name}", 1);
                    continue;
                }
                $plugin = new \enrol_arlo_plugin();

                $newinstance = $plugin->get_instance_defaults();
                $newinstance['status'] = ENROL_INSTANCE_ENABLED;
                $newinstance['arlotype'] = \enrol_arlo_plugin::ARLO_TYPE_ONLINEACTIVITY;
                $newinstance['arloonlineactivity'] = $templateassociation->sourceguid;
                $plugin->add_instance($course, $newinstance);
            }
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
    // Enrolment params.
    $params = array();
    $params['courseid'] = $courseid;
    $params['instenabled'] = ENROL_INSTANCE_ENABLED;
    $params['suspended'] = ENROL_USER_SUSPENDED;
    $params = array_merge($params, $inparams);
    // Get records and iterate.
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $ue) {
        // Fetch enrolment instance from cache or get from DB and save to cache.
        if (!isset($instances[$ue->enrolid])) {
            $instances[$ue->enrolid] = $DB->get_record('enrol', array('id' => $ue->enrolid));
        }
        // Get enrolment instance.
        $instance = $instances[$ue->enrolid];
        if ($ue->status == ENROL_USER_SUSPENDED) { // ... @todo is this condition needed in Arlo Cancellded is remove?
            continue;
        } else {
            $timestart = time();
            if ($instance->enrolperiod) {
                $timeend = $timestart + $instance->enrolperiod;
            } else {
                $timeend = 0;
            }
            $plugin->enrol_user($instance, $ue->userid, $defaultroleid, $timestart, $timeend);
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
    foreach ($rs as $ue) {
        // Record been flagged then skip.
        if ($ue->flag) {
            continue;
        }
        if (!isset($instances[$ue->enrolid])) {
            $instances[$ue->enrolid] = $DB->get_record('enrol', array('id' => $ue->enrolid));
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
    foreach ($rs as $ue) {
        if (!isset($instances[$ue->enrolid])) {
            $instances[$ue->enrolid] = $DB->get_record('enrol', array('id' => $ue->enrolid));
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

/**
 * Prevent removal of enrol roles.
 *
 * @param int $itemid
 * @param int $groupid
 * @param int $userid
 * @return bool
 */
function enrol_arlo_allow_group_member_remove($itemid, $groupid, $userid) {
    return false;
}

/**
 * Deletes all occurences of the old instance
 * troughout the arlo tables if FQDN setting is changed.
 *
 * @param String $oldinstance
 * @param String $newinstance
 */
function enrol_arlo_change_platform($oldinstance, $newinstance) {
    global $DB, $CFG;

    require_once($CFG->dirroot.'/enrol/arlo/lib.php');
    // Nothing changed.
    if ($oldinstance  === $newinstance) {
        return;
    }
    $rs = $DB->get_recordset('enrol', array('enrol' => 'arlo'));
    $plugin = new enrol_arlo_plugin();
    foreach ($rs as $instance) {
        $plugin->delete_instance($instance);
    }
    $rs->close();
    role_unassign_all(array('component' => 'enrol_arlo'));
    // Clear any create password flags.
    $DB->delete_records('user_preferences', array('name' => 'enrol_arlo_createpassword'));
    // Clear out tables.
    $DB->delete_records('enrol_arlo_applicationlog');
    $DB->delete_records('enrol_arlo_contact', array('platform' => $oldinstance));
    $DB->delete_records('enrol_arlo_emaillog');
    $DB->delete_records('enrol_arlo_event', array('platform' => $oldinstance));
    $DB->delete_records('enrol_arlo_instance', array('platform' => $oldinstance));
    $DB->delete_records('enrol_arlo_onlineactivity', array('platform' => $oldinstance));
    $DB->delete_records('enrol_arlo_registration', array('platform' => $oldinstance));
    $DB->delete_records('enrol_arlo_requestlog', array('platform' => $oldinstance));
    $DB->delete_records('enrol_arlo_schedule', array('platform' => $oldinstance));
    $DB->delete_records('enrol_arlo_template', array('platform' => $oldinstance));
    $DB->delete_records('enrol_arlo_templatelink');
    // Finally purge all caches.
    purge_all_caches();
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
function enrol_arlo_process_template_change($sourcetemplateguid) {
    global $CFG, $DB;


    $templatelink = $DB->get_record('enrol_arlo_templatelink', array('templateguid' => $sourcetemplateguid));
    $course = $DB->get_record('course', array('id' => $templatelink->courseid));

    // use template guid to get the course associated

    // Use course ID to check what enrolment methods currently exist

    // If a enrolment method does not exist make it.    Could check from both events and onlince activities
    require_once($CFG->dirroot . '/group/lib.php');


    if (!$course) {
        return false;
    }

    $currentinstances = array();
    $templateassociations = array();

    $arloinstance = get_config('enrol_arlo', 'platform');
    $plugin = enrol_get_plugin('arlo');

    $student = get_archetype_roles('student');
    $student = reset($student);
    $defaultroleid = $plugin->get_config('roleid', $student->id);

    // Get full template information.
    $template = $DB->get_record('enrol_arlo_template', array('sourceguid' => $templatelink->templateguid), '*', IGNORE_MULTIPLE);
    if ($template->sourcestatus == 'Cancelled') {
        return;
    }

    // Get all event associated with the template.
    $events = $DB->get_records('enrol_arlo_event', array('sourcetemplateguid' => $templatelink->templateguid));
    foreach ($events as $event) {
        $event->type = \enrol_arlo_plugin::ARLO_TYPE_EVENT;
        $templateassociations[$event->sourceguid] = $event;
    }
    // Get all online activities associated with the template.
    //template guid to match
    $onlineactivities = $DB->get_records('enrol_arlo_onlineactivity', array('sourcetemplateguid' => $templatelink->templateguid));
    foreach ($onlineactivities as $onlineactivity) {
        $onlineactivity->type = \enrol_arlo_plugin::ARLO_TYPE_ONLINEACTIVITY;
        $templateassociations[$onlineactivity->sourceguid] = $onlineactivity;
    }

    // Get current Arlo enrolment instances. Will use to check against later on.
    $enrolinstances = $DB->get_records('enrol', array('enrol' => 'arlo', 'courseid' => $course->id));
    foreach ($enrolinstances as $enrolinstance) {
        $currentinstances[$enrolinstance->customchar3] = $enrolinstance;
    }

    // Array merge to get count.
    $x = array_merge($events, $onlineactivities);

    if (count($x) === count($enrolinstances)) {
        foreach ($x as $key => $arloitem) {
            $sql = 'SELECT enrolid FROM {enrol_arlo_instance} WHERE sourceid = :sourceid AND type = :type';
            $query = $DB->get_record_sql($sql, array('sourceid' => $arloitem->sourceid, 'type' => $arloitem->type));
            //$plugin = new \enrol_arlo_plugin();
            //$plugin->update_instance($enrolinstances[$query->enrolid], $arloitem);
            // Change the name.
        }
    } else {
        foreach ($x as $key => $arloitem) {
            $sql = 'SELECT enrolid FROM {enrol_arlo_instance} WHERE sourceid = :sourceid AND type = :type';
            $query = $DB->get_record_sql($sql, array('sourceid' => $arloitem->sourceid, 'type' => $arloitem->type));
            if (empty($query)) {
                $plugin = new \enrol_arlo_plugin();

                $newinstance = $plugin->get_instance_defaults();
                $newinstance['status'] = ENROL_INSTANCE_ENABLED;
                if ($arloitem->type === 'event') {
                    $newinstance['arlotype'] = \enrol_arlo_plugin::ARLO_TYPE_EVENT;
                    $newinstance['arloevent'] = $arloitem->sourceguid;
                } else {
                    $newinstance['arlotype'] = \enrol_arlo_plugin::ARLO_TYPE_ONLINEACTIVITY;
                    $newinstance['arloonlineactivity'] = $arloitem->sourceguid;
                }
                $plugin->add_instance($course, $newinstance, true);
            }
        }
    }
}