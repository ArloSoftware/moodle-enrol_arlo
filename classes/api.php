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
use enrol_arlo\local\job\job;
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

    public static function get_time_norequests_after(persistent $persistent) {
        return 0;
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

    public static function run_scheduled_jobs($time = null, progress_trace $trace = null) {
        if (is_null($time)) {
            $time = time();
        }
        if (is_null($trace)) {
            $trace = new null_progress_trace();
        }
        foreach (job_factory::get_scheduled_jobs() as $scheduledjob) {
            /** @var  $scheduledjob job */
            $status = $scheduledjob->run();
            if ($scheduledjob->has_errors()) {
                print_object($scheduledjob->get_errors());
                die;
            }
        }
    }


}
