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
 * API Class
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/arlo/lib.php');
require_once($CFG->dirroot . '/enrol/arlo/locallib.php');

use enrol_arlo\local\administrator_notification;
use enrol_arlo\local\factory\job_factory;
use enrol_arlo\local\job\job;
use enrol_arlo\local\persistent\job_persistent;
use enrol_arlo_plugin;
use moodle_exception;
use progress_trace;
use null_progress_trace;
use UnexpectedValueException;

/**
 * API Class
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {

    /** @var int MAXIMUM_ERROR_COUNT */
    const MAXIMUM_ERROR_COUNT = 20;

    /**
     * Check if Arlo API can be called based on previous responses. Sets a wait period if
     * reaches maximum error count, resets after certain time period.
     *
     * @param progress_trace|null $trace
     * @return bool
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public static function api_callable(progress_trace $trace = null) {
        if (is_null($trace)) {
            $trace = new null_progress_trace();
        }
        $pluginconfig = static::get_enrolment_plugin()->get_plugin_config();
        if (empty($pluginconfig->get('platform')) |
            empty($pluginconfig->get('apiusername')) |
            empty($pluginconfig->get('apipassword'))) {
            administrator_notification::send_invalid_credentials_message();
            $trace->output('Arlo API not callable');
            return false;
        }
        $apierrorcounter = $pluginconfig->get('apierrorcounter');
        if ($apierrorcounter >= self::MAXIMUM_ERROR_COUNT) {
            $apistatus = $pluginconfig->get('apistatus');
            $apitimelastrequest = $pluginconfig->get('apitimelastrequest');
            $apierrorcountresetdelay = $pluginconfig->get('apierrorcountresetdelay');
            if (time() < ($apitimelastrequest + $apierrorcountresetdelay)) {
                if ($apistatus == 401 || $apistatus == 403) {
                    administrator_notification::send_invalid_credentials_message();
                }
                $trace->output('Arlo API not callable');
                return false;
            } else {
                $pluginconfig->set('apistatus', -1);
                $pluginconfig->set('apierrorcounter', 0);
            }
        }
        $trace->output('Arlo API ok to be called');
        return true;
    }

    /**
     * Get an instance on Arlo enrolment plugin.
     *
     * @return enrol_arlo_plugin
     */
    public static function get_enrolment_plugin() {
        static $enrolmentplugin;
        if (is_null($enrolmentplugin)) {
            $enrolmentplugin = new enrol_arlo_plugin();
        }
        return $enrolmentplugin;
    }

    /**
     * Push result information to Arlo.
     *
     * @param int $limit
     * @param progress_trace|null $trace
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws moodle_exception
     */
    public static function run_outcome_jobs($limit = 100, progress_trace $trace = null) {
        global $DB;
        if (!static::api_callable($trace)) {
            return false;
        }
        if (!is_number($limit) || $limit > 100) {
            $limit = 100;
        }
        if (is_null($trace)) {
            $trace = new null_progress_trace();
        }
        $sql = "SELECT *
                  FROM {enrol_arlo_scheduledjob}
                 WHERE area = :area
                   AND type = :type
                   AND disabled <> :disabled";
        $conditions = [
            'area' => 'enrolment',
            'type' => 'outcomes',
            'disabled' => 1,
        ];
        $rs = $DB->get_recordset_sql($sql, $conditions);
        foreach ($rs as $record) {
            try {
                $jobpersistent = new job_persistent(0, $record);
                $scheduledjob = job_factory::create_from_persistent($jobpersistent);
                $trace->output($scheduledjob->get_job_run_identifier());
                $scheduledjob->set_trace($trace);
                if (!$scheduledjob->can_run()) {
                    if ($scheduledjob->has_reasons()) {
                        $trace->output('Cannot run for following reasons:', 1);
                        foreach ($scheduledjob->get_reasons() as $reason) {
                            $trace->output($reason, 2);
                        }
                    }
                } else {
                    $trace->output('Can run job', 1);
                    $status = $scheduledjob->run();
                    if (!$status) {
                        if ($scheduledjob->has_errors()) {
                            $trace->output('Failed with errors.', 1);
                            $jobpersistent->set_errors($scheduledjob->get_errors());
                            $jobpersistent->save();
                        }
                    } else {
                        $trace->output('Completed', 1);
                    }
                }
            } catch (Exception $exception) {
                throw $exception;
            }
        }
        $rs->close();
    }

    /**
     * Main method for running scheduled type jobs that call the Arlo API.
     *
     * @param null $time
     * @param int $limit
     * @param progress_trace|null $trace
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws moodle_exception
     */
    public static function run_scheduled_jobs($area, $type, $time = null, $limit = 1000, progress_trace $trace = null) {
        global $DB;
        if (!static::api_callable($trace)) {
            return false;
        }
        if (is_null($time)) {
            $time = time();
        }
        if (!is_number($limit) || $limit > 1000) {
            $limit = 1000;
        }
        if (is_null($trace)) {
            $trace = new null_progress_trace();
        }
        if (!in_array($area, ['site', 'enrolment'])) {
            throw new UnexpectedValueException('Not a valid area');
        }
        $conditions = [
            'area' => $area,
            'type' => $type,
            'disabled' => 1,
            'timerequestnow' => $time,
            'timenorequest' => $time
        ];
        $timingdelaysql = "AND (timelastrequest + timenextrequestdelay) < :timerequestnow";
        $sql = "SELECT *
                  FROM {enrol_arlo_scheduledjob}
                 WHERE area = :area
                   AND type = :type
                   AND disabled <> 1
                   $timingdelaysql
                   AND ((:timenorequest < (timenorequestsafter + timerequestsafterextension)) OR timelastrequest = 0)";
        $rs = $DB->get_recordset_sql($sql, $conditions, 0, $limit);
        if ($rs->valid()) {
            foreach ($rs as $record) {
                try {
                    $jobpersistent = new job_persistent(0, $record);
                    $scheduledjob = job_factory::create_from_persistent($jobpersistent);
                    $trace->output($scheduledjob->get_job_run_identifier());
                    $scheduledjob->set_trace($trace);
                    if (!$scheduledjob->can_run()) {
                        if ($scheduledjob->has_reasons()) {
                            $trace->output('Cannot run for following reasons:', 1);
                            foreach ($scheduledjob->get_reasons() as $reason) {
                                $trace->output($reason, 2);
                            }
                        }
                    } else {
                        $trace->output('Can run job', 1);
                        $status = $scheduledjob->run();
                        if (!$status) {
                            if ($scheduledjob->has_errors()) {
                                $trace->output('Failed with errors.', 1);
                                $jobpersistent->set_errors($scheduledjob->get_errors());
                                $jobpersistent->save();
                            }
                        } else {
                            $trace->output('Completed', 1);
                        }
                    }
                } catch (moodle_exception $exception) {
                    if ($exception->getMessage() == 'error/locktimeout') {
                        $trace->output('Operation is currently locked by another process.');
                    } else {
                        throw $exception;
                    }
                }
            }
        }
        $rs->close();
    }

    /**
     * Site jobs must be called all the time as information they provided is used for linking enrolment
     * instances to Arlo Events and Online Activities along with Contact Merge Requests.
     *
     * @param progress_trace|null $trace
     * @return bool
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws moodle_exception
     */
    public static function run_site_jobs(progress_trace $trace = null) {
        global $DB;
        if (!static::api_callable($trace)) {
            return false;
        }
        if (is_null($trace)) {
            $trace = new null_progress_trace();
        }
        $records = $DB->get_records('enrol_arlo_scheduledjob', ['area' => 'site']);
        if (!$records) {
            job::register_site_jobs();
        } else {
            foreach ($records as $record) {
                $jobpersistent = new job_persistent(0, $record);
                $sitejob = job_factory::create_from_persistent($jobpersistent);
                $trace->output($sitejob->get_job_run_identifier());
                $sitejob->set_trace($trace);
                if (!$sitejob->can_run()) {
                    if ($sitejob->has_reasons()) {
                        $trace->output('Site job cannot run for following reasons:', 1);
                        foreach ($sitejob->get_reasons() as $reason) {
                            $trace->output($reason, 2);
                        }
                    }
                } else {
                    $status = $sitejob->run();
                    if (!$status) {
                        if ($sitejob->has_errors()) {
                            $trace->output('Failed with errors.', 1);
                            $jobpersistent->set_errors($sitejob->get_errors());
                            $jobpersistent->save();
                        }

                    } else {
                        $trace->output('Completed', 1);
                    }
                }
            }
        }
        return true;
    }

    /**
     * Main method for doing any clean up. Stale logs etc.
     *
     * @param progress_trace|null $trace
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function run_cleanup(progress_trace $trace = null) {
        global $DB;
        if (is_null($trace)) {
            $trace = new null_progress_trace();
        }
        $plugin = static::get_enrolment_plugin();
        $pluginconfig = $plugin->get_plugin_config();
        // Clean up stale request log entries.
        $period = $pluginconfig->get('requestlogcleanup');
        if ($period) {
            $starttime = time();
            $exittime = $starttime + 300; // 5 minutes plus in seconds.
            $days = 86400 * $period; // Days as seconds.
            $loglifetime = time() - $days;
            $maximumdeletelimit = 100000;
            while ($count = $DB->count_records_select(
                'enrol_arlo_requestlog', "timelogged < ?", [$loglifetime]
            )) {
                if ($count > $maximumdeletelimit) {
                    $trace->output("Log entry count {$count} over maximum delete limit.");
                    $items = $DB->get_records_select(
                        'enrol_arlo_requestlog', "timelogged < ?", [$loglifetime], 'timelogged', 'id', 0, 500
                    );
                    if ($items) {
                        $trace->output("Deleting a chunk of " . count($items));
                        $DB->delete_records_list('enrol_arlo_requestlog', 'id', array_keys($items));
                    }
                } else {
                    $trace->output("Attempting to purge {$count} log entries");
                    $DB->delete_records_select('enrol_arlo_requestlog', "timelogged < ?", [$loglifetime]);
                    break;
                }
                // Do not churn on log deletion for too long each run.
                if (time() > $exittime) {
                    $trace->output("Exiting log purge due to exit time passing.");
                    break;
                }
            }
        }

        // Clean up orphaned registrations.
        $sql = "SELECT DISTINCT ear.enrolid
                  FROM {enrol_arlo_registration} ear
             LEFT JOIN {enrol} e ON e.id = ear.enrolid
                 WHERE ear.enrolid <> 0 AND e.id IS NULL";
        foreach ($DB->get_records_sql($sql) as $record) {
            $enrolid = $record->enrolid;
            // Delete associated registrations.
            $DB->delete_records('enrol_arlo_registration', ['enrolid' => $enrolid]);
            // Delete job scheduling information.
            $conditions = [
                'area' => 'enrolment',
                'instanceid' => $enrolid
            ];
            $DB->delete_records(
                'enrol_arlo_scheduledjob',
                $conditions
            );
            // Delete email queue information.
            $DB->delete_records('enrol_arlo_emailqueue', $conditions);
        }
        //Clean up orphaned contacts.
        $time = time() - (86400);
        $select = "userid = 0 AND timecreated < ?";
        $DB->delete_records_select('enrol_arlo_contact', $select, [$time]);
    }

    /**
     * Method to add any missing event or elearning module associations.
     *
     * @throws \dml_exception
     */
    public static function run_associate_all() {
        global $DB;
        $sql = "SELECT eat.*
                  FROM {enrol_arlo_templateassociate} eat
                  JOIN {course} c ON c.id = eat.courseid AND c.visible = 1";
        $records = $DB->get_records_sql($sql);
        foreach ($records as $record) {
            $course = get_course($record->courseid);
            enrol_arlo_associate_all($course, $record->sourcetemplateguid);
        }
    }

}
