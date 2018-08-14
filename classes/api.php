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
use core_plugin_manager;
use enrol_arlo\local\persistent\contact_merge_request;
use enrol_arlo_plugin;
use enrol_arlo\local\config\arlo_plugin_config;
use Exception;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use stdClass;
use enrol_arlo\Arlo\AuthAPI\XmlDeserializer;
use moodle_exception;

class api {
    public static function get_enrolment_plugin() {
        static $enrolmentplugin;
        if (is_null($enrolmentplugin)) {
            $enrolmentplugin = new enrol_arlo_plugin();
        }
        return $enrolmentplugin;
    }

    public static function get_http_client() {
        $pluginconfig = new arlo_plugin_config();
        $config = [
            'auth' => [
                $pluginconfig->get('apiusername'),
                $pluginconfig->get('apipassword'),
            ],
            'decode_content' => 'gzip',
            'connect_timeout' => 30,
            'headers' => [
                'User-Agent' => static::get_user_agent(),
                'X-Plugin-Version' => static::get_plugin_release(),
            ]
        ];
        return new \GuzzleHttp\Client($config);
    }

    public static function get_user_agent() {
        global $CFG;
        return 'Moodle/' . moodle_major_version() . ';' . $CFG->wwwroot;
    }

    public static function get_plugin_release() {
        $pluginmanager = core_plugin_manager::instance();
        $information = $pluginmanager->get_plugin_info('enrol_arlo');
        return $information->release;
    }

    public static function parse_response($response) {
        $statuscode = $response->getStatusCode();
        if (200 != $statuscode) {
            throw new moodle_exception('HTTP: ' . $statuscode);
        }
        $contenttype = $response->getHeaderLine('content-type');
        if (strpos($contenttype, 'application/xml') === false) {
            throw new moodle_exception('HTTP: 415');

        }
        $deserializer = new XmlDeserializer('\enrol_arlo\Arlo\AuthAPI\Resource\\');
        $stream = $response->getBody();
        $contents = $stream->getContents();
        if ($stream->eof()) {
            $stream->rewind();
        }
        return $deserializer->deserialize($contents);
    }

    public static function request_collection($client, $request) {
        $response = $client->send($request);
        return static::parse_response($response);
    }

    public static function syncronize_contact_merge_requests() {
        global $DB;
        $pluginconfig = new arlo_plugin_config();
        try {
            $uri = new RequestUri();
            $uri->setHost($pluginconfig->get('platform'));
            $uri->setResourcePath('contactmergerequests/');
            $uri->addExpand('ContactMergeRequest');
            $uri->setOrderBy('CreatedDateTime ASC');
            $request = new Request('GET', (string) $uri);
            $collection = static::request_collection(static::get_http_client(), $request);
            if ($collection->count() > 0) {
                foreach ($collection as $resource) {
                    $sourceid               = $resource->RequestID;
                    $sourcecontactid        = $resource->SourceContactInfo->ContactID;
                    $sourcecontactguid      = $resource->SourceContactInfo->UniqueIdentifier;
                    $destinationcontactid   = $resource->DestinationContactInfo->ContactID;
                    $destinationcontactguid = $resource->DestinationContactInfo->UniqueIdentifier;
                    $sourcecreated          = $resource->CreatedDateTime;
                    try {
                        $contactmergerequest = new contact_merge_request();
                        $contactmergerequest->from_record_property('sourceid', $sourceid);
                        $contactmergerequest->set('platform', $pluginconfig->get('platform'));
                        $contactmergerequest->set('sourcecontactid', $sourcecontactid);
                        $contactmergerequest->set('sourcecontactguid', $sourcecontactguid);
                        $contactmergerequest->set('destinationcontactid', $destinationcontactid);
                        $contactmergerequest->set('destinationcontactguid', $destinationcontactguid);
                        $contactmergerequest->set('sourcecreated', $sourcecreated);
                        $contactmergerequest->save();
                    } catch (moodle_exception $exception) {
                        // TODO what can we handle in loop and what has to be passed to outer?
                        throw $exception;
                    }
                }
            }
        } catch (moodle_exception $exception) {
            // TODO handle and log.
        }
    }
}
