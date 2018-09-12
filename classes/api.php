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
use progress_trace;
use null_progress_trace;
use core_date;
use DateTime;

class api {

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

    public static function parse_response($response) {
        $statuscode = $response->getStatusCode();
        if (200 != $statuscode) {
            throw new moodle_exception('HTTP: ' . $statuscode);
        }
        $contenttype = $response->getHeaderLine('content-type');
        if (strpos($contenttype, 'application/xml') === false) {
            $code = 'httpstatus:415';
            $debuginfo = format_backtrace(debug_backtrace());
            throw new moodle_exception($code, 'enrol_arlo', '', null, $debuginfo);
        }
        $deserializer = new XmlDeserializer('\enrol_arlo\Arlo\AuthAPI\Resource\\');
        $stream = $response->getBody();
        $contents = $stream->getContents();
        if ($stream->eof()) {
            $stream->rewind();
        }
        return $deserializer->deserialize($contents);
    }

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

        $trace->output($pluginconfig->get('apistatus'));
        $trace->output($pluginconfig->get('apierrormessage'));
        $trace->output($pluginconfig->get('apierrorcounter'));
        $trace->output($pluginconfig->get('apitimelastrequest'));
        $conditions = [
            'disabled' => 1,
            'timerequestnow' => $time,
            'timenorequest' => $time
        ];
        $sql = "SELECT *
                  FROM {enrol_arlo_scheduledjob}
                 WHERE disabled <> 1
                   AND (timelastrequest + timenextrequestdelay) < :timerequestnow
                   AND (:timenorequest < (timenorequestsafter + timerequestsafterextension) OR timenorequestsafter = 0)";
        $rs = $DB->get_recordset_sql($sql, $conditions, 0, $limit);
        foreach ($rs as $record) {
            $jobpersistent = new job_persistent(0, $record);
            $scheduledjob = job_factory::create_from_persistent($jobpersistent);
            $trace->output($scheduledjob->get_type());
            $status = $scheduledjob->run();
            if (!$status) {
                $jobpersistent->set_errors($scheduledjob->get_errors());
                $jobpersistent->save();
                die;
            }
        }
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
