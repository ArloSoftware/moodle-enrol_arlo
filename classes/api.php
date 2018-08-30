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

use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\local\client;
use enrol_arlo\local\factory\job_factory;
use enrol_arlo\local\persistent\contact_merge_request_persistent;
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

class api {

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
            throw new moodle_exception($code, 'enrol_arlo');

        }
        $deserializer = new XmlDeserializer('\enrol_arlo\Arlo\AuthAPI\Resource\\');
        $stream = $response->getBody();
        $contents = $stream->getContents();
        if ($stream->eof()) {
            $stream->rewind();
        }
        return $deserializer->deserialize($contents);
    }

    public static function send_request($client, $request) {
        try {
            /** @var $client \GuzzleHttp\Client */
            $response = $client->send($request);
            return $response;
        } catch (Exception $exception) {
            // Re throw as a new moodle exception.
            $code = 'httpstatus:' . $exception->getCode();
            throw new moodle_exception($code, 'enrol_arlo', '', null, $exception->getTraceAsString());
        }
    }

    public static function run_scheduled_jobs($time = null, progress_trace $trace = null) {
        if (is_null($time)) {
            $time = time();
        }
        if (is_null($trace)) {
            $trace = new null_progress_trace();
        }
        foreach (job_factory::get_scheduled_jobs() as $scheduledjob) {
            $status = $scheduledjob->run();
            if (!$status) {
                var_dump($scheduledjob->get_errors());
            }

        }
    }

    /**
     * Register site level syncronisation jobs.
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws invalid_persistent_exception
     * @throws local\persistent\coding_exception
     */
    public static function register_site_level_scheduled_jobs() {
        global $SITE;

        // Register Event Templates job.
        $job = new local\persistent\job_persistent();
        $job->from_record_property('type', 'site/event_templates');
        $job->set('instanceid', $SITE->id);
        $job->set('collection', 'EventTemplates');
        $job->set('endpoint', 'eventtemplates/');
        if ($job->get('id') <= 0) {
            $job->create();
        }

        // Register Events job.
        $job = new local\persistent\job_persistent();
        $job->from_record_property('type', 'site/events');
        $job->set('instanceid', $SITE->id);
        $job->set('collection', 'Events');
        $job->set('endpoint', 'events/');
        if ($job->get('id') <= 0) {
            $job->create();
        }

        // Register Online Activities job.
        $job = new local\persistent\job_persistent();
        $job->from_record_property('type', 'site/online_activities');
        $job->set('instanceid', $SITE->id);
        $job->set('collection', 'OnlineActivities');
        $job->set('endpoint', 'onlineactivities/');
        if ($job->get('id') <= 0) {
            $job->create();
        }

        // Register Contact Merge Requests job.
        $job = new local\persistent\job_persistent();
        $job->from_record_property('type', 'site/contact_merge_requests');
        $job->set('instanceid', $SITE->id);
        $job->set('collection', 'ContactMergeRequests');
        $job->set('endpoint', 'contactmergerequests/');
        if ($job->get('id') <= 0) {
            $job->create();
        }

    }
}
