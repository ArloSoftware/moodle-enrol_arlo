<?php

namespace enrol_arlo;

use stdClass;
use enrol_arlo\alert;
use enrol_arlo\Arlo\AuthAPI\Client;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\Arlo\AuthAPI\Filter;
use GuzzleHttp\Psr7\Response;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;

class collection_request {
    const REQUEST_INTERVAL_SECONDS      = 900;       // 15 Minutes (In seconds).
    const REQUEST_EXTENSION_SECONDS     = 259200;    // 72 Hours (In seconds).
    const MAXIMUM_ERROR_COUNT           = 20;

    private static $plugin;
    private $table                      = 'enrol_arlo_collection';
    private $record;
    private $requesturi;
    private $manualoverride             = false; // Used for manual syncs.
    private $retryallowed               = false; // TODO not yet implemented.

    public function __construct(stdClass $record, RequestUri $requesturi, $manualoverride = false) {
        self::load_record($record, self::get_requiredfields());
        self::set_requesturi($requesturi);
        self::$plugin = enrol_get_plugin('arlo');
        $this->manualoverride = $manualoverride;
    }

    /**
     * Checks synchronization and returns if request can be executed.
     *
     * @return bool
     */
    public function executable() {
        $timestart = time();
        if ($this->manualoverride) {
            return true;
        }
        $nextpulltime = $this->record->nextpulltime;
        // Return if next pull time hasn't passed current time.
        if ($timestart < ($nextpulltime + self::REQUEST_INTERVAL_SECONDS)) {
            return false;
        }
        $endpulltime = $this->record->endpulltime;
        // Return if end pull time has past.
        if (!empty($endpulltime) && $timestart > ($endpulltime + self::REQUEST_EXTENSION_SECONDS)) {
            return false;
        }
        return true;
    }

    /**
     * Execute the request.
     *
     * @return bool|\Psr\Http\Message\ResponseInterface
     */
    public function execute() {
        global $CFG;
        try {
            list($platform, $apiusername, $apipassword) = self::get_connection_vars();
            $requesturi = $this->requesturi;

            // Set latest modified date if not set. First pull.
            $latestmodified = $this->record->latestsourcemodified;
            if (empty($latestmodified)) {
                $servertimezone = \core_date::get_server_timezone();
                $tz = new \DateTimeZone($servertimezone);
                $date = \DateTime::createFromFormat('U', 0, $tz);
                $latestmodified = $date->format(DATE_ISO8601);
            }
            // Set URI Host, OrderBy and Filters.
            $requesturi->setHost($platform);
            $requesturi->setOrderBy('LastModifiedDateTime ASC');
            $createdfilter = Filter::create()
                ->setResourceField('CreatedDateTime')
                ->setOperator('gt')
                ->setDateValue($latestmodified);
            $requesturi->addFilter($createdfilter);
            $modifiedfilter = Filter::create()
                ->setResourceField('LastModifiedDateTime')
                ->setOperator('gt')
                ->setDateValue($latestmodified);
            $requesturi->addFilter($modifiedfilter);
            // Initialize client and send request.
            $client = new Client($platform, $apiusername, $apipassword);
            $response = $client->request('GET', $requesturi);
            print_object($response);
            $status = $response->getStatusCode();
            self::log($platform, $requesturi->output(), $status);
            // Update API status vars.
            set_config('apistatus', $status, 'enrol_arlo');
            set_config('apilastrequested', time(), 'enrol_arlo');
            set_config('apierrorcount', 0, 'enrol_arlo');
            return $response;
        } catch (RequestException $exception) {
            $apierrorcount = (int) get_config('enrol_arlo', 'apierrorcount');
            $status = $exception->getCode();
            $uri = (string) $exception->getRequest()->getUri();
            $extra = $exception->getMessage();
            // Update API status vars.
            set_config('apistatus', $status, 'enrol_arlo');
            set_config('apilastrequested', time(), 'enrol_arlo');
            set_config('apierrorcount', ++$apierrorcount, 'enrol_arlo');
            // Log the request.
            self::log($platform, $uri, $status, $extra);
            // Alert.
            if ($status == 401 || $status == 403) {
                $params = array('url' => $CFG->wwwroot . '/admin/settings.php?section=enrolsettingsarlo');
                alert::create('error_invalidcredentials', $params)->send();
            }
            // Be nice return a empty response with http status set.
            $response = new Response();
            return $response->withStatus($status);
        }
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
     * Require synchronization control fields.
     *
     * @return array
     */
    public function get_requiredfields() {
        $requiredfields = array(
            'id', 'type', 'latestsourcemodified', 'nextpulltime', 'endpulltime', 'lastpulltime', 'lasterror', 'errorcount'
        );
        return $requiredfields;
    }

    /**
     * Load in synchronization control data.
     *
     * @param stdClass $record
     * @param array $requiredfields
     * @throws \moodle_exception
     */
    protected function load_record(stdClass $record, array $requiredfields) {
        $recordfields = array_keys(get_object_vars($record));
        foreach ($requiredfields as $requiredfield) {
            if (!in_array($requiredfield, $recordfields)) {
                throw new \moodle_exception("Required {$requiredfield} not found.");
            }
        }
        $this->record = $record;
    }

    /**
     * Log the web service request.
     *
     * @param $platform
     * @param $uri
     * @param $status
     * @param string $extra
     * @return stdClass
     */
    protected function log($platform, $uri, $status, $extra = '') {
        global $DB;

        $record             = new \stdClass();
        $record->timelogged = time();
        $record->platform   = $platform;
        $record->uri        = $uri;
        $record->status     = $status;
        if ($extra != '') {
            $record->extra  = (string) $extra;
        }
        $record->id = $DB->insert_record('enrol_arlo_requestlog', $record);
        return $record;
    }

    public function retry_allowed() {}

    /**
     *
     *
     * @param RequestUri $requesturi
     * @throws \moodle_exception
     */
    public function set_requesturi(RequestUri $requesturi) {
        if (empty($requesturi->getResourcePath())) {
            throw new \moodle_exception('URI Resourse Path cannot be empty.');
        }
        if (empty($requesturi->getExpands())) {
            throw new \moodle_exception('URI Expands cannot be empty.');
        }
        $this->requesturi = $requesturi;
    }

}
