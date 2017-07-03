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

class instance_request extends collection_request {
    private $table = 'enrol_arlo_instance';
    public function __construct(stdClass $record, RequestUri $requesturi, $manualoverride = false) {
        parent::__construct($record, $requesturi, $manualoverride);
        $requiredfields = array_merge(self::get_requiredfields(), array('enrolid', 'sourceid', 'sourceguid'));
        self::load_record($record, $requiredfields);
    }
}