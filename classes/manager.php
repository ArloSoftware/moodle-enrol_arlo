<?php

namespace enrol_arlo;

use enrol_arlo\Arlo\AuthAPI\Client;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\Arlo\AuthAPI\Filter;
use enrol_arlo\Arlo\AuthAPI\Resource\Registration;
use enrol_arlo\Arlo\AuthAPI\XmlDeserializer;
use enrol_arlo\Arlo\AuthAPI\Resource\AbstractCollection;
use enrol_arlo\Arlo\AuthAPI\Resource\AbstractResource;
use enrol_arlo\Arlo\AuthAPI\Resource\ApiException;
use enrol_arlo\Arlo\AuthAPI\Resource\Event;
use enrol_arlo\Arlo\AuthAPI\Resource\EventTemplate;
use enrol_arlo\Arlo\AuthAPI\Resource\OnlineActivity;
use enrol_arlo\Arlo\AuthAPI\Enum\RegistrationStatus;

use enrol_arlo\exception\client_exception;
use enrol_arlo\exception\server_exception;


use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;


class manager {
    /** @var $plugin enrolment plugin instance. */
    private static $plugin;

    private $apiusername;
    private $apipassword;
    private $trace;

    const DELAY_REQUEST_SECONDS = 900; // 15 Minutes.

    public function __construct(\progress_trace $trace = null) {
        // Setup trace.
        if (is_null($trace)) {
            $this->trace = new \null_progress_trace();
        } else {
            $this->trace = $trace;
        }
        self::$plugin = enrol_get_plugin('arlo');
    }

    /**
     * @return bool
     */
    public function api_callable() {
        $apilaststatus      = (int) self::$plugin->get_config('apistatus');
        $apilastrequested   = self::$plugin->get_config('apilastrequested');
        $apierrorcount      = self::$plugin->get_config('apierrorcount');
        // Maximum error count reached.
        if ($apierrorcount >= collection_request::MAXIMUM_ERROR_COUNT) {
            self::trace('API error count has exceeded maximum permissible errors.');
            return false;
        }
        // Client errors.
        if ($apilaststatus == 401 && $apilaststatus == 403) {
            if ($apilastrequested + self::DELAY_REQUEST_SECONDS > time()) {
                self::trace(sprintf("API delay request until: %s", userdate($apilastrequested)));
                return false;
            }
            return true;
        }
        // Server errors.
        if ($apilaststatus > 500 && $apilaststatus < 599) {
            if ($apilastrequested + self::DELAY_REQUEST_SECONDS > time()) {
                self::trace(sprintf("API delay request until: %s", userdate($apilastrequested)));
                return false;
            }
            return true;
        }
        return true;
    }

    public function process_instances() {
        global $DB;
        $platform = self::$plugin->get_config('platform');
        $conditions = array(
            'enrol' => 'arlo',
            'status' => ENROL_INSTANCE_ENABLED,
            'platform' => $platform
        );
        $sql = "SELECT e.* 
                  FROM {enrol} e
                  JOIN {enrol_arlo_instance} ai
                    ON ai.enrolid = e.id
                 WHERE e.enrol = :enrol 
                   AND e.status = :status
                   AND ai.platform = :platform
              ORDER BY ai.nextpulltime";

        $records = $DB->get_records_sql($sql, $conditions);
        foreach ($records as $record) {
            self::update_instance_registrations($record);
        }

    }

    public static function get_collection_sync_info($collection) {
        global $DB;
        $platform = self::$plugin->get_config('platform');
        $conditions = array('type' => $collection);
        $record = $DB->get_record('enrol_arlo_collection', $conditions);
        if (!$record) {
            $record                         = new \stdClass();
            $record->platform               = $platform;
            $record->type                   = $collection;
            $record->latestsourcemodified   = '';
            $record->nextpulltime           = 0;
            $record->endpulltime            = 0;
            $record->lastpulltime           = 0;
            $record->lasterror              = '';
            $record->errorcount             = 0;
            $record->id = $DB->insert_record('enrol_arlo_collection', $record);
        }
        return $record;
    }

    public static function get_associated_arlo_instance(array $conditions) {
        global $DB;
        return $DB->get_record('enrol_arlo_instance', $conditions, '*', MUST_EXIST);
    }

    /**
     * @param \stdClass $record
     * @param bool $hasnext
     * @return \stdClass
     */
    public static function update_collection_sync_info(\stdClass $record, $hasnext= false) {
        global $DB;

        $record->lastpulltime = time();
        // Only update nextpulltime if no more records to process.
        if (!$hasnext) {
            $record->nextpulltime = time();
        }
        $DB->update_record('enrol_arlo_collection', $record);
        return $record;
    }

    public static function update_associated_arlo_instance(\stdClass $record, $hasnext= false) {

    }

    public function update_instance_registrations($instance, $manualoverride = false) {
        $timestart = microtime();
        self::trace("Updating Registrations for instance");
        try {
            $hasnext = true; // Initialise to for multiple pages.
            while ($hasnext) {
                $hasnext = false; // Avoid infinite loop by default.
                // Get sync information.
                $arloinstance = self::get_associated_arlo_instance(array('enrolid' => $instance->id));
                $type     = $arloinstance->type;
                $sourceid = $arloinstance->sourceid;
                if ($type == \enrol_arlo_plugin::ARLO_TYPE_EVENT) {
                    $resourcepath = 'events/' . $sourceid . '/registrations/';

                }
                if ($type == \enrol_arlo_plugin::ARLO_TYPE_ONLINEACTIVITY) {
                    $resourcepath = 'onlineactivities/' . $sourceid . '/registrations/';
                }
                // Setup RequestUri for getting Events.
                $requesturi = new RequestUri();
                $requesturi->setPagingTop(2);
                $requesturi->setResourcePath($resourcepath);
                $requesturi->addExpand('Registration/Contact');
                $request = new instance_request($arloinstance, $requesturi, $manualoverride);
                if (!$request->executable()) {
                    self::trace('Cannot execute request due to timing or API status');
                } else {
                    $response = $request->execute();
                    if (200 != $response->getStatusCode()) {
                        self::trace(sprintf("Bad response (%s) leaving the room.", $response->getStatusCode()));
                        return false;
                    }
                    $collection = self::deserialize_response_body($response);
                    // Any returned.
                    if (empty($collection)) {
                        //self::update_associated_arlo_instance($arloinstance, $hasnext);
                        self::trace("No new or updated resources found.");
                    } else {
                        foreach ($collection as $registration) {
                            self::update_enrolment_registration($instance, $arloinstance, $registration);
                            $latestmodified = $event->LastModifiedDateTime;
                            $arloinstance->latestsourcemodified = $latestmodified;
                        }
                        $hasnext = (bool) $collection->hasNext();
                        //self::update_associated_arlo_instance($arloinstance, $hasnext);
                    }
                }
            }
        } catch (\Exception $e) {
            print_object($e); // TODO handle XMLParse and Moodle exceptions.
            die;
        }
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        self::trace("Execution took {$difftime} seconds");
        return true;
    }

    protected function helper_make_record_from_resource() {

    }

    public function update_enrolment_registration($instance, $arloinstance, Registration $registration) {
        global $DB;
        $contactresource = $registration->getContact();
        if (is_null($contactresource)) {
            throw new \moodle_exception('Contact is not set on Registration');
        }
        $plugin = self::$plugin;

        // Load Contact.
        $user = user::get_by_guid($contactresource->UniqueIdentifier);
        if (!$user->exists()) {
            $user = $user->create($contactresource);
        }

        $conditions = array('userid' => $user->id, 'enrolid' => $instance->id);
        $registrationrecord = $DB->get_record('enrol_arlo_registration', $conditions);

        if ($registration->Status == RegistrationStatus::APPROVED || $registration->Status == RegistrationStatus::COMPLETED) {

        }

print_object($instance);
print_object($user);
print_object($registration);

        die;
    }

    public function update_events($manualoverride = false) {
        $timestart = microtime();
        self::trace("Updating Events");
        try {
            $hasnext = true; // Initialise to for multiple pages.
            while ($hasnext) {
                $hasnext = false; // Avoid infinite loop by default.
                // Get sync information.
                $syncinfo = self::get_collection_sync_info('events');
                // Setup RequestUri for getting Events.
                $requesturi = new RequestUri();
                $requesturi->setResourcePath('events/');
                $requesturi->addExpand('Event/EventTemplate');
                $request = new collection_request($syncinfo, $requesturi, $manualoverride);
                if (!$request->executable()) {
                    self::trace('Cannot execute request due to throttling');
                } else {
                    $response = $request->execute();
                    if (200 != $response->getStatusCode()) {
                        self::trace(sprintf("Bad response (%s) leaving the room.", $response->getStatusCode()));
                        return false;
                    }
                    $collection = self::deserialize_response_body($response);
                    // Any returned.
                    if (empty($collection)) {
                        self::update_collection_sync_info($syncinfo, $hasnext);
                        self::trace("No new or updated resources found.");
                    } else {
                        foreach ($collection as $event) {
                            $record = self::update_event($event);
                            $latestmodified = $event->LastModifiedDateTime;
                            $syncinfo->latestsourcemodified = $latestmodified;
                        }
                        $hasnext = (bool) $collection->hasNext();
                        self::update_collection_sync_info($syncinfo, $hasnext);
                    }
                }
            }
        } catch (\Exception $e) {
            print_object($e); // TODO handle XMLParse and Moodle exceptions.
            die;
        }
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        self::trace("Execution took {$difftime} seconds");
        return true;
    }

    public function update_onlineactivities($manualoverride = false) {
        $timestart = microtime();
        self::trace("Updating Online Activities");
        try {
            $hasnext = true; // Initialise to for multiple pages.
            while ($hasnext) {
                $hasnext = false; // Avoid infinite loop by default.
                // Get sync information.
                $syncinfo = self::get_collection_sync_info('onlineactivities');
                // Setup RequestUri for getting Events.
                $requesturi = new RequestUri();
                $requesturi->setResourcePath('onlineactivities/');
                $requesturi->addExpand('OnlineActivity/EventTemplate');
                $request = new collection_request($syncinfo, $requesturi, $manualoverride);
                if (!$request->executable()) {
                    self::trace('Cannot execute request due to throttling');
                } else {
                    $response = $request->execute();
                    if (200 != $response->getStatusCode()) {
                        self::trace(sprintf("Bad response (%s) leaving the room.", $response->getStatusCode()));
                        return false;
                    }
                    $collection = self::deserialize_response_body($response);
                    // Any returned.
                    if (empty($collection)) {
                        self::update_collection_sync_info($syncinfo, $hasnext);
                        self::trace("No new or updated resources found.");
                    } else {
                        foreach ($collection as $onlineactivity) {
                            $record = self::update_onlineactivity($onlineactivity);
                            $latestmodified = $onlineactivity->LastModifiedDateTime;
                            $syncinfo->latestsourcemodified = $latestmodified;
                        }
                        $hasnext = (bool) $collection->hasNext();
                        self::update_collection_sync_info($syncinfo, $hasnext);
                    }
                }
            }
        } catch (\Exception $e) {
            print_object($e); // TODO handle XMLParse and Moodle exceptions.
            die;
        }
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        self::trace("Execution took {$difftime} seconds");
        return true;
    }

    public function update_templates($manualoverride = false) {
        $timestart = microtime();
        if (!self::api_callable()) {
            return false;
        }

        self::trace("Updating Templates");
        try {
            $hasnext = true; // Initialise to for multiple pages.
            while ($hasnext) {
                $hasnext = false; // Avoid infinite loop by default.
                // Get sync information.
                $syncinfo = self::get_collection_sync_info('eventtemplates');
                // Setup RequestUri for getting Templates.
                $requesturi = new RequestUri();
                $requesturi->setResourcePath('eventtemplates/');
                $requesturi->addExpand('EventTemplate');
                $request = new collection_request($syncinfo, $requesturi, $manualoverride);
                if (!$request->executable()) {
                    self::trace('Cannot execute request due to throttling');
                } else {
                    $response = $request->execute();
                    if (200 != $response->getStatusCode()) {
                        self::trace(sprintf("Bad response (%s) leaving the room.", $response->getStatusCode()));
                        return false;
                    }
                    $collection = self::deserialize_response_body($response);
                    // Any returned.
                    if (empty($collection)) {
                        self::update_collection_sync_info($syncinfo, $hasnext);
                        self::trace("No new or updated resources found.");
                    } else {
                        foreach ($collection as $template) {
                            $record = self::update_template($template);
                            $latestmodified = $template->LastModifiedDateTime;
                            $syncinfo->latestsourcemodified = $latestmodified;
                        }
                        $hasnext = (bool) $collection->hasNext();
                        self::update_collection_sync_info($syncinfo, $hasnext);
                    }
                }
            }
        } catch (\Exception $e) {
            print_object($e); // TODO handle XMLParse and Moodle exceptions.
            die;
        }
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        self::trace("Execution took {$difftime} seconds");
        return true;
    }

    private function deserialize_response_body(Response $response) {
        // Returned HTTP status, used for error checking.
        $status = (int) $response->getStatusCode();
        $reason = $response->getReasonPhrase();
        // Incorrect content-type.
        $contenttype = $response->getHeaderLine('content-type');
        if (strpos($contenttype, 'application/xml') === false) {
            throw new server_exception(
                $reason,
                $status,
                'error_incorrectcontenttype',
                array('contenttype' => $contenttype)
            );
        }
        // Deserialize response body.
        $deserializer = new XmlDeserializer('\enrol_arlo\Arlo\AuthAPI\Resource\\');
        $stream = $response->getBody();
        $contents = $stream->getContents();
        if ($stream->eof()) {
            $stream->rewind(); // Rewind stream.
        }
        // If everything went OK a resource class will be returned.
        return $deserializer->deserialize($contents);
    }

    public function update_event(Event $event) {
        global $DB;

        $platform               = self::$plugin->get_config('platform');
        $record                 = new \stdClass();
        $record->platform       = $platform;
        $record->sourceid       = $event->EventID;
        $record->sourceguid     = $event->UniqueIdentifier;

        $record->code           = $event->Code;
        $record->startdatetime  = $event->StartDateTime;
        $record->finishdatetime = $event->FinishDateTime;

        $record->sourcestatus   = $event->Status;
        $record->sourcecreated  = $event->CreatedDateTime;
        $record->sourcemodified = $event->LastModifiedDateTime;
        $record->modified       = time();

        $template = $event->getEventTemplate();
        if ($template) {
            $record->sourcetemplateid       = $template->TemplateID;
            $record->sourcetemplateguid     = $template->UniqueIdentifier;
        }

        $params = array(
            'platform'      => $platform,
            'sourceid'      => $record->sourceid,
            'sourceguid'    => $record->sourceguid
        );
        $record->id = $DB->get_field('enrol_arlo_event', 'id', $params);
        if (empty($record->id)) {
            unset($record->id);
            $record->id = $DB->insert_record('enrol_arlo_event', $record);
            self::trace(sprintf('Created: %s', $record->code));
        } else {
            $DB->update_record('enrol_arlo_event', $record);
            self::trace(sprintf('Updated: %s', $record->code));
        }
        return $record;
    }

    public function update_template(EventTemplate $template) {
        global $DB;

        $platform               = self::$plugin->get_config('platform');
        $record                 = new \stdClass();
        $record->platform       = $platform;
        $record->sourceid       = $template->TemplateID;
        $record->sourceguid     = $template->UniqueIdentifier;
        $record->name           = $template->Name;
        $record->code           = $template->Code;
        $record->sourcestatus   = $template->Status;
        $record->sourcecreated  = $template->CreatedDateTime;
        $record->sourcemodified = $template->LastModifiedDateTime;
        $record->modified       = time();

        $params = array(
            'platform'      => $platform,
            'sourceid'      => $record->sourceid,
            'sourceguid'    => $record->sourceguid
        );
        $record->id = $DB->get_field('enrol_arlo_template', 'id', $params);
        if (empty($record->id)) {
            unset($record->id);
            $record->id = $DB->insert_record('enrol_arlo_template', $record);
            self::trace(sprintf('Created: %s', $record->name));
        } else {
            $DB->update_record('enrol_arlo_template', $record);
            self::trace(sprintf('Updated: %s', $record->name));
        }
        return $record;
    }

    public function update_onlineactivity(OnlineActivity $onlineactivity) {
        global $DB;

        $platform               = self::$plugin->get_config('platform');
        $record                 = new \stdClass();
        $record->platform       = $platform;
        $record->sourceid       = $onlineactivity->OnlineActivityID;
        $record->sourceguid     = $onlineactivity->UniqueIdentifier;
        $record->name           = $onlineactivity->Name;
        $record->code           = $onlineactivity->Code;
        $record->contenturi     = $onlineactivity->ContentUri;
        $record->sourcestatus   = $onlineactivity->Status;
        $record->sourcecreated  = $onlineactivity->CreatedDateTime;
        $record->sourcemodified = $onlineactivity->LastModifiedDateTime;
        $record->modified       = time();

        $template = $onlineactivity->getEventTemplate();
        if ($template) {
            $record->sourcetemplateid       = $template->TemplateID;
            $record->sourcetemplateguid     = $template->UniqueIdentifier;
        }

        $params = array(
            'platform'      => $platform,
            'sourceid'      => $record->sourceid,
            'sourceguid'    => $record->sourceguid
        );
        $record->id = $DB->get_field('enrol_arlo_onlineactivity', 'id', $params);
        if (empty($record->id)) {
            unset($record->id);
            $record->id = $DB->insert_record('enrol_arlo_onlineactivity', $record);
            self::trace(sprintf('Created: %s', $record->name));
        } else {
            $DB->update_record('enrol_arlo_onlineactivity', $record);
            self::trace(sprintf('Updated: %s', $record->name));
        }
        return $record;
    }

    /**
     * Output a progress message.
     *
     * @param $message the message to output.
     * @param int $depth indent depth for this message.
     */
    private function trace($message, $depth = 0) {
        $this->trace->output($message, $depth);
    }
}
