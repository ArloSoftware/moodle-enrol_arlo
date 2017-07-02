<?php

namespace enrol_arlo;

use stdClass;
use enrol_arlo\Arlo\AuthAPI\Client;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\Arlo\AuthAPI\Filter;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;

class collection_request {
    const REQUEST_INTERVAL      = 900;       // 15 Minutes (In seconds).
    const REQUEST_EXTENSION     = 259200;    // 72 Hours (In seconds).
    const MAXIMUM_ERROR_COUNT   = 20;

    private static $plugin;
    private $table = 'enrol_arlo_collection';
    private $record;
    private $requesturi;
    private $manualoverride = false;
    private $retryallowed = true;

    public function __construct(stdClass $record, RequestUri $requesturi) {
        self::$plugin = enrol_get_plugin('arlo');
        self::load_record($record, self::get_requiredfields());
        self::set_requesturi($requesturi);
    }

    private function executable() {
        if ($this->manualoverride) {
            return true;
        }
        $nextpulltime = $this->record->nextpulltime;
        // Return if next pull time hasn't passed current time.
        if ($timestart < ($nextpulltime + self::REQUEST_INTERVAL)) {
            return false;
        }
        $endpulltime = $this->record->endpulltime;
        // Return if end pull time has past.
        if (!empty($endpulltime) && $timestart > ($endpulltime + self::REQUEST_EXTENSION)) {
            return false;
        }
        return true;
    }

    public function execute() {
        try {
            // Can execute?
            if (!self::executable()) {
                return false;
            }

            // Timestamp.
            $timestart = time();

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
        } catch (\Exception $e) {

        }
        return $response;
    }

    private function get_connection_vars() {
        $platform       = self::$plugin->get_config('platform');
        $apiusername    = self::$plugin->get_config('apiusername');
        $apipassword    = self::$plugin->get_config('apipassword');
        return array($platform, $apiusername, $apipassword);
    }

    public function get_requiredfields() {
        $requiredfields = array(
            'id', 'type', 'latestsourcemodified', 'nextpulltime', 'endpulltime', 'lastpulltime', 'lasterror', 'errorcount'
        );
        return $requiredfields;
    }

    private function load_record(stdClass $record, array $requiredfields) {
        $recordfields = array_keys(get_object_vars($record));
        foreach ($requiredfields as $requiredfield) {
            if (!in_array($requiredfield, $recordfields)) {
                throw new \moodle_exception("Required {$requiredfield} not found.");
            }
        }
        $this->record = $record;
    }

    public function retry_allowed() {}

    public function set_manualoverride() {}

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
