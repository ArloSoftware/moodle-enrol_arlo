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
        global $CFG;
        $status = (int) $response->getStatusCode();
        // Handle client side errors
        if ($status >= 400 || $status < 499) {
            // Handle 401 Unauthorized, 403 Forbidden.
            $params = array('url' => $CFG->wwwroot . '/admin/settings.php?section=enrolsettingsarlo', 'name' => 'wtf');
            self::alert('error_401', $params);

        }
        // Handle server side errors
        if ($status >= 500 || $status < 599) {

        }

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
}