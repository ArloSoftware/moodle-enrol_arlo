<?php

namespace enrol_arlo;

use enrol_arlo\Arlo\AuthAPI\Client;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\Arlo\AuthAPI\Filter;
use GuzzleHttp\Psr7\Response;

class manager {


    public function get_templates() {

        // Can run? Check API status.

        $timestart = microtime();

        $platformname = plugin_config::get('platformname');
        $apiusername  = plugin_config::get('apiusername');
        $apipassword  = plugin_config::get('apipassword');
        $apipassword = '1';

        try {

            $client = new Client($platformname, $apiusername, $apipassword);

            $requesturi = new RequestUri();
            $requesturi->setHost($platformname);
            $requesturi->setResourcePath('eventtemplates/');
            $requesturi->setPagingTop(5);

            $response = $client->request('GET', $requesturi);
            $this->response_ok($response);

        } catch (\Exception $e) {
            mtrace($e->getMessage());
        }
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        mtrace("Execution took {$difftime} seconds");
    }
    private function response_ok(Response $response) {

        $status = (int) $response->getStatusCode();
        // Handle client side errors
        if ($status = 400 || $status < 499) {
            // Handle 401 Unauthorized, 403 Forbidden.

        }
        // Handle server side errors
        if ($status = 500 || $status < 599) {

        }

    }
}