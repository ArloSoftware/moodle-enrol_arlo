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
use enrol_arlo\local\persistent\registration_persistent;
use enrol_arlo\local\persistent\request_log_persistent;
use Exception;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\Arlo\AuthAPI\Resource\Registration as RegistrationResource;
use enrol_arlo\local\config\arlo_plugin_config;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;

defined('MOODLE_INTERNAL') || die();

class external {

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

    public static function patch_registration_resource(RegistrationResource $registration, array $data) {
        $id = $registration->RegistrationID;
        if ($id <= 0) {
            throw new coding_exception('UnexpectedValueException');
        }
        if (empty($data)) {
            throw new coding_exception('UnexpectedValueException');
        }
        $pluginconfig = new arlo_plugin_config();
        $client = client::get_instance();
        $uri = new RequestUri();
        $uri->setHost($pluginconfig->get('platform'));
        $uri->setResourcePath("registrations/{$id}/");
        $dom = new DOMDocument('1.0', 'utf-8');
        $root = $dom->appendChild(new DOMElement('diff'));
        foreach ($data as $key => $value) {
            if (!is_null($value)) {
                if ($registration->{$key} != $value) {
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
