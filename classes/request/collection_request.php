<?php

namespace enrol_arlo\request;


use stdClass;
use enrol_arlo\alert;
use enrol_arlo\manager;
use enrol_arlo\Arlo\AuthAPI\Client;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\Arlo\AuthAPI\Filter;
use GuzzleHttp\Psr7\Response;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\RequestException;

class collection_request extends abstract_request {
    public function execute() {
        global $CFG;
        try {
            $requesturi = $this->requesturi;
            $schedule = $this->schedule;
            /// Set default order by if empty.
            if (empty($requesturi->getOrderBy())) {
                $requesturi->setOrderBy('LastModifiedDateTime ASC');
            }
            // Set default filter if empty.
            if (empty($requesturi->getFilters())) {
                // Set latest modified date if not set. First pull.
                $latestmodified = $schedule->latestsourcemodified;
                if (empty($latestmodified)) {
                    $servertimezone = \core_date::get_server_timezone();
                    $tz = new \DateTimeZone($servertimezone);
                    $date = \DateTime::createFromFormat('U', 0, $tz);
                    $latestmodified = $date->format(DATE_ISO8601);
                }
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
            }

            $client = new Client();
            $response = $client->request('GET', $requesturi, $this->headers, $this->body, $this->options);
            $status = $response->getStatusCode();
            self::log($requesturi->getHost(), $requesturi->output(), $status);
            // Update API status vars.
            set_config('apistatus', $status, 'enrol_arlo');
            set_config('apilastrequested', time(), 'enrol_arlo');
            set_config('apierrorcount', 0, 'enrol_arlo');
            return $response;
        } catch (RequestException $exception) {
            $errorcount = (int) $schedule->errorcount;
            $schedule->errorcount = ++$errorcount;
            $schedule->lasterror = $exception->getMessage();
            manager::update_scheduling_information($schedule);
            $apierrorcount = (int) get_config('enrol_arlo', 'apierrorcount');
            $status = $exception->getCode();
            $uri = (string) $exception->getRequest()->getUri();
            $extra = $exception->getMessage();
            // Update API status vars.
            set_config('apistatus', $status, 'enrol_arlo');
            set_config('apilastrequested', time(), 'enrol_arlo');
            set_config('apierrorcount', ++$apierrorcount, 'enrol_arlo');
            // Log the request.
            self::log($requesturi->getHost(), $uri, $status, $extra);
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
}