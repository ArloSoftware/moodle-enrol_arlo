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
 * Client used for communicating with Arlo Auth API.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local;

defined('MOODLE_INTERNAL') || die();

use enrol_arlo\api;
use enrol_arlo\local\persistent\request_log_persistent;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

class client {

    /** @var $httpclient \GuzzleHttp\Client */
    protected $httpclient;

    /**
     * Construct a guzzle client setup with basic authentication and appropriate
     * options and headers set.
     *
     * @param array $headers
     * @return static
     * @throws \coding_exception
     */
    public static function get_instance($headers = []) {
        $pluginconfig = api::get_enrolment_plugin()->get_plugin_config();
        $config = [
            'auth' => [
                $pluginconfig->get('apiusername'),
                $pluginconfig->get('apipassword'),
            ],
            'decode_content' => 'gzip',
            'connect_timeout' => 30,
            'headers' => [
                'User-Agent' => static::get_user_agent(),
                'X-Plugin-Version' => api::get_enrolment_plugin()->get_plugin_release()
            ]
        ];
        $config['headers'] = array_merge($config['headers'], $headers);
        $client = new static();
        $client->httpclient = new \GuzzleHttp\Client($config);
        return $client;
    }

    /**
     * Custom user agent string to identify the client.
     *
     * @return string
     */
    public static function get_user_agent() {
        global $CFG;
        return 'Moodle/' . moodle_major_version() . ';' . $CFG->wwwroot;
    }

    /**
     * Send PSR-7 Request to Arlo API. Count errors of same type.
     *
     * @param Request $request
     * @return Response|mixed|\Psr\Http\Message\ResponseInterface
     * @throws Exception
     * @throws \coding_exception
     */
    public function send_request(Request $request) {
        try {
            $time = time();
            $pluginconfig = api::get_enrolment_plugin()->get_plugin_config();
            $apierrormessage = $pluginconfig->get('apierrormessage');
            $apierrorcounter = $pluginconfig->get('apierrorcounter');
            $pluginconfig->set('apierrormessage', 0);
            $pluginconfig->set('apierrorcounter', 0);
            $pluginconfig->set('apitimelastrequest', $time);
            /** @var $client \GuzzleHttp\Client */
            $response = $this->httpclient->send($request);
            $statuscode = $response->getStatusCode();
            return $response;
        } catch (Exception $exception) {
            if ($exception instanceof ClientException || $exception instanceof ServerException) {
                $statuscode = $exception->getResponse()->getStatusCode();
                $trace = $exception->getTraceAsString();
                if ($apierrormessage == $statuscode) {
                    $pluginconfig->set('apierrormessage', $statuscode);
                    $pluginconfig->set('apierrorcounter', ++$apierrorcounter);
                } else {
                    $pluginconfig->set('apierrormessage', $statuscode);
                    $pluginconfig->set('apierrorcounter', 1);
                }
                return $exception->getResponse(); // TODO maybe return false?
            }
            throw $exception;
        } finally {
            $pluginconfig->set('apistatus', $statuscode);
            // Log request.
            $requestlog = new request_log_persistent();
            $requestlog->set('timelogged', $time);
            $requestlog->set('uri', (string) $request->getUri());
            $requestlog->set('status', $statuscode);
            if (!empty($trace)) {
                $requestlog->set('extra', $trace);
            }
            $requestlog->save();
        }
    }

}
