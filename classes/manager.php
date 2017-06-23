<?php

namespace enrol_arlo;

use enrol_arlo\Arlo\AuthAPI\Client;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\Arlo\AuthAPI\Filter;
use enrol_arlo\Arlo\AuthAPI\XmlDeserializer;
use enrol_arlo\Arlo\AuthAPI\Resource\ApiException;
use GuzzleHttp\Psr7\Response;

class manager {
    private $platform;
    private $apiusername;
    private $apipassword;


    private $lastresponsestatus;
    private $lastresponsebody;


    public function __construct($platform = null,
                                $apiusername = null,
                                $apipassword = null) {

        $this->platform = $platform;
        $this->apiusername = $apiusername;
        $this->apipassword = $apipassword;
    }


    public function get_templates() {

        // Can run? Check API status.
        //self::check_apistatus();

        $timestart = microtime();

        try {
            $client = new Client($this->platform, $this->apiusername, $this->apipassword);
            // Setup RequestUri for getting Templates.
            $requesturi = new RequestUri();
            $requesturi->setHost($this->platform);
            $requesturi->setResourcePath('eventtemplates/');
            $requesturi->setPagingTop(5);

            $log = array(
                'timelogged' => $timestart,
                'platform' => $this->platform,
                'uri' => (string) $requesturi
            );
            $response = $client->request('GET', $requesturi);
            $template = self::parse_response($response);
            if (!$template) {

            }

            if (self::response_ok($response)) {

            } else {

            }

        } catch (\Exception $e) {
            mtrace($e->getMessage());
        }
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        mtrace("Execution took {$difftime} seconds");
    }

    /**
     * @param $timelogged
     * @param $uri
     * @param $status
     * @param string $info
     */
    private function log($timelogged, $uri, $status, $info = '') {
        global $DB;

        $item = new \stdClass();
        $item->timelogged = $timelogged;
        $item->uri = $uri;
        $item->status = $status;
        if ($info != '') {
            $item->info = (string) $info;
        }
        $DB->insert_record('enrol_arlo_webservicelog', $item);
    }


    private function response_ok(Response $response) {
        global $CFG;
        $status = (int) $response->getStatusCode();
        plugin_config::set('apistatus', $status);
        // Incorrect content-type.
        $contenttype = $response->getHeaderLine('content-type');
        if (strpos($contenttype, 'application/xml') === false) {
            self::alert('error_incorrectcontenttype' , array('contenttype' => $contenttype));
            return false;
        }
        // Handle HTTP Status errors.
        if ($status >= 400 && $status < 599) {
            $exception = self::parse_response($response);
            $exceptioncode = '';
            $exceptionmessage = '';
            if ($exception instanceof ApiException) {
                $exceptioncode = $exception->Code;
                $exceptionmessage = $exception->Message;
            }
            // Custom 401 Unauthorized, 403 Forbidden messages.
            if ($status == 401 || $status == 403) {
                $params = array('url' => $CFG->wwwroot . '/admin/settings.php?section=enrolsettingsarlo');
                self::alert('error_' . $status, $params);
                return false;
            } else {
                $params = array(
                    'status' => $status,
                    'reason' => $response->getReasonPhrase(),
                    'exceptioncode' => $exceptioncode,
                    'exceptionmessage' => $exceptionmessage
                );
                self::alert('error_xxx', $params);
                return false;
            }
        }
        return true;
    }

    private function parse_response(Response $response) {
        $deserializer = new XmlDeserializer('\enrol_arlo\Arlo\AuthAPI\Resource\\');
        $stream = $response->getBody();
        $body = $stream->getContents();
        $this->lastresponsestatus = (int) $response->getStatusCode();
        $this->lastresponsebody = $body;
        if ($stream->eof()) $stream->rewind(); // Rewind stream.
        return $deserializer->deserialize($body);
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