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

    // Purge all roles if cohort sync disabled, those can be recreated later here by cron or CLI.
    if (!enrol_is_enabled('arlo')) {
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
    $unenrolaction = $plugin->get_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);

    // Iterate through all not enrolled yet users.
    $onecourse = $courseid ? "AND e.courseid = :courseid" : "";
    $sql = "SELECT u.id AS userid, e.id AS enrolid, ue.status
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
        if ($ue->status == ENROL_USER_SUSPENDED) {
            //$plugin->update_user_enrol($instance, $ue->userid, ENROL_USER_ACTIVE);
            //$trace->output("unsuspending: $ue->userid ==> $instance->courseid via arlo $instance->customint1", 1);
            $trace->output('suspended', 1);
        } else {
            $plugin->enrol_user($instance, $ue->userid);
            $trace->output("enrolling: $ue->userid > $instance->courseid via Arlo", 1);
        }
    }
    $rs->close();


    // Sync groups.
    $affectedusers = groups_sync_with_enrolment('arlo', $courseid);
    foreach ($affectedusers['removed'] as $gm) {
        $trace->output("removing user from group: $gm->userid > $gm->courseid - $gm->groupname", 1);
    }
    foreach ($affectedusers['added'] as $ue) {
        $trace->output("adding user to group: $ue->userid > $ue->courseid - $ue->groupname", 1);
    }

    $trace->output('...user enrolment synchronisation finished.');

    return 0;
}
