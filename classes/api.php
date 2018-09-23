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
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/arlo/lib.php');

use enrol_arlo\Arlo\AuthAPI\Enum\EventStatus;
use enrol_arlo\Arlo\AuthAPI\Enum\OnlineActivityStatus;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\local\client;
use enrol_arlo\local\factory\job_factory;
use enrol_arlo\local\job\job;
use enrol_arlo\local\persistent\job_persistent;
use enrol_arlo\local\persistent\contact_merge_request_persistent;
use enrol_arlo\local\persistent\event_persistent;
use enrol_arlo\local\persistent\online_activity_persistent;
use enrol_arlo_plugin;
use enrol_arlo\local\config\arlo_plugin_config;
use Exception;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use stdClass;
use enrol_arlo\Arlo\AuthAPI\XmlDeserializer;
use moodle_exception;
use moodle_url;
use progress_trace;
use null_progress_trace;
use core_date;
use core_user;
use DateTime;

class api {

    /** @var int MAXIMUM_ERROR_COUNT */
    const MAXIMUM_ERROR_COUNT = 20;

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
     * Main method for running scheduled job that call the Arlo API.
     *
     * @param null $time
     * @param int $limit
     * @param progress_trace|null $trace
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws moodle_exception
     */
    public static function run_scheduled_jobs($time = null, $limit = 1000, progress_trace $trace = null) {
        global $DB;
        if (is_null($time)) {
            $time = time();
        }
        if (!is_number($limit) || $limit > 1000) {
            $limit = 1000;
        }
        if (is_null($trace)) {
            $trace = new null_progress_trace();
        }
        $pluginconfig = static::get_enrolment_plugin()->get_plugin_config();
        $apierrorcounter = $pluginconfig->get('apierrorcounter');
        if ($apierrorcounter >= self::MAXIMUM_ERROR_COUNT) {
            $apistatus = $pluginconfig->get('apistatus');
            $apitimelastrequest = $pluginconfig->get('apitimelastrequest');
            $apierrorcountresetdelay = $pluginconfig->get('apierrorcountresetdelay');
            if (time() < ($apitimelastrequest + $apierrorcountresetdelay)) {
                $url = new moodle_url('/admin/settings.php', ['section' => 'enrolsettingsarlo']);
                $noreplyuser = core_user::get_noreply_user();
                $subject = get_string(
                    "httpstatuserror_{$apistatus}_subject",
                    'enrol_arlo',
                    ['url' => $url->out()]
                );
                $messagetext = get_string(
                    "httpstatuserror_{$apistatus}_fullmessage",
                    'enrol_arlo',
                    ['url' => $url->out()]
                );
                foreach (get_admins() as $admin) {
                    email_to_user($admin, $noreplyuser, $subject, $messagetext);
                }
                return false;
            } else {
                $pluginconfig->set('apistatus', -1);
                $pluginconfig->set('apierrorcounter', 0);
            }
        }
        $conditions = [
            'disabled' => 1,
            'timerequestnow' => $time,
            'timenorequest' => $time
        ];
        $timingdelaysql = "";
        if (!$pluginconfig->get('throttlerequests')) {
            $timingdelaysql = "AND (timelastrequest + timenextrequestdelay) < :timerequestnow";
        }
        $sql = "SELECT *
                  FROM {enrol_arlo_scheduledjob}
                 WHERE disabled <> 1
                   $timingdelaysql
                   AND (:timenorequest < (timenorequestsafter + timerequestsafterextension) OR timenorequestsafter = 0)";
        $rs = $DB->get_recordset_sql($sql, $conditions, 0, $limit);
        if ($rs->valid()) {
            foreach ($rs as $record) {
                try {
                    $jobpersistent = new job_persistent(0, $record);
                    $scheduledjob = job_factory::create_from_persistent($jobpersistent);
                    $trace->output($scheduledjob->get_job_run_identifier());
                    $scheduledjob->set_trace($trace);
                    $status = $scheduledjob->run();
                    if (!$status) {
                        if ($scheduledjob->has_errors()) {
                            $trace->output('Failed with errors.', 1);
                            $jobpersistent->set_errors($scheduledjob->get_errors());
                            $jobpersistent->save();
                        }
                        if ($scheduledjob->has_reasons()) {
                            $trace->output('Failed with reasons.', 1);
                            foreach ($scheduledjob->get_reasons() as $reason) {
                                $trace->output($reason, 2);
                            }
                        }
                    } else {
                        $trace->output('Completed', 1);
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
     * Main method for doing any clean up. Stale logs etc.
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public static function run_cleanup() {
        global $DB;
        $pluginconfig = static::get_enrolment_plugin()->get_plugin_config();
        $period = $pluginconfig->get('requestlogcleanup');
        if ($period) {
            $time = time() - (86400 * $period);
            $DB->delete_records_select('enrol_arlo_requestlog', "timelogged < ?", [$time]);
        }
    }

}
