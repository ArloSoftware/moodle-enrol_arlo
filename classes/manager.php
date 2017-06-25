<?php

namespace enrol_arlo;

use enrol_arlo\Arlo\AuthAPI\Client;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\Arlo\AuthAPI\Filter;
use enrol_arlo\Arlo\AuthAPI\XmlDeserializer;
use enrol_arlo\Arlo\AuthAPI\Resource\AbstractCollection;
use enrol_arlo\Arlo\AuthAPI\Resource\AbstractResource;
use enrol_arlo\Arlo\AuthAPI\Resource\ApiException;
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

    private function get_lastestmodified_field($table) {
        global $DB;
        $sql = "SELECT MAX(t.sourcemodified) AS latestmodified 
                  FROM {{$table}} t
                 WHERE t.platform = :platform";
        return (int) $DB->get_field_sql($sql, array('platform' => $this->platform));
    }

    public static function update_api_status($status) {
        if (!is_int($status)) {
            throw new \Exception('API Status must integer.');
        }
        plugin_config::set('apistatus', $status);
    }

    // Can run? Check API status.
    //self::check_apistatus();
    public function get_templates() {
        global $DB;

        try {
            $hasnext = true; // Initialise to true for first fetch.
            while ($hasnext) {
                $hasnext = false;
                $latestmodified = self::get_lastestmodified_field('enrol_arlo_template');
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
                $modifiedfilter = Filter::create()
                    ->setResourceField('LastModifiedDateTime')
                    ->setOperator('gt')
                    ->setDateValue(date::create($latestmodified),true);
                $requesturi->addFilter($createdfilter);
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
                //$logentry = self::log(time(), (string) $requesturi, $status);

                $templates = self::parse_response($response);
                if (!$templates) {
                    self::trace("No new or updated resources found.");
                } else {
                    if ($templates instanceof AbstractCollection) {
                        foreach ($templates as $template) {
                            $record = new \stdClass();
                            $record->platform = $this->platform;
                            $record->sourceid = $template->TemplateID;
                            $record->sourceguid = $template->UniqueIdentifier;
                            $record->name = $template->Name;
                            $record->code = $template->Code;
                            $record->sourcestatus = $template->Status;
                            $record->sourcecreated = date::create($template->CreatedDateTime)->getTimestamp();
                            $record->sourcemodified = date::create($template->LastModifiedDateTime)->getTimestamp();

                            $params = array(
                                'platform' => $this->platform,
                                'sourceid' => $template->TemplateID,
                                'sourceguid' => $template->UniqueIdentifier
                            );
                            $record->id = $DB->get_field('enrol_arlo_template', 'id', $params);
                            if (empty($record->id)) {
                                unset($record->id);
                                $DB->insert_record('enrol_arlo_template', $record);
                                self::trace(sprintf('Created: %s', $record->name));
                            } else {
                                $DB->update_record('enrol_arlo_template', $record);
                                self::trace(sprintf('Updated: %s', $record->name));
                            }
                        }
                        $hasnext = (bool) $templates->hasNext();
                    }
                }
            }
        } catch (\Exception $e) {
            // TODO Any extra handling/logging?
            if ($e instanceof client_exception) {
                self::trace($e->getCode() . ' ' . $e->getMessage());
                $identifier = $e->getStringidentifier();
                $params = $e->getParameters();
                self::alert($identifier, $params);
            }
            return false;
        }
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        self::trace("Execution took {$difftime} seconds");
        return true;
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
        if ($stream->eof()) $stream->rewind(); // Rewind stream.
        $resourceclass = $deserializer->deserialize($contents);
        // Get api exception information if available.
        $apiexceptioninfo = array();
        if ($resourceclass instanceof ApiException) {
            $apiexceptioninfo['code'] = $resourceclass->Code;
            $apiexceptioninfo['message'] = $resourceclass->Message;
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
                $apiexceptioninfo);
        // Server side.
        } else if ($status >= 500 && $status < 599) {
            $identifier = 'error_5xx';
            $params = array();
            throw new server_exception(
                $reason,
                $status,
                $identifier,
                $params,
                $apiexceptioninfo);
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
