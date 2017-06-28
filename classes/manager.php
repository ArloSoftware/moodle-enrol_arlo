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


class manager {
    private $platform;
    private $apiusername;
    private $apipassword;
    private $trace;


    public function __construct($platform = null,
                                $apiusername = null,
                                $apipassword = null,
                                \progress_trace $trace = null) {

        $this->platform = $platform;
        $this->apiusername = $apiusername;
        $this->apipassword = $apipassword;
        if (is_null($trace)) {
            $this->trace = new \null_progress_trace();
        } else {
            $this->trace = $trace;
        }
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
        plugin_config::set('apistatus', $status);
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
            self::handle_exception($e, $logitem);
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
                $requesturi->addExpand('EventTemplate');
                $createdfilter = Filter::create()
                    ->setResourceField('CreatedDateTime')
                    ->setOperator('gt')
                    ->setDateValue(date::create($latestmodified), true);
                $requesturi->addFilter($createdfilter);
                $modifiedfilter = Filter::create()
                    ->setResourceField('LastModifiedDateTime')
                    ->setOperator('gt')
                    ->setDateValue(date::create($latestmodified), true);
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
                        $record = self::process_template($onlineactivity);
                        $latestmodified = $onlineactivity->LastModifiedDateTime;
                    }
                }
                $hasnext = (bool) $collection->hasNext();
            }
        } catch (\Exception $e) {
            self::handle_exception($e, $logitem);
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
            self::handle_exception($e, $logitem);
            return false;
        }
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        self::trace("Execution took {$difftime} seconds");
        return true;
    }

    /**
     * @param \Exception $exception
     * @param null $logitem
     * @return bool
     */
    private function handle_exception(\Exception $exception, $logitem = null) {
        global $DB;
        $code = $exception->getCode();
        $message = $exception->getMessage();

        // Add log extra info.
        if (!is_null($logitem)) {
            $DB->set_field('enrol_arlo_webservicelog', 'info', $message, array('id' => $logitem->id));
        }
        if (method_exists($exception, 'get_string_dentifier')) {
            $stringidentifier = $exception->get_string_dentifier();
        }
        if (method_exists($exception, 'get_parameters')) {
            $stringparameters = $exception->get_parameters();
        }
        if (method_exists($exception, 'get_api_exception')) {
            $apiexception = $exception->get_api_exception();
        }
        // Client exception send a message.
        if ($exception instanceof client_exception) {
            self::alert($stringidentifier, $stringparameters);
        } else {
            print_object($exception);die; // TODO - Handle
        }
        return false;
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
        $record->sourceid       = $onlineactivity->TemplateID;
        $record->sourceguid     = $onlineactivity->UniqueIdentifier;
        $record->name           = $onlineactivity->Name;
        $record->code           = $onlineactivity->Code;
        $record->contenturi     = $onlineactivity->ContentUri;
        $record->sourcestatus   = $onlineactivity->Status;
        $record->sourcecreated  = $onlineactivity->CreatedDateTime;
        $record->sourcemodified = $onlineactivity->LastModifiedDateTime;
        $record->modified       = time();

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
            $DB->update_record('enrol_arlo_template', $record);
            self::trace(sprintf('Updated: %s', $record->name));
        }
        return $record;
    }

    private function parse_response(Response $response) {
        global $CFG;
        $resourceclass = false;
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
        // Throw exception if empty response body.
        if (empty($contents)) {
            throw new server_exception(
                $reason,
                $status,
                'error_emptyresponse');
        }
        $resourceclass = $deserializer->deserialize($contents);
        // Get api exception information if available.
        $apiexception = array();
        if ($resourceclass instanceof ApiException) {
            $apiexception['code'] = $resourceclass->Code;
            $apiexception['message'] = $resourceclass->Message;
        }
        // Client side.
        if ($status >= 400 && $status < 499) {
            $identifier = 'error_4xx';
            $params = array();
            // Custom 401 Unauthorized, 403 Forbidden messages.
            if ($status == 401 || $status == 403) {
                $identifier = 'error_' . $status;
                $params = array('url' => $CFG->wwwroot . '/admin/settings.php?section=enrolsettingsarlo');
            }
            throw new client_exception(
                $reason,
                $status,
                $identifier,
                $params,
                $apiexception);
        // Server side.
        } else if ($status >= 500 && $status < 599) {
            $identifier = 'error_5xx';
            $params = array();
            throw new server_exception(
                $reason,
                $status,
                $identifier,
                $params,
                $apiexception);
        }
        // If everything went OK a resource class will be returned.
        return $resourceclass;
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
