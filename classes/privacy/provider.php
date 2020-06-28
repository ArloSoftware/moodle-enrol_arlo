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
 * Privacy provider class for Arlo enrolment plugin.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2019 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\privacy;

defined('MOODLE_INTERNAL') || die();

use context;
use context_course;
use context_user;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\writer;

if (interface_exists('\core_privacy\local\request\core_userlist_provider')) {
    interface userlist_provider extends \core_privacy\local\request\core_userlist_provider{}
} else {
    interface userlist_provider {};
}

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    userlist_provider {

    // Backwards compatibility.
    use \core_privacy\local\legacy_polyfill;

    public static function _get_metadata(collection $collection) {
        $collection->add_database_table(
            'enrol_arlo_contact',
            [
                'userid'        => 'privacy:metadata:enrol_arlo_contact:userid',
                'sourceid'      => 'privacy:metadata:enrol_arlo_contact:sourceid',
                'sourceguid'    => 'privacy:metadata:enrol_arlo_contact:sourceguid',
                'firstname'     => 'privacy:metadata:enrol_arlo_contact:firstname',
                'lastname'      => 'privacy:metadata:enrol_arlo_contact:lastname',
                'email'         => 'privacy:metadata:enrol_arlo_contact:email',
                'codeprimary'   => 'privacy:metadata:enrol_arlo_contact:codeprimary',
                'phonework'     => 'privacy:metadata:enrol_arlo_contact:phonework',
                'phonemobile'   => 'privacy:metadata:enrol_arlo_contact:phonemobile'

            ],
            'privacy:metadata:enrol_arlo_contact'
        );
        $collection->add_database_table(
            'enrol_arlo_emailqueue',
            [
                'area'       => 'privacy:metadata:enrol_arlo_emailqueue:area',
                'instanceid' => 'privacy:metadata:enrol_arlo_emailqueue:instanceid',
                'userid'     => 'privacy:metadata:enrol_arlo_emailqueue:userid',
                'type'       => 'privacy:metadata:enrol_arlo_emailqueue:type',
                'status'     => 'privacy:metadata:enrol_arlo_emailqueue:status',
                'extra'      => 'privacy:metadata:enrol_arlo_emailqueue:extra'

            ],
            'privacy:metadata:enrol_arlo_emailqueue'
        );
        $collection->add_database_table(
            'enrol_arlo_registration',
            [
                'enrolid'           => 'privacy:metadata:enrol_arlo_registration:enrolid',
                'userid'            => 'privacy:metadata:enrol_arlo_registration:userid',
                'sourceid'          => 'privacy:metadata:enrol_arlo_registration:sourceid',
                'sourceguid'        => 'privacy:metadata:enrol_arlo_registration:sourceguid',
                'grade'             => 'privacy:metadata:enrol_arlo_registration:grade',
                'outcome'           => 'privacy:metadata:enrol_arlo_registration:outcome',
                'lastactivity'      => 'privacy:metadata:enrol_arlo_registration:lastactivity',
                'progressstatus'    => 'privacy:metadata:enrol_arlo_registration:progressstatus',
                'progresspercent'   => 'privacy:metadata:enrol_arlo_registration:progresspercent',
                'sourcecontactid'   => 'privacy:metadata:enrol_arlo_registration:sourcecontactid',
                'sourcecontactguid' => 'privacy:metadata:enrol_arlo_registration:sourcecontactguid'
            ],
            'privacy:metadata:enrol_arlo_registration'
        );
        $collection->add_database_table(
            'enrol_arlo_templateassociate',
            [
                'usermodified' => 'privacy:metadata:field:usermodified'
            ]
        );
        $collection->add_subsystem_link('core_group', [], 'privacy:metadata:core_group');
        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function _get_contexts_for_userid($userid){
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {user} u ON u.id = ctx.instanceid AND ctx.contextlevel = :contextuser
                  JOIN {enrol_arlo_contact} eac ON eac.userid = u.id
                 WHERE u.id = :userid";
        $params = [
            'contextuser' => CONTEXT_USER,
            'userid'      => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {enrol} e ON e.courseid = ctx.instanceid AND ctx.contextlevel = :contextcourse
                  JOIN {enrol_arlo_registration} ear ON ear.enrolid = e.id
                  JOIN {enrol_arlo_contact} eac ON eac.userid = ear.userid
                  JOIN {user} u ON u.id = eac.userid
                 WHERE u.id = :userid";
        $params = [
            'contextcourse' => CONTEXT_COURSE,
            'userid'        => $userid,
        ];
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param \core_privacy\local\request\userlist $userlist
     */
    public static function get_users_in_context(\core_privacy\local\request\userlist $userlist) {
        $context = $userlist->get_context();
        if ($context instanceof context_user) {
            $sql = "SELECT u.id
                      FROM {enrol_arlo_contact} eac
                      JOIN {user} u ON u.id = eac.userid
                     WHERE u.id = :userid";
            $params = ['userid' => $context->instanceid];
            $userlist->add_from_sql('id', $sql, $params);
        }
        if ($context instanceof context_course) {
            $sql = "SELECT u.id
                      FROM {enrol_arlo_registration} ear
                      JOIN {enrol_arlo_contact} eac ON eac.userid = ear.userid
                      JOIN {enrol} e ON e.id = ear.enrolid
                      JOIN {user} u ON u.id = ear.userid
                     WHERE e.courseid = :courseid";
            $params = ['courseid' => $context->instanceid];
            $userlist->add_from_sql('id', $sql, $params);
        }
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function _export_user_data(approved_contextlist $contextlist) {
        global $DB;
        if (empty($contextlist->count())) {
            return;
        }
        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            list($contextsql, $contextparams) = $DB->get_in_or_equal($context->id, SQL_PARAMS_NAMED);
            // Handle exporting of course context user data.
            if ($context instanceof context_course) {
                $enrolmentinstances = [];
                // Registration information.
                $registrationsubcontext = \core_enrol\privacy\provider::get_subcontext(
                    [
                        get_string('pluginname', 'enrol_arlo'),
                        get_string('metadata:enrol_arlo_registration', 'enrol_arlo')
                    ]
                );
                $sql = "SELECT ear.*
                          FROM {context} ctx
                          JOIN {enrol} e ON e.courseid = ctx.instanceid AND ctx.contextlevel = :contextcourse
                          JOIN {enrol_arlo_registration} ear ON ear.enrolid = e.id
                          JOIN {enrol_arlo_contact} eac ON eac.userid = ear.userid
                          JOIN {user} u ON u.id = eac.userid
                         WHERE ctx.id {$contextsql} AND u.id = :userid";
                $params = [
                    'contextcourse' => CONTEXT_COURSE,
                    'userid'        => $user->id
                ];
                $params += $contextparams;
                $rs = $DB->get_recordset_sql($sql, $params);
                foreach ($rs as $record) {
                    $enrolmentinstances[] = $record->enrolid;
                    $registration = (object) [
                        'enrolid'           => $record->enrolid,
                        'userid'            => $record->userid,
                        'sourceid'          => $record->sourceid,
                        'sourceguid'        => $record->sourceguid,
                        'grade'             => $record->grade,
                        'outcome'           => $record->outcome,
                        'lastactivity'      => $record->lastactivity,
                        'progressstatus'    => $record->progressstatus,
                        'progresspercent'   => $record->progresspercent,
                        'sourcecontactid'   => $record->sourcecontactid,
                        'sourcecontactguid' => $record->sourcecontactguid
                    ];
                    writer::with_context($context)->export_data($registrationsubcontext, $registration);
                }
                $rs->close();
                // Email communications at enrolment context.
                $communicationsubcontext = \core_enrol\privacy\provider::get_subcontext(
                    [
                        get_string('pluginname', 'enrol_arlo'),
                        get_string('communications', 'enrol_arlo')
                    ]
                );
                list($insql, $inparams) = $DB->get_in_or_equal($enrolmentinstances, SQL_PARAMS_NAMED);
                $params = ['userid' => $user->id, 'area' => 'enrolment'];
                $params = array_merge($params, $inparams);
                $select = "userid = :userid AND area = :area AND instanceid $insql";
                $rs = $DB->get_recordset_select('enrol_arlo_emailqueue', $select, $params);
                foreach ($rs as $record) {
                    $communication = (object) [
                        'area'          => $record->area,
                        'instanceid'    => $record->instanceid,
                        'userid'        => $record->userid,
                        'type'          => $record->type,
                        'status'        => $record->status,
                        'extra'         => $record->extra];
                    writer::with_context($context)->export_data($communicationsubcontext, $communication);
                }
                $rs->close();
            }
            // Handle exporting of user context user data (site).
            if ($context instanceof context_user) {
                // Contact information in user context.
                $contactsubcontext = \core_enrol\privacy\provider::get_subcontext(
                    [
                        get_string('pluginname', 'enrol_arlo'),
                        get_string('metadata:enrol_arlo_contact', 'enrol_arlo')
                    ]
                );
                // Associated Contact information.
                $sql = "SELECT eac.*
                          FROM {context} ctx
                          JOIN {user} u ON u.id = ctx.instanceid AND ctx.contextlevel = :contextuser
                          JOIN {enrol_arlo_contact} eac ON eac.userid = u.id
                         WHERE ctx.id {$contextsql} AND u.id = :userid";
                $params = [
                    'contextuser' => CONTEXT_USER,
                    'userid'      => $user->id
                ];
                $params += $contextparams;
                $rs = $DB->get_recordset_sql($sql, $params);
                foreach ($rs as $record) {
                    $contact = (object) [
                        'userid'        => $record->userid,
                        'sourceid'      => $record->sourceid,
                        'sourceguid'    => $record->sourceguid,
                        'firstname'     => $record->firstname,
                        'lastname'      => $record->lastname,
                        'email'         => $record->email,
                        'codeprimary'   => $record->codeprimary,
                        'phonework'     => $record->phonework,
                        'phonemobile'   => $record->phonemobile
                    ];
                    writer::with_context($context)->export_data($contactsubcontext, $contact);
                }
                $rs->close();
                // Communications at user context.
                $communicationsubcontext = \core_enrol\privacy\provider::get_subcontext(
                    [
                        get_string('pluginname', 'enrol_arlo'),
                        get_string('communications', 'enrol_arlo')
                    ]
                );
                $rs = $DB->get_recordset('enrol_arlo_emailqueue', ['userid' => $user->id, 'area' => 'site']);
                foreach ($rs as $record) {
                    $communication = (object) [
                        'area'          => $record->area,
                        'instanceid'    => $record->instanceid,
                        'userid'        => $record->userid,
                        'type'          => $record->type,
                        'status'        => $record->status,
                        'extra'         => $record->extra];
                    writer::with_context($context)->export_data($communicationsubcontext, $communication);
                }
                $rs->close();
            }
        }
    }

    /**
     * Delete all user data which matches the specified context.
     *
     * @param context $context
     * @throws \dml_exception
     */
    public static function _delete_data_for_all_users_in_context(context $context) {
        global $CFG, $DB;
        require_once($CFG->libdir . '/enrollib.php');
        if (empty($context)) {
            return;
        }
        if ($context instanceof context_course) {
            $rs = $DB->get_recordset('enrol', ['enrol' => 'arlo', 'courseid' => $context->instanceid]);
            foreach ($rs as $instance) {
                // Disable enrolment instance.
                $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $instance->id]);
                // Delete associated registrations.
                $DB->delete_records('enrol_arlo_registration', ['enrolid' => $instance->id]);
                // Delete email queue information.
                $DB->delete_records('enrol_arlo_emailqueue', ['area' => 'enrolment', 'instanceid' => $instance->id]);
            }
            // Delete all the associated groups.
            \core_group\privacy\provider::delete_groups_for_all_users($context, 'enrol_arlo');
        }
        if ($context instanceof context_user) {
            // Delete contact association.
            $DB->delete_records('enrol_arlo_contact', ['userid' => $context->instanceid]);
            // Delete email queue information.
            $DB->delete_records('enrol_arlo_emailqueue', ['area' => 'site', 'userid' => $context->instanceid]);
        }
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function _delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;
        // User deletions are always handled at the user context.
        if (empty($contextlist->count())) {
            return;
        }
        $user = $contextlist->get_user();
        $contexts = $contextlist->get_contexts();
        // Context collectors.
        $systemcontext = [];
        $usercontext = [];
        $coursecontexts = [];
        foreach ($contexts as $context) {
            if ($context->contextlevel == CONTEXT_SYSTEM) {
                $systemcontext[$context->instanceid] = $context;
            }
            if ($context->contextlevel == CONTEXT_USER) {
                $usercontext[$context->instanceid] = $context;
            }
            if ($context->contextlevel == CONTEXT_COURSE) {
                $coursecontexts[$context->instanceid] = $context;
            }
        }
        // Everything must go.
        if (count($systemcontext) == 1 || count($usercontext) == 1) {
            // Delete registrations.
            $DB->delete_records('enrol_arlo_registration', ['userid' => $user->id]);
            // Delete contact association.
            $DB->delete_records('enrol_arlo_contact', ['userid' => $user->id]);
            // Delete communications.
            $DB->delete_records('enrol_arlo_emailqueue', ['userid' => $user->id]);
            // Delete all the associated groups.
            \core_group\privacy\provider::delete_groups_for_user($contextlist, 'enrol_arlo');
        } else if (count($coursecontexts) > 0) {
            $courseids = array_keys($coursecontexts);
            list($sql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
            $enrolids = $DB->get_fieldset_select(
                'enrol',
                'id',
                "enrol = 'arlo' AND courseid $sql",
                $params
            );
            if (!empty($enrolids)) {
                list($sql, $params) = $DB->get_in_or_equal($enrolids, SQL_PARAMS_NAMED);
                $params = array_merge($params, ['userid' => $user->id]);
                // Delete associated registrations.
                $DB->delete_records_select(
                    'enrol_arlo_registration',
                    "enrolid $sql AND userid = :userid",
                    $params
                );
                // Delete associated communications.
                $DB->delete_records_select(
                    'enrol_arlo_emailqueue',
                    "area = 'enrolment' AND instanceid $sql AND userid =:userid",
                    $params
                );
            }
        }
        // Delete all the associated groups.
        \core_group\privacy\provider::delete_groups_for_user($contextlist, 'enrol_arlo');
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;
        // User deletions are always handled at the user context.
        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        $allowedcontextlevels = [
            CONTEXT_SYSTEM, CONTEXT_COURSE
        ];
        if (!in_array($context->contextlevel, $allowedcontextlevels)) {
            return;
        }
        if (empty($userids)) {
            return;
        }
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            // Delete registrations.
            $DB->delete_records_select(
                'enrol_arlo_registration',
                "userid $usersql",
                $userparams
            );
            // Delete contacts.
            $DB->delete_records_select(
                'enrol_arlo_contact',
                "userid $usersql",
                $userparams
            );
            // Delete communications.
            $DB->delete_records_select(
                'enrol_arlo_emailqueue',
                "userid $usersql",
                $userparams
            );
            \core_group\privacy\provider::delete_groups_for_users($userlist, 'enrol_arlo');
        }
        if ($context->contextlevel == CONTEXT_COURSE) {
            $instances = $DB->get_records('enrol', ['enrol' => 'arlo', 'courseid' => $context->instanceid]);
            if (empty($instances)) {
                return;
            }
            $instanceids = array_keys($instances);
            list($usersql, $userparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
            list($enrolsql, $enrolparams) = $DB->get_in_or_equal($instanceids, SQL_PARAMS_NAMED, 'e');
            // Delete registrations in course context.
            $DB->delete_records_select(
                'enrol_arlo_registration',
                "enrolid $enrolsql AND userid $usersql",
                array_merge($userparams, $enrolparams)
            );
            // Delete communications in course context.
            $DB->delete_records_select(
                'enrol_arlo_emailqueue',
                "area = 'enrolment' AND instanceid $enrolsql AND userid $usersql",
                array_merge($userparams, $enrolparams)
            );
            \core_group\privacy\provider::delete_groups_for_all_users($context, 'enrol_arlo');
        }
    }
}
