<?php

namespace enrol_arlo\request;

use stdClass;
use enrol_arlo\alert;
use enrol_arlo\Arlo\AuthAPI\Client;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\Arlo\AuthAPI\Filter;
use GuzzleHttp\Psr7\Response;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;


abstract class abstract_request {
    protected $tablename;
    protected $schedule ;
    protected $requesturi;
    protected $headers;
    protected $body;
    protected $options;

    public function __construct(stdClass $schedule, RequestUri $requesturi, array $headers = [], $body = null, array $options = []) {
        self::load_schedule($schedule, self::get_requiredfields());
        $this->requesturi   = $requesturi;
        $this->headers      = $headers;
        $this->body         = $body;
        $this->options      = $options;
    }

    abstract public function execute();

    /**
     * Require synchronization control fields.
     *
     * @return array
     */
    public function get_requiredfields() {
        $requiredfields = array(
             'id', 'enrolid', 'platform', 'lasterror', 'errorcount'
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
    protected function load_schedule(stdClass $schedule, array $requiredfields) {
        $recordfields = array_keys(get_object_vars($schedule));
        foreach ($requiredfields as $requiredfield) {
            if (!in_array($requiredfield, $recordfields)) {
                throw new \moodle_exception("Required {$requiredfield} not found.");
            }
        }
        $this->schedule = $schedule;
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

        $record             = new stdClass();
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
}
