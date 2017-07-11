<?php

namespace enrol_arlo;

use enrol_arlo\Arlo\AuthAPI\Client;
use enrol_arlo\Arlo\AuthAPI\Exception\XMLDeserializerException;
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
use enrol_arlo\Arlo\AuthAPI\Enum\RegistrationOutcome;

use enrol_arlo\exception\invalidcontent_exception;
use enrol_arlo\request\collection;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Psr7\Response;



class manager {
    const REQUEST_INTERVAL_SECONDS      = 900; // 15 Minutes.
    const REQUEST_EXTENSION_SECONDS     = 259200; // 72 Hours.
    const MAXIMUM_ERROR_COUNT           = 20;
    /** @var DELAY_REQUEST_SECONDS time in seconds to delay next request. */
    const DELAY_REQUEST_SECONDS         = 900; // 15 Minutes.
    /** @var $plugin enrolment plugin instance. */
    private static $plugin;
    /** @var \progress_trace  */
    private static $trace;

    public function __construct(\progress_trace $trace = null) {
        // Raise limits, so this script can be interrupted without problems.
        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);
        // Setup trace.
        if (is_null($trace)) {
            self::$trace = new \null_progress_trace();
        } else {
            self::$trace = $trace;
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
        if (!enrol_is_enabled('arlo')) {
            self::trace(get_string('pluginnotenabled', 'enrol_arlo'));
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

    public function process_all($manualoverride = false) {
        // Order of processing.
        self::process_templates($manualoverride);
        self::process_events($manualoverride);
        self::process_onlineactivities($manualoverride);
    }

    /**
     * Function for get enrolment instance to process. Hidden instances or instances in a hidden
     * course are not included.
     *
     * @param bool $manualoverride
     * @return bool
     */
    public function process_instances($manualoverride = false) {
        global $DB;
        $platform = self::$plugin->get_config('platform');

        $sql = "SELECT e.* 
                  FROM {enrol} e
                  JOIN {enrol_arlo_instance} ai ON ai.enrolid = e.id
                  JOIN {course} c ON c.id = e.courseid
                 WHERE e.enrol = :enrol 
                   AND e.status = :status
                   AND ai.platform = :platform
                   AND c.visible = 1
              ORDER BY ai.nextpulltime";

        $conditions = array(
            'enrol' => 'arlo',
            'status' => ENROL_INSTANCE_ENABLED,
            'platform' => $platform
        );
        $records = $DB->get_records_sql($sql, $conditions);
        if (empty($records)) {
            self::trace('No enrolment instances to process.');
        } else {
            foreach ($records as $record) {
                self::process_instance_registrations($record, $manualoverride);
            }
        }
        return true;
    }

    public function get_enrol_instances($orderby = '') {
        global $DB;
        $platform = self::$plugin->get_config('platform');
        $sql = "SELECT e.*
                  FROM {enrol} e
                  JOIN {enrol_arlo_instance} ai ON ai.enrolid = e.id
                  JOIN {course} c ON c.id = e.courseid
                 WHERE e.enrol = :enrol
                   AND e.status = :status
                   AND ai.platform = :platform
                   AND c.visible = 1";
        if (!empty($orderby)) {
            $sql .= " ORDER BY {$orderby}";
        }
        $conditions = array(
            'enrol' => 'arlo',
            'status' => ENROL_INSTANCE_ENABLED,
            'platform' => $platform
        );
        return $DB->get_records_sql($sql, $conditions);
    }

    public function process_results($manualoverride = false) {
        $records = self::get_enrol_instances();
        foreach ($records as $instance) {
            self::process_instance_results($instance, $manualoverride);
        }
    }

    public function process_instance_results($instance, $manualoverride) {
        global $DB;
        $platform       = self::$plugin->get_config('platform');
        $apiusername    = self::$plugin->get_config('apiusername');
        $apipassword    = self::$plugin->get_config('apipassword');
        $conditions = array(
            'enrolid' => $instance->id,
            'updatesource' => 1
        );
        $records = $DB->get_records('enrol_arlo_registration', $conditions);
        foreach ($records as $registrationrecord) {
            $result = new result($instance->courseid, $registrationrecord);
            $body = $result->export_to_xml();
            $sourceid = $registrationrecord->sourceid;
            $requesturi = new RequestUri();
            $requesturi->setHost($platform);
            $resourcepath = 'registrations/' . $sourceid . '/';
            $requesturi->setResourcePath($resourcepath);
            try{
                $client = new Client($platform, $apiusername, $apipassword);
                $headers = array('Content-type' => 'application/xml; charset=utf-8');
                $response = $client->request('patch', $requesturi, $headers, $body);
            } catch (BadResponseException $e) {
                $c = $e->getResponse()->getBody()->getContents();
                print_object($e->getMessage());
                print_object($c);
            }

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
        $record->tablename = 'enrol_arlo_collection';
        return $record;
    }

    public static function get_associated_arlo_instance(array $conditions) {
        global $DB;
        return $DB->get_record('enrol_arlo_instance', $conditions);
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
        $record->tablename = 'enrol_arlo_collection';
        return $record;
    }

    public static function update_associated_arlo_instance(\stdClass $record, $hasnext= false) {
        global $DB;
        $record->lastpulltime = time();
        // Only update nextpulltime if no more records to process.
        if (!$hasnext) {
            $record->nextpulltime = time();
        }
        $DB->update_record('enrol_arlo_instance', $record);
        return $record;
    }

    protected static function can_pull($record, $manualoverride = false) {
        $timestart = time();
        if ($manualoverride) {
            return true;
        }
        // Pull disabled for this record.
        if ($record->nextpulltime == -1) {
            self::trace('Disabled due to errors');
            return false;
        }
        $nextpulltime = $record->nextpulltime;
        // Return if next pull time hasn't passed current time.
        if ($timestart < ($nextpulltime + self::REQUEST_INTERVAL_SECONDS)) {
            self::trace('Next pull time not yet reached');
            return false;
        }
        $endpulltime = $record->endpulltime;
        // Return if end pull time has past.
        if (!empty($endpulltime) && $timestart > ($endpulltime + self::REQUEST_EXTENSION_SECONDS)) {
            self::trace('End pull time has passed');
            return false;
        }
        return true;
    }

    protected static function can_push() {}


    /**
     * Process any registrations for an enrolment instance.
     *
     * @param $instance
     * @param bool $manualoverride
     * @return bool
     */
    public function process_instance_registrations($instance, $manualoverride = false) {
        $timestart = microtime();
        if (!self::api_callable()) {
            self::trace('API not callable due to status');
            return false;
        }
        self::trace("Updating Registrations for instance");
        try {
            $hasnext = true; // Initialise to for multiple pages.
            while ($hasnext) {
                $hasnext = false; // Avoid infinite loop by default.
                // Get sync information.
                $arloinstance = self::get_associated_arlo_instance(array('enrolid' => $instance->id));
                if (!$arloinstance) {
                    self::trace('No matching Arlo enrolment instance.');
                    break;
                }
                if (!self::can_pull($arloinstance, $manualoverride)) {
                    break;
                }
                $type     = $arloinstance->type;
                $sourceid = $arloinstance->sourceid;
                // Event, set resource path and expand accordingly.
                if ($type == \enrol_arlo_plugin::ARLO_TYPE_EVENT) {
                    $resourcepath = 'events/' . $sourceid . '/registrations/';
                    $expand = 'Registration/Event';

                }
                // Online Activity, set resource path and expand accordingly.
                if ($type == \enrol_arlo_plugin::ARLO_TYPE_ONLINEACTIVITY) {
                    $resourcepath = 'onlineactivities/' . $sourceid . '/registrations/';
                    $expand = 'Registration/OnlineActivity';
                }
                // Setup RequestUri for getting Events.
                $requesturi = new RequestUri();
                $requesturi->setResourcePath($resourcepath);
                $requesturi->addExpand('Registration/Contact');
                $requesturi->addExpand($expand);
                $request = new instance_request($arloinstance, $requesturi, $manualoverride);
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
                        self::update_associated_arlo_instance($arloinstance, false);
                        self::trace("No new or updated resources found.");
                    } else {
                        foreach ($collection as $registration) {
                            self::process_enrolment_registration($instance, $arloinstance, $registration);
                            $latestmodified = $registration->LastModifiedDateTime;
                            $arloinstance->latestsourcemodified = $latestmodified;
                        }
                        $hasnext = (bool) $collection->hasNext();
                        $apionepageperrequest = self::$plugin->get_config('apionepageperrequest', false);
                        if ($apionepageperrequest) {
                            $hasnext = false;
                        }
                        self::update_associated_arlo_instance($arloinstance, $hasnext);
                        $delayemail = self::$plugin->get_config('delayemail', false);
                        if ($delayemail) {
                            break;
                        }
                        self::email_new_user_passwords();
                        self::email_welcome_message_per_instance($instance);

                    }
                }
            }
        } catch (\Exception $exception) {
            debugging($exception->getMessage(), DEBUG_NORMAL, $exception->getTrace());
            return false;
        }
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        self::trace("Execution took {$difftime} seconds");
        return true;
    }

    /**
     * Get users with enrol_arlo_createpassword preference set and email new
     * password.
     *
     */
    public function email_new_user_passwords() {
        global $DB;

        $sql = "SELECT u.*
                  FROM mdl_user u
                  JOIN mdl_user_preferences up ON up.userid = u.id
                 WHERE name = ?";
        $records = $DB->get_records_sql($sql, array('enrol_arlo_createpassword'));
        foreach($records as $user) {
            self::$plugin->email_newpassword($user);
        }
    }

    /**
     * Email course welcome to users in an enrolment instance.
     *
     * @param $instance
     */
    public function email_welcome_message_per_instance($instance) {
        global $DB;
        $sql = "SELECT u.*
                  FROM mdl_user u
                  JOIN mdl_user_preferences up ON up.userid = u.id
                 WHERE name = :name AND value = :value";
        $conditions = array('name' => 'enrol_arlo_coursewelcome_'.$instance->id, 'value' => $instance->id);
        $records = $DB->get_records_sql($sql, $conditions);
        foreach($records as $user) {
            self::$plugin->email_welcome_message($instance, $user);
        }
    }

    /**
     * Process enrolment registration. Enrol, unenrol or suspend based on configuration.
     *
     * @param $instance
     * @param $arloinstance
     * @param Registration $registration
     * @throws \moodle_exception
     */
    public function process_enrolment_registration($instance, $arloinstance, Registration $registration) {
        global $DB;

        $plugin = self::$plugin;
        $unenrolaction = $plugin->get_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);

        $contactresource = $registration->getContact();
        if (is_null($contactresource)) {
            throw new \moodle_exception('Contact is not set on Registration');
        }

        // Load Contact.
        $user = new user(self::$trace);
        $user->load_by_guid($contactresource->UniqueIdentifier);
        if (!$user->exists()) {
            $user = $user->create($contactresource);
        }
        $userid = $user->get_id();
        $conditions = array('userid' => $userid, 'enrolid' => $instance->id);
        $registrationrecord = $DB->get_record('enrol_arlo_registration', $conditions);
        $parameters = $conditions;
        // Add id if available.
        if ($registrationrecord) {
            $parameters['id'] = $registrationrecord->id;
        }
        // Build record for Moodle.
        $record = helper::resource_to_record($registration, $parameters);
        if ($registration->Status == RegistrationStatus::APPROVED || $registration->Status == RegistrationStatus::COMPLETED) {
            $record->modified = time();
            if (empty($record->id)) {
                unset($record->id);
                $record->id = $DB->insert_record('enrol_arlo_registration', $record);
                self::trace(sprintf('Created registration record: %s', $record->userid));
            } else {
                $DB->update_record('enrol_arlo_registration', $record);
                self::trace(sprintf('Updated registration record: %s', $record->userid));
            }
            $plugin->enrol_user($instance, $userid);
            self::trace(sprintf(sprintf('User %s enrolled', $record->userid)));
        }
        if ($registration->Status == RegistrationStatus::CANCELLED && ($unenrolaction == ENROL_EXT_REMOVED_UNENROL)) {
            $plugin->unenrol_user($instance, $userid);
            self::trace(sprintf(sprintf('User %s unenrolled', $record->userid)));
        }
        if ($registration->Status == RegistrationStatus::CANCELLED && ($unenrolaction == ENROL_EXT_REMOVED_SUSPENDNOROLES)) {
            $plugin->suspend_and_remove_roles($instance, $userid);
            self::trace(sprintf(sprintf('User %s suspended', $record->userid)));
        }
    }

    public function process_events($manualoverride = false) {
        global $DB;
        $timestart = microtime();
        if (!self::api_callable()) {
            return false;
        }
        list($platform, $apiusername, $apipassword) = self::get_connection_vars();
        self::trace("Processing Events");
        try {
            $hasnext = true; // Initialise to for multiple pages.
            while ($hasnext) {
                $hasnext = false; // Avoid infinite loop by default.
                // Get sync information.
                $syncinfo = self::get_collection_sync_info('events');
                if (!$syncinfo) {
                    self::trace('No matching sync instance');
                    break;
                }
                if (!self::can_pull($syncinfo, $manualoverride)) {
                    break;
                }
                // Setup RequestUri for getting Events.
                $requesturi = new RequestUri();
                $requesturi->setHost($platform);
                $requesturi->setResourcePath('events/');
                $requesturi->addExpand('Event/EventTemplate');
                $options = array();
                $options['auth'] = array(
                    $apiusername,
                    $apipassword
                );
                $request = new \enrol_arlo\request\collection_request($syncinfo, $requesturi, array(), null, $options);
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
                        $record = self::process_event($event);
                        $latestmodified = $event->LastModifiedDateTime;
                        $syncinfo->latestsourcemodified = $latestmodified;
                    }
                    $hasnext = (bool) $collection->hasNext();
                    self::update_collection_sync_info($syncinfo, $hasnext);
                }
            }
        } catch (\Exception $exception) {
            $record                 = new \stdClass();
            $record->timelogged     = time();
            $record->message        = $exception->getMessage();
            $DB->insert_record('enrol_arlo_applicationlog', $record);
            self::trace('Error processing Events');
            return false;
        }
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        self::trace("Execution took {$difftime} seconds");
        return true;
    }

    public function process_onlineactivities($manualoverride = false) {
        global $DB;
        $timestart = microtime();
        if (!self::api_callable()) {
            return false;
        }
        list($platform, $apiusername, $apipassword) = self::get_connection_vars();
        self::trace("Processing Online Activities");
        try {
            $hasnext = true; // Initialise to for multiple pages.
            while ($hasnext) {
                $hasnext = false; // Avoid infinite loop by default.
                // Get sync information.
                $syncinfo = self::get_collection_sync_info('onlineactivities');
                if (!$syncinfo) {
                    self::trace('No matching sync instance');
                    break;
                }
                if (!self::can_pull($syncinfo, $manualoverride)) {
                    break;
                }
                // Setup RequestUri for getting Events.
                $requesturi = new RequestUri();
                $requesturi->setHost($platform);
                $requesturi->setResourcePath('onlineactivities/');
                $requesturi->addExpand('OnlineActivity/EventTemplate');
                $options = array();
                $options['auth'] = array(
                    $apiusername,
                    $apipassword
                );
                $request = new \enrol_arlo\request\collection_request($syncinfo, $requesturi, array(), null, $options);
                $response = $request->execute();
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
                        $record = self::process_onlineactivity($onlineactivity);
                        $latestmodified = $onlineactivity->LastModifiedDateTime;
                        $syncinfo->latestsourcemodified = $latestmodified;
                    }
                    $hasnext = (bool) $collection->hasNext();
                    self::update_collection_sync_info($syncinfo, $hasnext);
                }
            }
        } catch (\Exception $exception) {
            $record                 = new \stdClass();
            $record->timelogged     = time();
            $record->message        = $exception->getMessage();
            $DB->insert_record('enrol_arlo_applicationlog', $record);
            self::trace('Error processing Online Activities');
            return false;
        }
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        self::trace("Execution took {$difftime} seconds");
        return true;
    }

    public function process_templates($manualoverride = false) {
        global $DB;
        $timestart = microtime();
        if (!self::api_callable()) {
            return false;
        }
        list($platform, $apiusername, $apipassword) = self::get_connection_vars();
        self::trace("Processing Event Templates");
        try {
            $hasnext = true; // Initialise to for multiple pages.
            while ($hasnext) {
                $hasnext = false; // Avoid infinite loop by default.
                // Get sync information.
                $syncinfo = self::get_collection_sync_info('eventtemplates');
                if (!$syncinfo) {
                    self::trace('No matching sync instance');
                    break;
                }
                if (!self::can_pull($syncinfo, $manualoverride)) {
                    break;
                }
                // Setup RequestUri for getting Templates.
                $requesturi = new RequestUri();
                $requesturi->setHost($platform);
                $requesturi->setResourcePath('eventtemplates/');
                $requesturi->addExpand('EventTemplate');
                $options = array();
                $options['auth'] = array(
                    $apiusername,
                    $apipassword
                );
                $request = new \enrol_arlo\request\collection_request($syncinfo, $requesturi, array(), null, $options);
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
                        $record = self::process_template($template);
                        $latestmodified = $template->LastModifiedDateTime;
                        $syncinfo->latestsourcemodified = $latestmodified;
                    }
                    $hasnext = (bool) $collection->hasNext();
                    self::update_collection_sync_info($syncinfo, $hasnext);
                }
            }
        } catch (\Exception $exception) {
            $record                 = new \stdClass();
            $record->timelogged     = time();
            $record->message        = $exception->getMessage();
            $DB->insert_record('enrol_arlo_applicationlog', $record);
            self::trace('Error processing Event Templates');
            return false;
        }
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        self::trace("Execution took {$difftime} seconds");
        return true;
    }

    private function deserialize_response_body(Response $response) {
        // Incorrect content-type.
        $contenttype = $response->getHeaderLine('content-type');
        if (strpos($contenttype, 'application/xml') === false) {
            throw new invalidcontent_exception(
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

    public function process_event(Event $event) {
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

    public function process_template(EventTemplate $template) {
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

    public function process_onlineactivity(OnlineActivity $onlineactivity) {
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
     * Helper method. Return connection vars for client.
     *
     * @return array
     */
    protected function get_connection_vars() {
        $platform       = self::$plugin->get_config('platform');
        $apiusername    = self::$plugin->get_config('apiusername');
        $apipassword    = self::$plugin->get_config('apipassword');
        return array($platform, $apiusername, $apipassword);
    }

    /**
     * Output a progress message.
     *
     * @param $message the message to output.
     * @param int $depth indent depth for this message.
     */
    private function trace($message, $depth = 0) {
        self::$trace->output($message, $depth);
    }
}
