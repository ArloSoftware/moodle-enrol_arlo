<?php

namespace enrol_arlo;

use enrol_arlo\Arlo\AuthAPI\Client;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\Arlo\AuthAPI\Filter;
use enrol_arlo\Arlo\AuthAPI\XmlDeserializer;
use enrol_arlo\Arlo\AuthAPI\Resource\AbstractCollection;
use enrol_arlo\Arlo\AuthAPI\Resource\AbstractResource;
use enrol_arlo\Arlo\AuthAPI\Resource\ApiException;
use enrol_arlo\Arlo\AuthAPI\Resource\Event;
use enrol_arlo\Arlo\AuthAPI\Resource\EventTemplate;
use enrol_arlo\Arlo\AuthAPI\Resource\OnlineActivity;
use enrol_arlo\exception\client_exception;
use enrol_arlo\exception\server_exception;
use enrol_arlo\utility\date;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;


class manager {
    /** @var $plugin enrolment plugin instance. */
    private static $plugin;
    private $platform;
    private $apiusername;
    private $apipassword;
    private $trace;


    public function __construct($platform = null,
                                $apiusername = null,
                                $apipassword = null,
                                \progress_trace $trace = null) {
        // Check we have all config.
        if (empty($platform)) {
            throw new \coding_exception('Empty platform config');
        }
        $this->platform = $platform;
        if (empty($apiusername)) {
            throw new \coding_exception('Empty apiusername config');
        }
        $this->apiusername = $apiusername;
        if (empty($apipassword)) {
            throw new \coding_exception('Empty apipassword config');
        }
        $this->apipassword = $apipassword;
        // Setup trace.
        if (is_null($trace)) {
            $this->trace = new \null_progress_trace();
        } else {
            $this->trace = $trace;
        }
        self::$plugin = enrol_get_plugin('arlo');
    }

    public function process_instances() {
        global $DB;
        $conditions = array(
            'enrol' => 'arlo',
            'status' => ENROL_INSTANCE_ENABLED,
            'platform' => $this->platform
        );
        $sql = "SELECT ai.* 
                  FROM {enrol} e
                  JOIN {enrol_arlo_instance} ai
                    ON ai.enrolid = e.id
                 WHERE e.enrol = :enrol 
                   AND e.status = :status
                   AND ai.platform = :platform
              ORDER BY ai.nextpulltime";

        $records = $DB->get_records_sql($sql, $conditions);
        foreach ($records as $record) {
            self::fetch_instance_response($record);
        }

    }


    /**
     * Get the Arlo enrolment plugin.
     *
     * @return \enrol_plugin
     */
    private static function get_plugin() {
        if (is_null(self::$plugin)) {
            self::$plugin = enrol_get_plugin('arlo');
        }
        return self::$plugin;
    }

    private function get_latestmodified_field($table) {
        global $DB;
        $sql = "SELECT MAX(t.sourcemodified) AS latestmodified 
                  FROM {{$table}} t
                 WHERE t.platform = :platform";
        $latestmodified = $DB->get_field_sql($sql, array('platform' => $this->platform));
        if (empty($latestmodified)) {
            $servertimezone = \core_date::get_server_timezone();
            $tz = new \DateTimeZone($servertimezone);
            $date = \DateTime::createFromFormat('U', 0, $tz);
            $latestmodified = $date->format(DATE_ISO8601);
        }
        return $latestmodified;
    }

    public static function update_api_status($status) {
        if (!is_int($status)) {
            throw new \Exception('API Status must integer.');
        }
        self::get_plugin()->set_config('apistatus', $status);
    }

    public function update_events() {
        $timestart = microtime();
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
                $request = new collection_request($syncinfo, $requesturi);
                if (!$request->executable()) {
                    self::trace('Cannot execute request due to timing or API status');
                } else {
                    $response = $request->execute();
                    $collection = self::deserialize_response_body($response);
                    // Any returned.
                    if (!$collection) {
                        self::update_collection_sync_info($syncinfo, $hasnext);
                        self::trace("No new or updated resources found.");
                    } else {
                        foreach ($collection as $event) {
                            $record = self::update_event($event);
                            $latestmodified = $event->LastModifiedDateTime;
                        }
                        $hasnext = (bool) $collection->hasNext();
                        $syncinfo->latestsourcemodified = $latestmodified;
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

    public function update_onlineactivies() {
        $timestart = microtime();
        try {
            $hasnext = true; // Initialise to for multiple pages.
            while ($hasnext) {
                $hasnext = false; // Avoid infinite loop by default.
                // Get sync information.
                $syncinfo = self::get_collection_sync_info('onlineactivities');
                // Setup RequestUri for getting Events.
                $requesturi = new RequestUri();
                $requesturi->setResourcePath('onlineactivities/');
                $requesturi->addExpand('Event/EventTemplate');
                $request = new collection_request($syncinfo, $requesturi);
                if (!$request->executable()) {
                    self::trace('Cannot execute request due to timing or API status');
                } else {
                    $response = $request->execute();
                    $collection = self::deserialize_response_body($response);
                    // Any returned.
                    if (!empty($collection)) {
                        self::update_collection_sync_info($syncinfo, $hasnext);
                        self::trace("No new or updated resources found.");
                    } else {
                        foreach ($collection as $onlineactivity) {
                            $record = self::update_onlineactivity($onlineactivity);
                            $latestmodified = $onlineactivity->LastModifiedDateTime;
                        }
                        $hasnext = (bool) $collection->hasNext();
                        $syncinfo->latestsourcemodified = $latestmodified;
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

    public static function get_collection_sync_info($collection) {
        global $DB;
        $conditions = array('type' => $collection);
        $record = $DB->get_record('enrol_arlo_collection', $conditions);
        if (!$record) {
            $record = new \stdClass();
            $record->platform = self::$plugin->get_config('platform');
            $record->type = $collection;
            $record->latestsourcemodified = '';
            $record->nextpulltime = 0;
            $record->endpulltime = 0;
            $record->lastpulltime = 0;
            $record->lasterror = '';
            $record->errorcount = 0;
            $record->id = $DB->insert_record('enrol_arlo_collection', $record);
        }
        return $record;
    }

    public static function update_collection_sync_info(\stdClass $record, $hasnext= false) {
        global $DB;

        $record->lastpulltime = time();
        if (!$hasnext) {
            $record->nextpulltime = time();
        }
        $DB->update_record('enrol_arlo_collection', $record);
        return $record;
    }

    public function update_templates() {
        $timestart = microtime();
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
                $request = new collection_request($syncinfo, $requesturi);
                if (!$request->executable()) {
                    self::trace('Cannot execute request due to timing or API status');
                } else {
                    $response = $request->execute();
                    $collection = self::deserialize_response_body($response);
                    // Any returned.
                    if (!$collection) {
                        self::update_collection_sync_info($syncinfo, $hasnext);
                        self::trace("No new or updated resources found.");
                    } else {
                        foreach ($collection as $template) {
                            $record = self::update_template($template);
                            $latestmodified = $template->LastModifiedDateTime;
                        }
                        $hasnext = (bool) $collection->hasNext();
                        $syncinfo->latestsourcemodified = $latestmodified;
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

    /**
     * Handle request exceptions.
     *
     * @param \Exception $exception
     * @return bool
     */
    private function handle_request_exception(\Exception $exception) {
        global $CFG;
        $timelogged = time();
        if ($exception instanceof ClientException) {
            $status = $exception->getCode();
            $uri = (string) $exception->getRequest()->getUri();
            $message = $exception->getMessage();
            // Set status.
            self::update_api_status($status);
            // Log.
            self::log($timelogged, $uri, $status, $message);
            // Alert.
            if ($status == 401 || $status == 403) {
                $identifier = 'error_' . $status;
                $params = array('url' => $CFG->wwwroot . '/admin/settings.php?section=enrolsettingsarlo');
                self::alert($identifier, $params);
            }
            return false;
        }
        if ($exception instanceof RequestException) {
            $status = $exception->getCode();
            $uri = (string) $exception->getRequest()->getUri();
            $message = $exception->getMessage();
            // Set status.
            self::update_api_status($status);
            // Log.
            self::log($timelogged, $uri, $status, $message);
            return true;
        }
        return false;
    }

    private function deserialize_response_body(Response $response) {
        return self::parse_response($response);
    }

    /**
     * Parse response. Check content-type.
     *
     * @param Response $response
     * @return mixed
     * @throws server_exception
     */
    private function parse_response(Response $response) {
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

        $record                 = new \stdClass();
        $record->platform       = $this->platform;
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
            'platform'      => self::$plugin->get_config('platform'),
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

        $record = new \stdClass();
        $record->platform       = $this->platform;
        $record->sourceid       = $template->TemplateID;
        $record->sourceguid     = $template->UniqueIdentifier;
        $record->name           = $template->Name;
        $record->code           = $template->Code;
        $record->sourcestatus   = $template->Status;
        $record->sourcecreated  = $template->CreatedDateTime;
        $record->sourcemodified = $template->LastModifiedDateTime;
        $record->modified       = time();

        $params = array(
            'platform'      => self::$plugin->get_config('platform'),
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
        $record = new \stdClass();
        $record->platform       = $this->platform;
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
            'platform'      => $this->platform,
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
     * @param $timelogged
     * @param $uri
     * @param $status
     * @param string $info
     * @return \stdClass
     */
    private function log($timelogged, $uri, $status, $extra = '') {
        global $DB;

        $item = new \stdClass();
        $item->timelogged = time();
        $item->platform = $this->platform;
        $item->uri = $uri;
        $item->status = $status;
        if ($extra != '') {
            $item->extra = (string) $extra;
        }
        $item->id = $DB->insert_record('enrol_arlo_requestlog', $item);
        return $item;
    }

    /**
     * Send admin alert on integration status.
     *
     * Each alert must define following locale lang strings based on passed in identifier:
     *
     *  - $identifier_subject
     *  - $identifier_small
     *  - $identifier_full
     *  - $identifier_full_html
     *
     * An associative array of can be passed to provide more context specific information
     * to the lang string.
     *
     * Example:
     *          $params['configurl' => 'someurl', 'level' => 'warning'];
     *
     * @param $identifier
     * @param array $params
     * @throws \Exception
     */
    private static function alert($identifier, $params = array()) {
        // Check admin alerts are enabled.
        if (!self::get_plugin()->get_config('alertsiteadmins')) {
            return;
        }
        if (empty($identifier) && !is_string($identifier)) {
            throw new \Exception('Alert identifier is empty or not a string.');
        }
        // Setup message.
        $message = new \core\message\message();
        $message->component = 'enrol_arlo';
        $message->name = 'alerts';
        $message->notification = 1;
        $message->userfrom = \core_user::get_noreply_user();
        $message->subject = get_string($identifier . '_subject', 'enrol_arlo', $params);
        $message->fullmessage = get_string($identifier . '_full', 'enrol_arlo', $params);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml = get_string($identifier . '_full_html', 'enrol_arlo', $params);
        $message->smallmessage = get_string($identifier . '_small', 'enrol_arlo', $params);
        // Message each recipient.
        foreach (get_admins() as $admin) {
            $messagecopy = clone($message);
            $messagecopy->userto = $admin;
            message_send($messagecopy);
        }
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
