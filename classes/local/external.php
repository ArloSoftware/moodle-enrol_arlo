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

namespace enrol_arlo\local;

use coding_exception;
use DOMDocument;
use DOMElement;
use enrol_arlo\local\enum\arlo_type;
use enrol_arlo\local\persistent\event_persistent;
use enrol_arlo\local\persistent\online_activity_persistent;
use enrol_arlo\local\persistent\registration_persistent;
use enrol_arlo\local\persistent\request_log_persistent;
use Exception;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\Arlo\AuthAPI\Resource\Registration as RegistrationResource;
use enrol_arlo\local\config\arlo_plugin_config;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use moodle_url;
use ReflectionClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Help class to simplify construction and calls to Arlo API.
 *
 * @package     enrol_arlo
 * @copyright   2019 Troy Williams <troy.williams@learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external {

    /**
     * Get a single registration from Arlo and deserialize to resource object.
     *
     * @param int $id
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \moodle_exception
     * @throws coding_exception
     */
    public static function get_registration_resource(int $id) {
        if ($id <= 0) {
            throw new coding_exception('UnexpectedValueException');
        }
        $pluginconfig = new arlo_plugin_config();
        $client = client::get_instance();
        $uri = new RequestUri();
        $uri->setHost($pluginconfig->get('platform'));
        $uri->setResourcePath("registrations/{$id}/");
        $request = new Request('GET', $uri->output(true));
        $response = $client->send_request($request);
        $resource = response_processor::process($response);
        return $resource;
    }

    /**
     * Get a single resource from specific Arlo collection and deserialize to resource object.
     *
     * @param string $collection
     * @param int $id
     * @return mixed
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws coding_exception
     */
    public static function get_resource(string $collection, int $id) {
        if ($id <= 0) {
            throw new coding_exception('UnexpectedValueException');
        }
        $pluginconfig = new arlo_plugin_config();
        $client = client::get_instance();
        $uri = new RequestUri();
        $uri->setHost($pluginconfig->get('platform'));
        $uri->setResourcePath("{$collection}/{$id}/");
        $request = new Request('GET', $uri->output(true));
        try {
            $response = $client->send($request);
            $statuscode = $response->getStatusCode();
            $resource = response_processor::process($response);
            return $resource;
        } catch (Exception $ex) {
            $statuscode = $ex->getCode();
            $message = $ex->getMessage();
            debugging($message, DEBUG_DEVELOPER);
        } finally {
            $pluginconfig = new arlo_plugin_config();
            if (isset($statuscode)) {
                $pluginconfig->set('apistatus', $statuscode);
            }
            // Log request.
            $requestlog = new request_log_persistent();
            $requestlog->set('timelogged', time());
            $requestlog->set('uri', $uri->output(true));
            if (isset($statuscode)) {
                $requestlog->set('status', $statuscode);
            }
            if (isset($message)) {
                $requestlog->set('extra', $message);
            }
            $requestlog->save();
        }
    }

    /**
     * Update the ContentUri on Arlo for a given Event/OnlineActivity. The course homepage URL in
     * which the associated enrolment instance resides is used.
     *
     * @param $type
     * @param string $guid
     * @param moodle_url $courseurl
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \ReflectionException
     * @throws coding_exception
     */
    public static function update_contenturi($type, string $guid, moodle_url $courseurl) {
        $pluginconfig = new arlo_plugin_config();
        if (!$pluginconfig->get('allowportalintegration')) {
            debugging('Arlo portal integration disabled by configuration', DEBUG_DEVELOPER);
            return;
        }
        if (!in_array($type, [arlo_type::EVENT, arlo_type::ONLINEACTIVITY])) {
            throw new InvalidArgumentException('Only arlo_type::EVENT, arlo_type::ONLINEACTIVITY are valid');
        }
        $contenturi = $courseurl->out(false);
        $collection = null;
        $id = null;
        $resource = null;
        $data = [];
        if ($type == arlo_type::EVENT) {
            $eventpersistent = event_persistent::get_record(['sourceguid' => $guid]);
            $collection = 'events';
            $id = $eventpersistent->get('id');
            $resource = static::get_resource($collection, $id);
        }
        if ($type == arlo_type::ONLINEACTIVITY) {
            $onlineactivitypersistent = online_activity_persistent::get_record(['sourceguid' => $guid]);
            $collection = 'onlineactivities';
            $id = $onlineactivitypersistent->get('id');
            $resource = static::get_resource($collection, $id);
        }
        if ($resource->ContentUri != $contenturi) {
            $data['ContentUri'] = $contenturi;
            static::patch_resource($collection, $id, $resource, $data);
        }
    }

    /**
     * Patch a specific resource against an array of Entity key/values.
     *
     * @param string $collection
     * @param int $id
     * @param $resource
     * @param array $data
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \ReflectionException
     * @throws coding_exception
     */
    public static function patch_resource(string $collection, int $id, $resource, array $data) {
        if ($id <= 0) {
            throw new coding_exception('UnexpectedValueException');
        }
        if (empty($data)) {
            return;
        }
        $reflection = new ReflectionClass($resource);
        $resourcename = ucfirst($reflection->getShortName());
        $pluginconfig = new arlo_plugin_config();
        $client = client::get_instance();
        $uri = new RequestUri();
        $uri->setHost($pluginconfig->get('platform'));
        $uri->setResourcePath("{$collection}/{$id}/");
        $dom = new DOMDocument('1.0', 'utf-8');
        $root = $dom->appendChild(new DOMElement('diff'));
        foreach ($data as $key => $value) {
            if (!is_null($value)) {
                if (!is_null($resource->{$key})) {
                    $element = $dom->createElement('replace', $value);
                    $element->setAttribute('sel', "{$resourcename}/{$key}/text()[1]");
                    $root->appendChild($element);
                } else {
                    $add = $dom->createElement('add');
                    $add->setAttribute('sel', $resourcename);
                    $element = $dom->createElement($key, $value);
                    $add->appendChild($element);
                    $root->appendChild($add);
                }
            }
        }
        $xmlbody = $dom->saveXML();
        $request = new Request(
            'PATCH',
            $uri->output(true),
            ['Content-type' => 'application/xml; charset=utf-8'],
            $xmlbody
        );
        try {
            $response = $client->send($request);
            $statuscode = $response->getStatusCode();
        } catch (Exception $ex) {
            $statuscode = $ex->getCode();
            $message = $ex->getMessage();
            debugging($message, DEBUG_DEVELOPER);
        } finally {
            $pluginconfig = new arlo_plugin_config();
            if (isset($statuscode)) {
                $pluginconfig->set('apistatus', $statuscode);
            }
            // Log request.
            $requestlog = new request_log_persistent();
            $requestlog->set('timelogged', time());
            $requestlog->set('uri', $uri->output(true));
            if (isset($statuscode)) {
                $requestlog->set('status', $statuscode);
            }
            if (isset($message)) {
                $requestlog->set('extra', $message);
            }
            $requestlog->save();
        }
    }

    /**
     * Patch an Arlo Registration with new result/progress data from Moodle.
     *
     * @param RegistrationResource $registration
     * @param array $data
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws coding_exception
     */
    public static function patch_registration_resource(RegistrationResource $registration, array $data) {
        $id = $registration->RegistrationID;
        if ($id <= 0) {
            throw new coding_exception('UnexpectedValueException');
        }
        if (empty($data)) {
            throw new coding_exception('UnexpectedValueException');
        }
        $pluginconfig = new arlo_plugin_config();
        $updatableproperties = explode(',', $pluginconfig->get('updatableregistrationproperties'));
        $client = client::get_instance();
        $uri = new RequestUri();
        $uri->setHost($pluginconfig->get('platform'));
        $uri->setResourcePath("registrations/{$id}/");
        $dom = new DOMDocument('1.0', 'utf-8');
        $root = $dom->appendChild(new DOMElement('diff'));
        foreach ($data as $key => $value) {
            if (!is_null($value)) {
                if (in_array($key, $updatableproperties) && ($registration->{$key} != $value)) {
                    if (!is_null($registration->{$key})) {
                        $element = $dom->createElement('replace', $value);
                        $element->setAttribute("sel", "Registration/{$key}/text()[1]");
                        $root->appendChild($element);
                    } else {
                        $add = $dom->createElement("add");
                        $add->setAttribute("sel", "Registration");
                        $element = $dom->createElement($key, $value);
                        $add->appendChild($element);
                        $root->appendChild($add);
                    }
                }
            }
        }
        $xmlbody = $dom->saveXML();
        $request = new Request(
            'PATCH',
            $uri->output(true),
            ['Content-type' => 'application/xml; charset=utf-8'],
            $xmlbody
        );
        try {
            $response = $client->send($request);
            $statuscode = $response->getStatusCode();
        } catch (Exception $ex) {
            if ($ex instanceof ClientException || $ex instanceof ServerException) {
                $statuscode = $ex->getCode();
                $message = $ex->getMessage();
                $persistent = registration_persistent::get_record(['sourceguid' => $registration->UniqueIdentifier]);
                $persistent->add_error_message($message);
                $persistent->save();
            }
        } finally {
            $pluginconfig = new arlo_plugin_config();
            if (isset($statuscode)) {
                $pluginconfig->set('apistatus', $statuscode);
            }
            // Log request.
            $requestlog = new request_log_persistent();
            $requestlog->set('timelogged', time());
            $requestlog->set('uri', $uri->output(true));
            if (isset($statuscode)) {
                $requestlog->set('status', $statuscode);
            }
            if (isset($message)) {
               $requestlog->set('extra', $message);
            }
            $requestlog->save();
        }
    }

}
