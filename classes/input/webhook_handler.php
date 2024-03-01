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

namespace enrol_arlo\input;

use moodle_url;
use stdClass;
use enrol_arlo\local\client;
use enrol_arlo\task\webhook_task;
use GuzzleHttp\Psr7\Request;

/**
 * @package   enrol_arlo
 *
 * @author    2023 Oscar Nadjar <oscar.nadjar@moodle.com>
 * @copyright Moodle US
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webhook_handler {

    /**
     * Mapping between the event type and the class to handle the event.
     * 
     * @var array
     */
    public static $classesmapping = [
        'Registration' => [
            'classname' => 'enrol_arlo\local\job\memberships_job',
            'method'    => 'process_registration_event']
    ];
    /**
     * Arlo domain.
     * 
     * @var string
     */
    private $platform;

    /**
     * Secret key used to validate the signature of the request.
     * 
     * @var string
     */
    private $webhooksecretkey;

     /**
     * Email of the Arlo admin user.
     * 
     * @var string
     */
    private $contact;

    /**
     * Current active webhook ID.
     * 
     * @var string
     */
    private $webhookid;

    /**
     * Current active webhook API URL.
     * 
     * @var string
     */
    private $webhookapiurl;

    /**
     * Moodle endpoint.
     * 
     * @var string
     */
    private $moodle_endpoint;

    /**
     * Enable webhook ebdpoint.
     * 
     * @var bool
     */
    private $enablewebhook;

    /**
     * Use adhoc.
     * 
     * @var bool
     */
    private $useadhoctask;

    /**
     * Constructor.
     * 
     * @var \enrol_arlo\api
     */
    public function __construct() {
        $config = get_config('enrol_arlo');
        $this->platform = !empty($config->platform) ? $config->platform : false;
        $this->contact = !empty($config->apiusername) ? $config->apiusername : false;
        $this->webhooksecretkey = !empty($config->webhooksecret) ? $config->webhooksecret : false;
        $this->webhookid = !empty($config->webhookid) ? $config->webhookid : false;
        $webhookapipath = !empty($config->webhookapipath) ? $config->webhookapipath : false;
        $this->webhookapiurl = $this->get_webhookapiurl($webhookapipath);
        $this->moodle_endpoint = (new moodle_url('/enrol/arlo/webhook_endpoint.php'))->out(false);
        $this->enablewebhook = !empty($config->enablewebhook) ? $config->enablewebhook : false;
        $this->useadhoctask = !empty($config->useadhoctask) ? $config->useadhoctask : false;
    }

    /**
     * Check if the webhook is enable.
     * 
     * @return void
     */
    public function webhook_is_enable() {
        return $this->enablewebhook;
    }

    /**
     * Validate the signature of the request.
     * 
     * @param string $signature
     * @param string $body 
     * @param string $webhooksecretkey
     * @return bool
     */
    public function validatesignature($signature, $body, $webhooksecretkey = false) {

        if (empty($webhooksecretkey)) {
            $webhooksecretkey = $this->webhooksecretkey;
        }
        
        // Decode the base64 secret key
        $secretkeystring = base64_decode($webhooksecretkey);
    
        // Compute the HMAC signature
        $hmacsignature = hash_hmac('sha512', $body, $secretkeystring, true);
        // Base64 encode the signature
        $hmacsignaturebase64 = base64_encode($hmacsignature);

        return hash_equals($signature, $hmacsignaturebase64);
    }

    /**
     * Create a webhook in Arlo and store the webhook ID.
     *
     * @param stdClass $data The webhook data.
     * @return int|bool The webhook ID.
     */
    public function createwebhook(stdClass $data) {

        $client = new client();
        $client = $client->get_instance();

        // Set the Arlo API endpoint URL
        $url = $this->get_webhookapiurl();
        $webhook = new \stdClass();
        $webhook->Name = $data->name;
        $webhook->Endpoint = $this->moodle_endpoint;
        $webhook->TechnicalContact = !empty($data->contact) ? $data->contact : $this->contact;
        $webhook->Status = 'Active';
        $webhook->PayloadSchema = $data->format;
        $webhook->EventTypes = 'RegistrationCreated,RegistrationUpdated';
        $xmlbody = $this->converttoxml($webhook);
        
        $request = new Request('POST', $url, ['Content-type' => 'application/xml; charset=utf-8'], $xmlbody);
        try {
            $response = $client->send($request);
        } catch (\Exception $ex) {
            $message = $ex->getMessage();
            echo $message;
        } finally {
            $bodycontent = $response->getBody()->getContents();
            $xml = simplexml_load_string($bodycontent);
            if (!empty($xml->ID)) {
                set_config('webhookid', (string) $xml->ID, 'enrol_arlo');
                set_config('webhooksecret', (string) $xml->Key, 'enrol_arlo');
                return true;
            } else {
                return false;
            }
        }        
    }

    /**
     * Validate the webhook ID.
     *
     * @return bool True if the webhook ID is valid.
     */
    public function validatedwebhookid() {

        $webhookid = $this->webhookid;
        if (empty($webhookid)) {
            return false;
        }
        $webhookapiurl = $this->get_webhookapiurl();

        // Set the Arlo API endpoint URL
        $endpoint = $webhookapiurl . '/' . $webhookid;
    
        $client = new client();
        $client = $client->get_instance();
        $request = new Request('GET', $endpoint);

        try {
            $response = $client->send($request);
        } catch (\Exception $ex) {
            $message = $ex->getMessage();
            echo $message;
        } finally {
            if (!empty($response) ){
                $bodycontent = $response->getBody()->getContents();
                $xml = simplexml_load_string($bodycontent);
                if (!empty($xml->Status) && (string) $xml->Status == 'Active') {
                    return true;
                } else {
                    return false;
                }
            }
        }   
    }

    /**
     * Get a valid webhook api url.
     *
     * @return string
     */
    private function get_webhookapiurl($webhookapipath = false) {

        if (empty($this->webhookapiurl)) {
            if (empty($webhookapipath)) {
                $webhookapiurl = "https://$this->platform/api/2012-02-01/auth/resources/webhookendpoints";
            } else {
                $url = (new moodle_url($this->platform . $webhookapipath))->out(false);
                $webhookapiurl = $url;
            }

        } else {
            $webhookapiurl = $this->webhookapiurl;
        }
        return $webhookapiurl;
    }

    /**
     * Convert webhook data to XML format.
     *
     * @param stdClass $data The webhook data.
     * @return string The XML string.
     */
    private function converttoxml(stdClass $data) {
        $xml = new \SimpleXMLElement('<WebhookEndpoint></WebhookEndpoint>');
        $xml->addChild('Name', $data->Name);
        $xml->addChild('Endpoint', $data->Endpoint);
        $xml->addChild('TechnicalContact', !empty($data->TechnicalContact) ? $data->TechnicalContact : $this->contact);
        $xml->addChild('PayloadSchema', $data->PayloadSchema);
        $xml->addChild('Status', $data->Status);
        $xml->addChild('EventTypes', $data->EventTypes);
        
        return $xml->asXML();
    }

    /**
     * Process the events.
     *
     * @param array $events.
     * @return void
     */
    public function process_events($events) {
        foreach ($events as $event) {
            if (empty(self::$classesmapping[$event->resourceType]) ||
                !class_exists(self::$classesmapping[$event->resourceType]['classname'])) {
                echo 'Event type: ' . $event->resourceType .  ' not supported' . PHP_EOL;
                continue;
            }
            if ($this->useadhoctask) {
                webhook_task::queue_task($event);
            } else {
                $this->process_event($event);
            }
        }
    }

    /**
     * Process the event.
     *
     * @param array $events.
     * @return void
     */
    public static function process_event($event) {
        global $CFG;
        require_once($CFG->dirroot . '/enrol/arlo/lib.php');
        if (!enrol_is_enabled('arlo')) {
            return;
        }
        $trace = new \null_progress_trace();
        if ($CFG->debug == DEBUG_DEVELOPER) {
            $trace = new \text_progress_trace();
        }
        $classhandler = self::get_class($event);
        $lockcallback = $classhandler . '::get_lock_factory';
        $lockfactory = $lockcallback();
        // Putting here small time because someone else is trying to process the same resource,
        // so we probably don't need to process it again.
        $lock = $lockfactory->get_lock($event->resourceType . ': ' . $event->resourceId, 1);
        if ($lock) {
            try{
                $callback = $classhandler . self::get_callback($event);
                $callback($event, $trace);
            } catch (\moodle_exception $exception) {
                debugging($exception->getMessage(), DEBUG_DEVELOPER);
            } finally {
                $lock->release();
            }
        } else {
           return;
        }
    }

    /**
     * Get the class for the event.
     *
     * @param object $event.
     * @return void
     */
    public static function get_class($event) {
        $eventtype = $event->resourceType;
        $classhandler = self::$classesmapping[$eventtype]['classname'];
        return $classhandler;
    }

    /**
     * Get the callback method for the event.
     *
     * @param object $event.
     * @return void
     */
    public static function get_callback($event) {
        $eventtype = $event->resourceType;
        $callback = self::$classesmapping[$eventtype]['method'];
        return '::' . $callback;
    }
}