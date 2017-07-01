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
            print_object($record);
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
        return $DB->get_field_sql($sql, array('platform' => $this->platform));
    }

    public static function update_api_status($status) {
        if (!is_int($status)) {
            throw new \Exception('API Status must integer.');
        }
        self::get_plugin()->set_config('apistatus', $status);
    }

    public function fetch_events() {
        global $DB;

        // Latest modified DateTime - High water mark.
        $latestmodified = null;

        try {
            $hasnext = true; // Initialise to true for first fetch.
            while ($hasnext) {
                $hasnext = false;
                if (is_null($latestmodified)) {
                    $latestmodified = self::get_latestmodified_field('enrol_arlo_event');
                }
                // Setup RequestUri for getting Templates.
                $requesturi = new RequestUri();
                $requesturi->setHost($this->platform);
                $requesturi->setOrderBy('LastModifiedDateTime ASC'); // Important.
                $requesturi->setResourcePath('events/');
                $requesturi->addExpand('Event/EventTemplate');
                $createdfilter = Filter::create()
                    ->setResourceField('CreatedDateTime')
                    ->setOperator('gt')
                    ->setDateValue(date::create($latestmodified));
                $requesturi->addFilter($createdfilter);
                $modifiedfilter = Filter::create()
                    ->setResourceField('LastModifiedDateTime')
                    ->setOperator('gt')
                    ->setDateValue(date::create($latestmodified));
                $requesturi->addFilter($modifiedfilter);
                // Get HTTP client.
                $client = new Client($this->platform, $this->apiusername, $this->apipassword);
                // Start the clock.
                $timestart = microtime();
                self::trace(sprintf('Fetching Events modified after: %s',
                    date::create($latestmodified)->format(DATE_ISO8601)));
                // Launch HTTP client request to API and get response.
                $response = $client->request('GET', $requesturi);
                // Set API status.
                self::update_api_status((int) $response->getStatusCode());
                // Log the request uri and response status.
                $logitem = self::log(time(), (string) $requesturi, (int) $response->getStatusCode());
                // Parse response body.
                $collection = self::parse_response($response);
                if (!$collection) {
                    self::trace("No new or updated resources found.");
                } else {
                    foreach ($collection as $event) {
                        $record = self::process_event($event);
                        $latestmodified = $event->LastModifiedDateTime;
                    }
                }
                $hasnext = (bool) $collection->hasNext();
            }
        } catch (\Exception $e) {
            $handled = self::handle_request_exception($e);
            if (!$handled) {
                // TODO.
                print_object($e);
            }
            return false;
        }
        return true;
    }

    public function fetch_onlineactivities() {
        global $DB;

        // self::check_apistatus();

        // Latest modified DateTime - High water mark.
        $latestmodified = null;

        try {
            $hasnext = true; // Initialise to true for first fetch.
            while ($hasnext) {
                $hasnext = false;
                if (is_null($latestmodified)) {
                    $latestmodified = self::get_latestmodified_field('enrol_arlo_onlineactivity');
                }
                // Setup RequestUri for getting Templates.
                $requesturi = new RequestUri();
                $requesturi->setHost($this->platform);
                $requesturi->setOrderBy('LastModifiedDateTime ASC'); // Important.
                $requesturi->setResourcePath('onlineactivities/');
                $requesturi->addExpand('OnlineActivity/EventTemplate');
                $createdfilter = Filter::create()
                    ->setResourceField('CreatedDateTime')
                    ->setOperator('gt')
                    ->setDateValue(date::create($latestmodified));
                $requesturi->addFilter($createdfilter);
                $modifiedfilter = Filter::create()
                    ->setResourceField('LastModifiedDateTime')
                    ->setOperator('gt')
                    ->setDateValue(date::create($latestmodified));
                $requesturi->addFilter($modifiedfilter);
                // Get HTTP client.
                $client = new Client($this->platform, $this->apiusername, $this->apipassword);
                // Start the clock.
                $timestart = microtime();
                self::trace(sprintf('Fetching OnlineActivities modified after: %s',
                    date::create($latestmodified)->format(DATE_ISO8601)));
                // Launch HTTP client request to API and get response.
                $response = $client->request('GET', $requesturi);
                // Set API status.
                self::update_api_status((int) $response->getStatusCode());
                // Log the request uri and response status.
                $logitem = self::log(time(), (string) $requesturi, (int) $response->getStatusCode());
                // Parse response body.
                $collection = self::parse_response($response);
                if (!$collection) {
                    self::trace("No new or updated resources found.");
                } else {
                    foreach ($collection as $onlineactivity) {
                        $record = self::process_onlineactivity($onlineactivity);
                        $latestmodified = $onlineactivity->LastModifiedDateTime;
                    }
                }
                $hasnext = (bool) $collection->hasNext();
            }
        } catch (\Exception $e) {
            $handled = self::handle_request_exception($e);
            if (!$handled) {
                // TODO.
                print_object($e);
            }
            return false;
        }
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        self::trace("Execution took {$difftime} seconds");
        return true;
    }

    public function fetch_templates() {
        global $DB;

        // self::check_apistatus();

        // Latest modified DateTime - High water mark.
        $latestmodified = null;

        try {
            $hasnext = true; // Initialise to true for first fetch.
            while ($hasnext) {
                $hasnext = false;
                if (is_null($latestmodified)) {
                    $latestmodified = self::get_latestmodified_field('enrol_arlo_template');
                }
                // Setup RequestUri for getting Templates.
                $requesturi = new RequestUri();
                $requesturi->setHost($this->platform);
                $requesturi->setOrderBy('LastModifiedDateTime ASC'); // Important.
                $requesturi->setResourcePath('eventtemplates/');
                $requesturi->addExpand('EventTemplate');
                $createdfilter = Filter::create()
                    ->setResourceField('CreatedDateTime')
                    ->setOperator('gt')
                    ->setDateValue(date::create($latestmodified));
                $requesturi->addFilter($createdfilter);
                $modifiedfilter = Filter::create()
                    ->setResourceField('LastModifiedDateTime')
                    ->setOperator('gt')
                    ->setDateValue(date::create($latestmodified));
                $requesturi->addFilter($modifiedfilter);
                // Get HTTP client.
                $client = new Client($this->platform, $this->apiusername, $this->apipassword);
                // Start the clock.
                $timestart = microtime();
                self::trace(sprintf('Fetching EventTemplates modified after: %s',
                    date::create($latestmodified)->format(DATE_ISO8601)));
                // Launch HTTP client request to API and get response.
                $response = $client->request('GET', $requesturi);
                // Set API status.
                self::update_api_status((int) $response->getStatusCode());
                // Log the request uri and response status.
                $logitem = self::log(time(), (string) $requesturi, (int) $response->getStatusCode());
                // Parse response body.
                $collection = self::parse_response($response);
                if (!$collection) {
                    self::trace("No new or updated resources found.");
                } else {
                    foreach ($collection as $template) {
                        $record = self::process_template($template);
                        $latestmodified = $template->LastModifiedDateTime;
                    }
                }
                $hasnext = (bool) $collection->hasNext();
            }
        } catch (\Exception $e) {
            $handled = self::handle_request_exception($e);
            if (!$handled) {
                // TODO.
                print_object($e);
            }
            return false;
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

    public function process_event(Event $event) {
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
            'platform'      => $this->platform,
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
            'platform'      => $this->platform,
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
    private function log($timelogged, $uri, $status, $info = '') {
        global $DB;

        $item = new \stdClass();
        $item->timelogged = $timelogged;
        $item->platform = $this->platform;
        $item->uri = $uri;
        $item->status = $status;
        if ($info != '') {
            $item->info = (string) $info;
        }
        $item->id = $DB->insert_record('enrol_arlo_webservicelog', $item);
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
        if (!plugin_config::get('alertsiteadmins')) {
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
