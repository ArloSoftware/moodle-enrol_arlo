<?php

namespace enrol_arlo;

use enrol_arlo\Arlo\AuthAPI\Client;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\Arlo\AuthAPI\Filter;
use enrol_arlo\Arlo\AuthAPI\XmlDeserializer;
use enrol_arlo\Arlo\AuthAPI\Resource\AbstractCollection;
use enrol_arlo\Arlo\AuthAPI\Resource\AbstractResource;
use enrol_arlo\Arlo\AuthAPI\Resource\ApiException;
use enrol_arlo\utility\date;
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

    private function get_lastestmodified_field($table) {
        global $DB;
        $sql = "SELECT MAX(t.sourcemodified) AS latestmodified 
                  FROM {{$table}} t
                 WHERE t.platform = :platform";
        return (int) $DB->get_field_sql($sql, array('platform' => $this->platform));
    }


    public function get_templates() {
        global $DB;
        // Can run? Check API status.
        //self::check_apistatus();
        try {

            //$cnt = 0;
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
                    ->setDateValue(date::create($latestmodified));
                $requesturi->addFilter($createdfilter);
                $requesturi->addFilter($modifiedfilter);

                $client = new Client($this->platform, $this->apiusername, $this->apipassword);
                $timestart = microtime();
                $response = $client->request('GET', $requesturi);

                $templates = self::parse_response($response);
                if ($templates) {
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
                            } else {
                                $DB->update_record('enrol_arlo_template', $record);
                            }
                        }
                        $hasnext = (bool) $templates->hasNext();
                    }
                }
            }

            $log = array(
                'timelogged' => $timestart,
                'platform' => $this->platform,
                'uri' => (string) $requesturi
            );


        } catch (\Exception $e) {
            print_object($e);
            die;
        }
        $timefinish = microtime();
       // $difftime = microtime_diff($timestart, $timefinish);
       // mtrace("Execution took {$difftime} seconds");
    }

    private function parse_response(Response $response) {
        $status = (int) $response->getStatusCode();
        plugin_config::set('apistatus', $status);
        $this->lastresponsestatus = $status;

        // Incorrect content-type.
        $contenttype = $response->getHeaderLine('content-type');
        if (strpos($contenttype, 'application/xml') === false) {
            self::alert('error_incorrectcontenttype' , array('contenttype' => $contenttype));
            return false;
        }

        $deserializer = new XmlDeserializer('\enrol_arlo\Arlo\AuthAPI\Resource\\');
        $stream = $response->getBody();
        $contents = $stream->getContents();
        if ($stream->eof()) $stream->rewind(); // Rewind stream.
        $resource = $deserializer->deserialize($contents);

        // Handle HTTP Status errors.
        if ($status >= 400 && $status < 599) {
            $exceptioncode = '';
            $exceptionmessage = '';
            if ($resource instanceof ApiException) {
                $exceptioncode = $resource->Code;
                $exceptionmessage = $resource->Message;
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
        return $resource;
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