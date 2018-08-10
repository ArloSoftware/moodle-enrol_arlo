<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace enrol_arlo;

use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\Arlo\AuthAPI\Filter;
use enrol_arlo\Arlo\AuthAPI\Resource\Registration;
use enrol_arlo\Arlo\AuthAPI\XmlDeserializer;
use enrol_arlo\Arlo\AuthAPI\Resource\Event;
use enrol_arlo\Arlo\AuthAPI\Resource\EventTemplate;
use enrol_arlo\Arlo\AuthAPI\Resource\OnlineActivity;
use enrol_arlo\Arlo\AuthAPI\Enum\RegistrationStatus;
use enrol_arlo\exception\instance_exception;
use enrol_arlo\exception\invalidcontent_exception;
use enrol_arlo\exception\lock_exception;
use enrol_arlo\request\collection;
use GuzzleHttp\Psr7\Response;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/enrol/arlo/lib.php");

class manager {
    const EMAIL_TYPE_NEW_ACCOUNT    = 'newaccount';
    const EMAIL_TYPE_COURSE_WELCOME = 'coursewelcome';
    const EMAIL_TYPE_NOTIFY_EXPIRY  = 'notifyexpiry';
    const EMAIL_STATUS_QUEUED       = 100;
    const EMAIL_STATUS_DELIVERED    = 200;
    const EMAIL_STATUS_FAILED       = 500;
    const EMAIL_PROCESSING_LIMIT    = 250;

    /** @var REQUEST_INTERVAL_SECONDS default for normal pull and push operations. */
    const REQUEST_INTERVAL_SECONDS      = 900; // 15 Minutes.
    /** @var REQUEST_EXTENSION_SECONDS default for normal pull and push operations. */
    const REQUEST_EXTENSION_SECONDS     = 259200; // 72 Hours.
    /** @var MAXIMUM_ERROR_COUNT */
    const MAXIMUM_ERROR_COUNT           = 20;
    /** @var DELAY_REQUEST_SECONDS time in seconds to delay next request. */
    const DELAY_REQUEST_SECONDS         = 900; // 15 Minutes.
    /** @var CONTACT_REQUEST_INTERVAL_SECONDS */
    const CONTACT_REQUEST_INTERVAL_SECONDS  = 86400; // 24 hours.
    /** @var $plugin enrolment plugin instance. */
    const LOCK_TIMEOUT_DEFAULT          = 5;

    private static $plugin;
    /** @var \progress_trace  */
    private static $trace;

    public function __construct(\progress_trace $trace = null) {
        // Raise limits, so this script can be interrupted without problems.
        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);
        // Setup trace.
        if (is_null($trace)) {
            self::$trace = new \null_progress_trace();
        } else {
            self::$trace = $trace;
        }
        self::$plugin = new \enrol_arlo_plugin();
    }

    /**
     * Can api be called. Delay call based client/server HTTP error status.
     *
     * @return bool
     */
    public function api_callable() {
        $apilaststatus      = self::$plugin->get_config('apistatus');
        $apilastrequested   = self::$plugin->get_config('apilastrequested');
        $apierrorcount      = self::$plugin->get_config('apierrorcount');
        if (!enrol_is_enabled('arlo')) {
            self::trace(get_string('pluginnotenabled', 'enrol_arlo'));
            return false;
        }
        // Client errors.
        if ($apilaststatus == 0 || ($apilaststatus == 401 || $apilaststatus == 403)) {
            $delay = $apilastrequested + self::DELAY_REQUEST_SECONDS;
            if ($delay > time()) {
                self::trace(sprintf("Client connection issue. Next request delayed until: %s", userdate($delay)));
                return false;
            }
        }
        // Server errors.
        if ($apilaststatus >= 500 && $apilaststatus <= 599) {
            $delay = $apilastrequested + self::DELAY_REQUEST_SECONDS;
            if ($delay > time()) {
                self::trace(sprintf("Server issue. Next request delayed until: %s", userdate($delay)));
                return false;
            }
        }
        return true; // Callable if get this far.
    }

    /**
     * Clean orphaned records.
     *
     * This is used to clean up stale records that can be left behind
     * when a course or enrolment instance is deleted during a sync.
     */
    public function cleanup_orphaned_instances() {
        global $DB;
        // Get orphaned Arlo instance records.
        $sql = "SELECT DISTINCT (eai.enrolid)
                           FROM {enrol_arlo_instance} eai
                      LEFT JOIN {enrol} e
                             ON e.id = eai.enrolid
                          WHERE e.id IS NULL";
        $instances = $DB->get_records_sql($sql);
        $instances = array_keys($instances);
        // Get orphaned Arlo schedule records.
        $sql = "SELECT DISTINCT (eas.enrolid)
                           FROM {enrol_arlo_schedule} eas
                      LEFT JOIN {enrol} e
                             ON e.id = eas.enrolid
                          WHERE eas.enrolid <> 0 AND e.id IS NULL;";
        $schedules = $DB->get_records_sql($sql);
        $schedules = array_keys($schedules);
        // Get orphaned Arlo registration records.
        $sql = "SELECT DISTINCT (ear.enrolid)
                           FROM {enrol_arlo_registration} ear
                      LEFT JOIN {enrol} e
                             ON e.id = ear.enrolid
                          WHERE e.id IS NULL";
        $registrations = $DB->get_records_sql($sql);
        $registrations = array_keys($registrations);
        // Unique orphans, no associated Moodle enrolment instance.
        $orphans = array_unique(array_merge($instances, $registrations));
        foreach ($orphans as $orphan) {
            if ($orphan) {
                // Delete Arlo instance information.
                $DB->delete_records('enrol_arlo_instance', array('enrolid' => $orphan));
                // Delete scheduling information.
                $DB->delete_records('enrol_arlo_schedule', array('enrolid' => $orphan));
                // Delete associated registrations.
                $DB->delete_records('enrol_arlo_registration', array('enrolid' => $orphan));
                // Delete email queue information.
                $DB->delete_records('enrol_arlo_emailqueue', array('enrolid' => $orphan));
            }
        }
    }

    /**
     * Get all active and visible Arlo enrolment instances.
     *
     * @param string $orderby
     * @return array
     */
    public function get_enrol_instances() {
        global $DB;
        $platform = self::$plugin->get_config('platform');
        $sql = "SELECT e.*
                  FROM {enrol} e
                  JOIN {enrol_arlo_instance} eai ON eai.enrolid = e.id
                  JOIN {course} c ON c.id = e.courseid
                 WHERE e.enrol = :enrol
                   AND e.status = :status
                   AND eai.platform = :platform
                   AND c.visible = 1";
        $conditions = array(
            'enrol' => 'arlo',
            'status' => ENROL_INSTANCE_ENABLED,
            'platform' => $platform
        );
        return $DB->get_records_sql($sql, $conditions);
    }

    /**
     * Headers used to identify plugin and requests to API.
     *
     * @return array
     */
    public function get_client_identity_headers() {
        global $CFG;
        require_once("$CFG->libdir/adminlib.php");
        static $headers;
        if (!isset($headers)) {
            include("$CFG->dirroot/enrol/arlo/version.php");
            $headers = array();
            $headers['User-Agent'] = 'Moodle/' . moodle_major_version() . ';' . $CFG->wwwroot;
            $headers['X-Plugin-Version'] = $plugin->release;
        }
        return $headers;
    }

    /**
     * Main processing function. Provide order of processing. Envoked by
     * scheduled task.
     *
     * @param bool $manualoverride
     */
    public function process_all($manualoverride = false) {
        // Check API callable.
        if (!self::api_callable()) {
            return; // Don't break scheduled task be returning false.
        }
        // Order of processing.
        self::process_templates($manualoverride);
        self::process_events($manualoverride);
        self::process_onlineactivities($manualoverride);
        self::process_instances($manualoverride);
        self::process_results($manualoverride);
        self::process_contacts($manualoverride);
        self::process_expirations();
        self::process_email_queue();
    }

    /**
     * Function for get enrolment instance to process. Hidden instances or instances in a hidden
     * course are not included.
     *
     * @param bool $manualoverride
     * @return bool
     */
    public function process_instances($manualoverride = false) {
        global $DB;
        $platform = self::$plugin->get_config('platform');
        $sql = "SELECT e.*
                  FROM {enrol} e
                  JOIN {enrol_arlo_instance} eai ON eai.enrolid = e.id
                  JOIN {enrol_arlo_schedule} eas ON eas.enrolid = e.id
                  JOIN {course} c ON c.id = e.courseid
                 WHERE e.enrol = :enrol
                   AND e.status = :status
                   AND eai.platform = :platform
                   AND eas.resourcetype = :resourcetype
                   AND c.visible = 1
              ORDER BY eas.lastpulltime";
        $conditions = array(
            'enrol' => 'arlo',
            'status' => ENROL_INSTANCE_ENABLED,
            'platform' => $platform,
            'resourcetype' => 'registrations'
        );
        $instances = $DB->get_records_sql($sql, $conditions);
        if (empty($instances)) {
            self::trace('No Arlo Registration instances found to pull.');
        } else {
            foreach ($instances as $instance) {
                self::process_instance_registrations($instance, $manualoverride);
            }
        }
        return true;
    }

    /**
     * Process Result information for any users in a active and enabled Arlo
     * enrolment instance. Information is pushed to Arlo.
     *
     * @param bool $manualoverride
     */
    public function process_results($manualoverride = false) {
        global $DB;
        $platform = self::$plugin->get_config('platform');
        $sql = "SELECT e.*
                  FROM {enrol} e
                  JOIN {enrol_arlo_instance} eai ON eai.enrolid = e.id
                  JOIN {enrol_arlo_schedule} eas ON eas.enrolid = e.id
                  JOIN {course} c ON c.id = e.courseid
                 WHERE e.enrol = :enrol
                   AND e.status = :status
                   AND eai.platform = :platform
                   AND eas.resourcetype = :resourcetype
                   AND c.visible = 1
              ORDER BY eas.lastpushtime";
        $conditions = array(
            'enrol' => 'arlo',
            'status' => ENROL_INSTANCE_ENABLED,
            'platform' => $platform,
            'resourcetype' => 'registrations'
        );
        $instances = $DB->get_records_sql($sql, $conditions);
        if (empty($instances)) {
            self::trace('No Arlo Result instances found to push');
        } else {
            foreach ($instances as $instance) {
                self::process_instance_results($instance, $manualoverride);
            }
        }
    }

    /**
     * Update Contact information for Moodle users that are in active and
     * enabled Arlo enrolment instances.
     *
     * @param bool $manualoverride
     * @return bool
     */
    public function process_contacts($manualoverride = false) {
        global $DB;
        $platform = self::$plugin->get_config('platform');
        $sql = "SELECT e.*
                  FROM {enrol} e
                  JOIN {enrol_arlo_instance} eai ON eai.enrolid = e.id
                  JOIN {enrol_arlo_schedule} eas ON eas.enrolid = e.id
                  JOIN {course} c ON c.id = e.courseid
                 WHERE e.enrol = :enrol
                   AND e.status = :status
                   AND eai.platform = :platform
                   AND eas.resourcetype = :resourcetype
                   AND c.visible = 1
              ORDER BY eas.lastpulltime";
        $conditions = array(
            'enrol' => 'arlo',
            'status' => ENROL_INSTANCE_ENABLED,
            'platform' => $platform,
            'resourcetype' => 'contacts'
        );
        $instances = $DB->get_records_sql($sql, $conditions);
        if (empty($instances)) {
            self::trace('No Arlo Contact instances found to pull.');
        } else {
            foreach ($instances as $instance) {
                self::update_instance_contacts($instance, $manualoverride);
            }
        }
        return true;
    }

    /**
     * Creates a schedule record for a resource type, can be enrolment instance specific.
     * Zero enrolment idenfier denotes a schedule for system wide collection. Such as
     * Event Templates, Events and Online Activities.
     *
     * @param $resourcetype
     * @param int $enrolid
     * @param int $endpulltime
     * @param int $endpushtime
     * @return mixed|\stdClass
     * @throws \coding_exception
     */
    public static function schedule($resourcetype, $enrolid = 0, $endpulltime = 0, $endpushtime = 0) {
        global $DB;
        if (!is_string($resourcetype)) {
            throw new \coding_exception('resourcetype must be string');
        }
        $nextpulltime = 0;
        $nextpushtime = time() + 86400;
        if ($resourcetype == 'contacts') {
            $nextpulltime = time() + 86400;
        }
        $plugin = new \enrol_arlo_plugin();
        $platform = $plugin->get_config('platform');
        $conditions = array('resourcetype' => $resourcetype, 'enrolid' => $enrolid, 'platform' => $platform);
        $schedule = $DB->get_record('enrol_arlo_schedule', $conditions);
        if (!$schedule) {
            $plugin                             = new \enrol_arlo_plugin();
            $schedule                           = new \stdClass();
            $schedule->enrolid                  = $enrolid;
            $schedule->platform                 = $platform;
            $schedule->resourcetype             = $resourcetype;
            $servertimezone                     = \core_date::get_server_timezone();
            $tz                                 = new \DateTimeZone($servertimezone);
            $date                               = \DateTime::createFromFormat('U', 0, $tz);
            $schedule->latestsourcemodified     = $date->format(DATE_ISO8601); // Default 0 to 1970-01-01T00:00:00+0000.
            $schedule->nextpulltime             = $nextpulltime;
            $schedule->lastpulltime             = 0;
            $schedule->endpulltime              = $endpulltime;
            $schedule->nextpushtime             = $nextpushtime;
            $schedule->lastpushtime             = 0;
            $schedule->endpushtime              = $endpushtime;
            $schedule->lasterror                = '';
            $schedule->errorcount               = 0;
            $schedule->modified                 = time();
            $schedule->id = $DB->insert_record('enrol_arlo_schedule', $schedule);
        } else {
            $schedule->endpulltime              = $endpulltime;
            $schedule->endpushtime              = $endpushtime;
            $DB->update_record('enrol_arlo_schedule', $schedule);
        }
        return $schedule;
    }

    /**
     * Get or create schedule record. Can filter on enrolment instance. Can reset error.
     *
     * @param $resourcetype
     * @param int $enrolid
     * @param bool $reseterror
     * @return mixed|\stdClass
     * @throws \coding_exception
     */
    public static function get_schedule($resourcetype, $enrolid = 0, $reseterror = false) {
        global $DB;
        if (!is_string($resourcetype)) {
            throw new \coding_exception('resourcetype must be string');
        }
        $plugin = new \enrol_arlo_plugin();
        $platform = $plugin->get_config('platform');
        $conditions = array('resourcetype' => $resourcetype, 'enrolid' => $enrolid, 'platform' => $platform);
        $schedule = $DB->get_record('enrol_arlo_schedule', $conditions);
        if (!$schedule) {
            $schedule = self::schedule($resourcetype, $enrolid);
        }
        if ($reseterror) {
            $schedule->lasterror = '';
            $schedule->errorcount = 0;
        }
        return $schedule;
    }

    /**
     * Updates scheduling, error information on a passed in record.
     *
     * @param \stdClass $schedule
     */
    public static function update_scheduling_information(\stdClass $schedule) {
        global $DB;
        if (!empty($schedule->updatenextpulltime) && $schedule->nextpulltime != '-1') {
            $schedule->nextpulltime = time();
        }
        if (!empty($schedule->updatenextpushtime) && $schedule->nextpushtime != '-1') {
            $schedule->nextpushtime = time();
        }
        $schedule->modified = time();
        $DB->update_record('enrol_arlo_schedule', $schedule);
    }

    /**
     * Process result information for registrations with an enrolment instance.
     *
     * @param $instance
     * @param $manualoverride
     * @return bool
     */
    public function process_instance_results($instance, $manualoverride = false) {
        global $DB;
        $timestart = microtime();
        if (!self::api_callable()) {
            return false;
        }
        list($platform, $apiusername, $apipassword) = self::get_connection_vars();
        self::trace(sprintf("Updating result information for %s", $instance->name));
        try {
            $lockresource = 'instance:' . $instance->id;
            $lockfactory = \core\lock\lock_config::get_lock_factory('enrol_arlo_process_instance');
            if ($lock = $lockfactory->get_lock($lockresource, self::LOCK_TIMEOUT_DEFAULT)) {
                // Get sync information.
                $arloinstance = self::get_associated_arlo_instance($instance->id);
                // Shouldn't happen. Just extra check if somehow  enrol record exists but no associated Arlo instance record.
                if (!$arloinstance) {
                    throw new instance_exception('No matching Arlo enrolment instance.');
                }
                // Push configuration.
                $pushonlineactivityresults = self::$plugin->get_config('pushonlineactivityresults');
                $pusheventresults = self::$plugin->get_config('pusheventresults');
                if ($arloinstance->type == \enrol_arlo_plugin::ARLO_TYPE_EVENT && !$pusheventresults) {
                    $lock->release();
                    self::trace('Pushing Event results disabled in configuration');
                    return false;
                }
                if ($arloinstance->type == \enrol_arlo_plugin::ARLO_TYPE_ONLINEACTIVITY && !$pushonlineactivityresults) {
                    $lock->release();
                    self::trace('Pushing Online Activity results disabled in configuration');
                    return false;
                }
                // Get schedule information.
                $schedule = self::get_schedule('registrations', $instance->id);
                if (!$schedule) {
                    throw new instance_exception('No matching schedule information');
                }
                if (!self::can_push($schedule, $manualoverride)) {
                    $lock->release();
                    return false;
                }
                // Get in registrations that require a push.
                $conditions = array(
                    'enrolid' => $instance->id,
                    'updatesource' => 1
                );
                $records = $DB->get_records('enrol_arlo_registration', array('enrolid' => $instance->id), 'lastpushtime');
                if (!$records) {
                    self::trace("No records found requiring a registration result push.");
                } else {
                    foreach ($records as $registrationrecord) {
                        $result = new result($instance->courseid, $registrationrecord);
                        // No need to go any further if nothing has changed.
                        if (!$result->has_changed()) {
                            self::trace('Result information has not changed');
                            continue;
                        }
                        // Get generated Xml for push to Arlo.
                        $xmlbody = $result->export_to_xml();
                        $sourceid = $registrationrecord->sourceid;
                        $requesturi = new RequestUri();
                        $requesturi->setHost($platform);
                        $resourcepath = 'registrations/' . $sourceid . '/';
                        $requesturi->setResourcePath($resourcepath);
                        $options = array();
                        $options['auth'] = array(
                            $apiusername,
                            $apipassword
                        );
                        $headers = self::get_client_identity_headers();
                        $headers['Content-type'] = 'application/xml; charset=utf-8';
                        $request = new \enrol_arlo\request\patch_request($schedule, $requesturi, $headers, $xmlbody, $options);
                        $schedule->lastpushtime = time();
                        $response = $request->execute();
                        $updateregistrationrecord = new \stdClass();
                        $updateregistrationrecord->id = $registrationrecord->id;
                        $updateregistrationrecord->lastpushtime = time();
                        $updateregistrationrecord->modified = time();
                        if (! (200 == $response->getStatusCode() || 201 == $response->getStatusCode())) {
                            self::trace(sprintf("Bad response (%s)", $response->getStatusCode()));
                            $lasterror = $response->getStatusCode();
                            $errorcount = $registrationrecord->errorcount;
                            $updateregistrationrecord->lasterror = $lasterror;
                            $updateregistrationrecord->errorcount = ++$errorcount;
                        } else {
                            self::trace('Result information successfully pushed');
                            // Add changed field values on to record object.
                            $changed = $result->get_changed();
                            foreach (get_object_vars($changed) as $field => $value) {
                                $updateregistrationrecord->{$field} = $value;
                            }
                            // Clear flags for successful push.
                            $updateregistrationrecord->updatesource = 0;
                            $updateregistrationrecord->lasterror = '';
                            $updateregistrationrecord->errorcount = 0;
                        }
                        // Update registration record in Moodle.
                        $DB->update_record('enrol_arlo_registration', $updateregistrationrecord);
                    }
                    $schedule->updatenextpushtime = true;
                    self::update_scheduling_information($schedule);
                }
            } else {
                throw new lock_exception('operationiscurrentlylocked', 'enrol_arlo');
            }
        } catch (\Exception $exception) {
            if ($exception instanceof instance_exception) {
                self::trace($exception->getMessage());
            }
            if ($exception instanceof lock_exception) {
                self::trace(get_string('operationiscurrentlylocked', 'enrol_arlo'));
            } else {
                $lock->release();
            }
            if (isset($schedule)) {
                $errorcount = (int) $schedule->errorcount;
                $schedule->errorcount = ++$errorcount;
                $schedule->lasterror = $exception->getMessage();
                self::update_scheduling_information($schedule);
            }
            debugging($exception->getMessage(), DEBUG_DEVELOPER, $exception->getTrace());
            return false;
        }
        $lock->release();
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        self::trace("Execution took {$difftime} seconds");
        return true;
    }

    /**
     * Update Contacts for a specific Arlo enrolment instance.
     *
     * @param $instance
     * @param $manualoverride
     * @return bool
     */
    public function update_instance_contacts($instance, $manualoverride) {
        $timestart = microtime();
        if (!self::api_callable()) {
            return false;
        }
        list($platform, $apiusername, $apipassword) = self::get_connection_vars();
        self::trace(sprintf("Updating Contact information for %s", $instance->name));
        try {
            $hasnext = true; // Initialise to for multiple pages.
            while ($hasnext) {
                $hasnext = false; // Avoid infinite loop by default.
                // Get sync information.
                $arloinstance = self::get_associated_arlo_instance($instance->id);
                // Shouldn't happen. Just extra check if somehow  enrol record exists but no associated Arlo instance record.
                if (!$arloinstance) {
                    self::trace('No matching Arlo enrolment instance.');
                    break;
                }
                // Get schedule information.
                $schedule = self::get_schedule('contacts', $instance->id, true);
                if (!$schedule) {
                    self::trace('No matching schedule information');
                    break;
                }
                if (!self::can_pull($schedule , $manualoverride)) {
                    break;
                }
                $type     = $arloinstance->type;
                $sourceid = $arloinstance->sourceid;
                // Event, set resource path.
                if ($type == \enrol_arlo_plugin::ARLO_TYPE_EVENT) {
                    $resourcepath = 'events/' . $sourceid . '/registrations/';
                }
                // Online Activity, set resource path.
                if ($type == \enrol_arlo_plugin::ARLO_TYPE_ONLINEACTIVITY) {
                    $resourcepath = 'onlineactivities/' . $sourceid . '/registrations/';
                }
                // Setup RequestUri for getting Events.
                $requesturi = new RequestUri();
                $requesturi->setHost($platform);
                $requesturi->setResourcePath($resourcepath);
                $requesturi->addExpand('Registration/Contact');
                $requesturi->setOrderBy('Contact/LastModifiedDateTime ASC');
                $latestmodified = $schedule->latestsourcemodified;
                $modifiedfilter = Filter::create()->setResourceField('Contact/LastModifiedDateTime')->setOperator('gt')->setDateValue($latestmodified);
                $requesturi->addFilter($modifiedfilter);
                $options = array();
                $options['auth'] = array(
                    $apiusername,
                    $apipassword
                );
                $headers = self::get_client_identity_headers();
                $request = new \enrol_arlo\request\collection_request($schedule, $requesturi, $headers, null, $options);
                $schedule->lastpulltime = time();
                $response = $request->execute();
                $schedule->lastpulltime = time();
                if (200 != $response->getStatusCode()) {
                    self::trace(sprintf("Bad response (%s) leaving the room.", $response->getStatusCode()));
                    return false;
                }
                $collection = self::deserialize_response_body($response);
                // Any returned.
                if (iterator_count($collection) == 0) {
                    self::update_scheduling_information($schedule);
                    self::trace("No new or updated Contact resources found.");
                } else {
                    foreach ($collection as $registration) {
                        $contactresource = $registration->getContact();
                        $user = new user(self::$trace);
                        if (!$user->load_by_resource($contactresource)) {
                            continue; // Cancelled before record was created.
                        }
                        $user->update();
                        self::trace(sprintf("Updated %s", $user->get_user_fullname()));
                        $latestmodified = $contactresource->LastModifiedDateTime;
                        $schedule->latestsourcemodified = $latestmodified;
                    }
                    $hasnext = (bool) $collection->hasNext();
                    $schedule->updatenextpulltime = ($hasnext) ? false : true;
                    self::update_scheduling_information($schedule);
                }
            }
        } catch (\Exception $exception) {
            if (isset($schedule)) {
                $errorcount = (int) $schedule->errorcount;
                $schedule->errorcount = ++$errorcount;
                $schedule->lasterror = $exception->getMessage();
                self::update_scheduling_information($schedule);
            }
            debugging($exception->getMessage(), DEBUG_DEVELOPER, $exception->getTrace());
            return false;
        }
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        self::trace("Execution took {$difftime} seconds");
        return true;

    }

    /**
     * Check if item can be pulled based on scheduling information.
     *
     * @param $record
     * @param bool $manualoverride
     * @return bool
     */
    protected function can_pull(\stdClass $record, $manualoverride = false) {
        $timestart = time();
        if ($manualoverride) {
            return true;
        }

        $nextpulltime = $record->nextpulltime;
        $endpulltime = $record->endpulltime;
        // Defaults.
        $interval = self::REQUEST_INTERVAL_SECONDS;
        $extension = self::REQUEST_EXTENSION_SECONDS;

        if ($record->resourcetype == 'contacts') {
            $interval = self::CONTACT_REQUEST_INTERVAL_SECONDS;
        }

        // Pull disabled for this record.
        if ($nextpulltime == -1) {
            self::trace('Disabled');
            return false;
        }

        // Return if next pull time hasn't passed current time.
        if ($timestart < ($nextpulltime + $interval)) {
            self::trace(sprintf('Next pull time not yet reached %s', userdate($nextpulltime + $interval)));
            return false;
        }

        // Return if end pull time has past.
        if (!empty($endpulltime) && $timestart > ($endpulltime + $extension)) {
            self::trace('End pull time has passed');
            return false;
        }
        return true;
    }

    /**
     * Check if item can be pushed based on scheduling information.
     *
     * @param \stdClass $record
     * @param bool $manualoverride
     * @return bool
     */
    protected function can_push(\stdClass $record, $manualoverride = false) {
        $timestart = time();
        if ($manualoverride) {
            return true;
        }

        $nextpushtime = $record->nextpushtime;
        $endpushtime = $record->endpushtime;
        // Defaults.
        $interval = self::REQUEST_INTERVAL_SECONDS;
        $extension = self::REQUEST_EXTENSION_SECONDS;

        // Push disabled for this record.
        if ($nextpushtime == -1) {
            self::trace('Disabled');
            return false;
        }
        // Return if next push time hasn't passed current time.
        if ($timestart < ($nextpushtime + $interval)) {
            self::trace(sprintf('Next push time not yet reached %s', userdate($nextpushtime + $interval)));
            return false;
        }

        // Return if end push time has past.
        if (!empty($endpushtime) && $timestart > ($endpushtime + $extension)) {
            self::trace('End push time has passed');
            return false;
        }
        return true;
    }

    /**
     * Get associated Arlo instance information. This contains information about the Event or Online Activity
     * linked to a Moodle enrolment instance record.
     *
     * @param $enrolid
     * @return mixed
     */
    public static function get_associated_arlo_instance($enrolid) {
        global $DB;
        $arloinstance = $DB->get_record('enrol_arlo_instance', array('enrolid' => $enrolid));
        if ($arloinstance) {
            $arloinstance->tablename = 'enrol_arlo_instance';
        }
        return $arloinstance;
    }

    /**
     * Process any registrations for an enrolment instance.
     *
     * @param $instance
     * @param bool $manualoverride
     * @return bool
     */
    public function process_instance_registrations($instance, $manualoverride = false) {
        $timestart = microtime();
        // Always run clean up first.
        self::cleanup_orphaned_instances();
        // Check if can call API.
        if (!self::api_callable()) {
            return false;
        }
        list($platform, $apiusername, $apipassword) = self::get_connection_vars();
        self::trace(sprintf("Processing Registrations for instance %s", $instance->name));
        try {
            $lockresource = 'instance:' . $instance->id;
            $lockfactory = \core\lock\lock_config::get_lock_factory('enrol_arlo_process_instance');
            if ($lock = $lockfactory->get_lock($lockresource, self::LOCK_TIMEOUT_DEFAULT)) {
                $hasnext = true; // Initialise to for multiple pages.
                while ($hasnext) {
                    $hasnext = false; // Avoid infinite loop by default.
                    // Get sync information.
                    $arloinstance = self::get_associated_arlo_instance($instance->id);
                    // Shouldn't happen. Just extra check if somehow  enrol record exists but no associated Arlo instance record.
                    if (!$arloinstance) {
                        throw new instance_exception('No matching Arlo enrolment instance.');
                    }
                    // Get schedule information.
                    $schedule = self::get_schedule('registrations', $instance->id, true);
                    if (!$schedule) {
                        throw new instance_exception('No matching schedule information');
                    }
                    if (!self::can_pull($schedule, $manualoverride)) {
                        $lock->release();
                        break;
                    }
                    $type = $arloinstance->type;
                    $sourceid = $arloinstance->sourceid;
                    // Event, set resource path and expand accordingly.
                    if ($type == \enrol_arlo_plugin::ARLO_TYPE_EVENT) {
                        $resourcepath = 'events/' . $sourceid . '/registrations/';
                        $expand = 'Registration/Event';

                    }
                    // Online Activity, set resource path and expand accordingly.
                    if ($type == \enrol_arlo_plugin::ARLO_TYPE_ONLINEACTIVITY) {
                        $resourcepath = 'onlineactivities/' . $sourceid . '/registrations/';
                        $expand = 'Registration/OnlineActivity';
                    }
                    // Setup RequestUri for getting Events.
                    $requesturi = new RequestUri();
                    $requesturi->setHost($platform);
                    $requesturi->setResourcePath($resourcepath);
                    $requesturi->addExpand('Registration/Contact');
                    $requesturi->addExpand($expand);
                    $requesturi->setPagingTop(250);
                    $latestmodified = $schedule->latestsourcemodified;
                    if (empty($latestmodified)) {
                        $servertimezone = \core_date::get_server_timezone();
                        $tz = new \DateTimeZone($servertimezone);
                        $date = \DateTime::createFromFormat('U', 0, $tz);
                        $latestmodified = $date->format(DATE_ISO8601);
                    }
                    $lastsourceid = $schedule->lastsourceid;
                    // Events end point doesn't like DateTimeOffset.
                    $filter = '';
                    $filter .= "(LastModifiedDateTime gt datetime('".$latestmodified."'))";
                    if ($lastsourceid) {
                        $filter .= " OR (LastModifiedDateTime eq datetime('".$latestmodified."') AND RegistrationID gt ".$lastsourceid.")";
                    }
                    $requesturi->setFilterBy($filter);
                    $requesturi->setOrderBy("LastModifiedDateTime ASC,RegistrationID ASC");
                    $options = array();
                    $options['auth'] = array(
                        $apiusername,
                        $apipassword
                    );
                    $headers = self::get_client_identity_headers();
                    $request = new \enrol_arlo\request\collection_request($schedule, $requesturi, $headers, null, $options);
                    $schedule->lastpulltime = time();
                    $response = $request->execute();
                    if (200 != $response->getStatusCode()) {
                        $lock->release();
                        self::trace(sprintf("Bad response (%s) leaving the room.", $response->getStatusCode()));
                        return false;
                    }
                    $collection = self::deserialize_response_body($response);
                    // Any returned.
                    if (iterator_count($collection) == 0) {
                        self::update_scheduling_information($schedule);
                        self::trace("No new or updated resources found.");
                    } else {
                        foreach ($collection as $registration) {
                            self::process_enrolment_registration($instance, $arloinstance, $registration);
                            $latestmodified = $registration->LastModifiedDateTime;
                            $schedule->latestsourcemodified = $latestmodified;
                            $lastsourceid = $registration->RegistrationID;
                            $schedule->lastsourceid = $lastsourceid;
                        }
                        $hasnext = (bool) $collection->hasNext();
                        if ($hasnext) {
                            $schedule->updatenextpulltime = false;
                            self::trace('Get next page from Arlo');
                        } else {
                            $schedule->updatenextpulltime = true;
                        }
                        self::update_scheduling_information($schedule);
                    }
                }
            } else {
                throw new lock_exception('operationiscurrentlylocked', 'enrol_arlo');
            }
        } catch (\Exception $exception) {
            if ($exception instanceof instance_exception) {
                self::trace($exception->getMessage());
            }
            if ($exception instanceof lock_exception) {
                self::trace(get_string('operationiscurrentlylocked', 'enrol_arlo'));
            } else {
                $lock->release();
            }
            if (isset($schedule)) {
                $errorcount = (int) $schedule->errorcount;
                $schedule->errorcount = ++$errorcount;
                $schedule->lasterror = $exception->getMessage();
                self::update_scheduling_information($schedule);
            }
            debugging($exception->getMessage(), DEBUG_DEVELOPER, $exception->getTrace());
            return false;
        }
        $lock->release();
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        self::trace("Execution took {$difftime} seconds");
        return true;
    }

    /**
     * Queue a email type for later processing.
     *
     * @param $enrolid
     * @param $userid
     * @param $type
     * @param int $status
     * @return bool|int
     */
    public function add_email_to_queue($enrolid, $userid, $type, $status = self::EMAIL_STATUS_QUEUED) {
        global $DB;

        switch ($type) {
            case self::EMAIL_TYPE_NEW_ACCOUNT:
            case self::EMAIL_TYPE_COURSE_WELCOME:
            case self::EMAIL_TYPE_NOTIFY_EXPIRY:
                break;
            default: // Type not supported.
                return false;
        }
        $record             = new \stdClass();
        $record->enrolid    = $enrolid;
        $record->userid     = $userid;
        $record->type       = $type;
        $record->status     = $status;
        $record->modified   = time();
        $record->id         = $DB->insert_record('enrol_arlo_emailqueue', $record);
        return $record->id;
    }

    /**
     * Update email log entries status in queue table.
     *
     * @param $enrolid
     * @param $userid
     * @param $type
     * @param $status
     */
    public function update_email_status_queue($enrolid, $userid, $type, $status) {
        global $DB;
        $conditions = array('enrolid' => $enrolid, 'userid' => $userid, 'type' => $type);
        $record = $DB->get_record('enrol_arlo_emailqueue', $conditions);
        if ($record) {
            $record->status = $status;
            $record->modified = time();
            $DB->update_record('enrol_arlo_emailqueue', $record);
        }
        return;
    }

    /**
     * Process the email queue. Can be off loaded to php cli/processemailqueue.php
     * for sites that have courses with 1000's of Arlo registrations. This to so the plugin
     * doesn't block any other scheduled tasks.
     */
    public function process_email_queue() {
        global $DB;
        $timestart = microtime();
        $plugin = self::$plugin;
        if ($plugin->get_config('sendemailimmediately', 1)) {
            self::trace('Email processing is configured to send immediately, skipping.');
            return;
        }
        $emailprocessingviacli = $plugin->get_config('emailprocessingviacli', 0);
        if ($emailprocessingviacli && !defined('ENROL_ARLO_CLI_EMAIL_PROCESSING')) {
            self::trace('Email processing is configured to send via cli, skipping.');
            return;
        }
        // Create lock and check if locked.
        $lockfactory = \core\lock\lock_config::get_lock_factory('enrol_arlo_email_queue');
        if (!$lock = $lockfactory->get_lock('enrol_arlo_email_queue', self::LOCK_TIMEOUT_DEFAULT)) {
            throw new \moodle_exception('locktimeout');
        }
        // Setup caches.
        $instances          = array();
        $deletedinstances   = array();
        $users              = array();
        $deletedusers       = array();
        self::trace('Process new account emails');
        // Process new account emails.
        $conditions = array('type' => self::EMAIL_TYPE_NEW_ACCOUNT, 'status' => self::EMAIL_STATUS_QUEUED);
        $rs = $DB->get_recordset('enrol_arlo_emailqueue', $conditions, 'modified', '*',
            0, self::EMAIL_PROCESSING_LIMIT);
        foreach ($rs as $record) {
            $instance = $DB->get_record('enrol', array('id' => $record->enrolid));
            if (!$instance) {
                // Clean up.
                $DB->delete_records('enrol_arlo_emailqueue', array('enrolid' => $record->enrolid));
                continue;
            }
            $user = $DB->get_record('user', array('id' => $record->userid));
            if (!$user) {
                // Clean up.
                $DB->delete_records('enrol_arlo_emailqueue', array('userid' => $record->userid));
                continue;
            }
            $status = self::email_newaccountdetails($instance, $user);
            $deliverystatus = ($status) ? self::EMAIL_STATUS_DELIVERED : self::EMAIL_STATUS_FAILED;
            self::update_email_status_queue($instance->id, $user->id, self::EMAIL_TYPE_NEW_ACCOUNT, $deliverystatus);
        }
        $rs->close();
        // Process course welcome emails.
        self::trace('Process course welcome emails');
        $conditions = array('type' => self::EMAIL_TYPE_COURSE_WELCOME, 'status' => self::EMAIL_STATUS_QUEUED);
        $rs = $DB->get_recordset('enrol_arlo_emailqueue', $conditions, 'modified', '*',
            0, self::EMAIL_PROCESSING_LIMIT);
        foreach ($rs as $record) {
            $instance = $DB->get_record('enrol', array('id' => $record->enrolid));
            if (!$instance) {
                // Clean up.
                $DB->delete_records('enrol_arlo_emailqueue', array('enrolid' => $record->enrolid));
                continue;
            }
            $user = $DB->get_record('user', array('id' => $record->userid));
            if (!$user) {
                // Clean up.
                $DB->delete_records('enrol_arlo_emailqueue', array('userid' => $record->userid));
                continue;
            }
            $status = self::email_coursewelcome($instance, $user);
            $deliverystatus = ($status) ? self::EMAIL_STATUS_DELIVERED : self::EMAIL_STATUS_FAILED;
            self::update_email_status_queue($instance->id, $user->id, self::EMAIL_TYPE_COURSE_WELCOME, $deliverystatus);
        }
        $rs->close();
        // Process expiration emails.
        self::trace('Process course expiration emails');
        $conditions = array('type' => self::EMAIL_TYPE_NOTIFY_EXPIRY, 'status' => self::EMAIL_STATUS_QUEUED);
        $rs = $DB->get_recordset('enrol_arlo_emailqueue', $conditions, 'modified', '*',
            0, self::EMAIL_PROCESSING_LIMIT);
        foreach ($rs as $record) {
            $instance = $DB->get_record('enrol', array('id' => $record->enrolid));
            if (!$instance) {
                // Clean up.
                $DB->delete_records('enrol_arlo_emailqueue', array('enrolid' => $record->enrolid));
                continue;
            }
            $user = $DB->get_record('user', array('id' => $record->userid));
            if (!$user) {
                // Clean up.
                $DB->delete_records('enrol_arlo_emailqueue', array('userid' => $record->userid));
                continue;
            }
            $status = self::email_expirynotice($instance, $user);
            $deliverystatus = ($status) ? self::EMAIL_STATUS_DELIVERED : self::EMAIL_STATUS_FAILED;
            self::update_email_status_queue($instance->id, $user->id, self::EMAIL_TYPE_NOTIFY_EXPIRY, $deliverystatus);
        }
        $rs->close();
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        self::trace("Execution took {$difftime} seconds");
        $lock->release();
        return true;
    }

    /**
     * Generate password for new user and email.
     *
     * @param $instance
     * @param $user
     * @return bool
     */
    public function email_newaccountdetails($instance, $user) {
        global $CFG, $DB;
        // We try to send the mail in language the user understands,
        // unfortunately the filter_string() does not support alternative langs yet
        // so multilang will not work properly for site->fullname.
        $lang = empty($user->lang) ? $CFG->lang : $user->lang;
        $site  = get_site();
        $noreplyuser = \core_user::get_noreply_user();
        $newpassword = generate_password();

        update_internal_user_password($user, $newpassword);

        $a = new \stdClass();
        $a->firstname   = fullname($user, true);
        $a->sitename    = format_string($site->fullname);
        $a->username    = $user->username;
        $a->newpassword = $newpassword;
        $a->link        = $CFG->wwwroot .'/login/';
        $a->signoff     = generate_email_signoff();

        $message = get_string('newusernewpasswordtext', '', $a);
        $subject = format_string($site->fullname) .': '. get_string('newusernewpasswordsubj', '', $a);
        $status = email_to_user($user, $noreplyuser, $subject, $message);
        $deliverystatus = get_string('delivered', 'enrol_arlo');
        if (!$status) {
            $deliverystatus = get_string('failed', 'enrol_arlo');
        }
        self::trace(sprintf("New account details email to user %s %s", $user->id, $deliverystatus));
        return $status;
    }

    /**
     * Send course welcome email to specified user.
     *
     * @param $instance
     * @param $user
     * @return bool
     */
    public function email_coursewelcome($instance, $user) {
        global $CFG, $DB;

        $noreplyuser = \core_user::get_noreply_user();
        $course = $DB->get_record('course', array('id' => $instance->courseid), '*', MUST_EXIST);
        $context = \context_course::instance($course->id);
        $a = new \stdClass();
        $a->coursename = format_string($course->fullname, true, array('context' => $context));
        $a->courseurl = "$CFG->wwwroot/course/view.php?id=$course->id";
        $a->username = $user->username;
        $a->forgotpasswordurl = "$CFG->wwwroot/login/forgot_password.php";
        if (trim($instance->customtext1) !== '') {
            $message = $instance->customtext1;
            $key = array(
                '{$a->coursename}',
                '{$a->courseurl}',
                '{$a->fullname}',
                '{$a->email}',
                '{$a->username}',
                '{$a->forgotpasswordurl}');
            $value = array(
                $a->coursename,
                $a->courseurl,
                fullname($user),
                $user->email,
                $user->username,
                $a->forgotpasswordurl
            );
            $message = str_replace($key, $value, $message);
            if (strpos($message, '<') === false) {
                // Plain text only.
                $messagetext = $message;
                $messagehtml = text_to_html($messagetext, null, false, true);
            } else {
                // This is most probably the tag/newline soup known as FORMAT_MOODLE.
                $messagehtml = format_text($message, FORMAT_MOODLE,
                    array('context' => $context, 'para' => false, 'newlines' => true, 'filter' => true));
                $messagetext = html_to_text($messagehtml);
            }
        } else {
            $messagetext = get_string('welcometocoursetext', 'enrol_arlo', $a);
            $messagehtml = text_to_html($messagetext, null, false, true);
        }

        $subject = get_string('welcometocourse', 'enrol_arlo',
            format_string($course->fullname, true, array('context' => $context)));

        $status = email_to_user($user, $noreplyuser, $subject, $messagetext, $messagehtml);
        $deliverystatus = get_string('delivered', 'enrol_arlo');
        if (!$status) {
            $deliverystatus = get_string('failed', 'enrol_arlo');
        }
        self::trace(sprintf("Course welcome email to user %s %s", $user->id, $deliverystatus));
        return $status;
    }

    /**
     * Notify user their course expiry. Only if notification of enrolled users (aka students) is enabled in course.
     *
     *
     * @param $instance
     * @param $user
     * @return bool
     */
    public function email_expirynotice($instance, $user) {
        global $CFG, $DB;

        $noreplyuser = \core_user::get_noreply_user();
        $course = $DB->get_record('course', array('id' => $instance->courseid), '*', MUST_EXIST);
        $context = \context_course::instance($course->id);

        $a              = new \stdClass();
        $a->coursename  = format_string($course->fullname, true, array('context' => $context));
        $a->courseurl   = "$CFG->wwwroot/course/view.php?id=$course->id";
        $a->user        = fullname($user, true);

        $subject        = get_string('expirymessagesubject', 'enrol_arlo', $a);
        $messagetext    = get_string('expirymessagetext', 'enrol_arlo', $a);
        $messagehtml    = text_to_html($messagetext, null, false, true);

        $status = email_to_user($user, $noreplyuser, $subject, $messagetext, $messagehtml);
        $deliverystatus = get_string('delivered', 'enrol_arlo');
        if (!$status) {
            $deliverystatus = get_string('failed', 'enrol_arlo');
        }
        self::trace(sprintf("Emrolment expiry email to user %s %s", $user->id, $deliverystatus));
        return $status;
    }

    /**
     * Process enrolment registration. Enrol, unenrol or suspend based on configuration.
     *
     * @param $instance
     * @param $arloinstance
     * @param Registration $registration
     * @throws \moodle_exception
     */
    public function process_enrolment_registration($instance, $arloinstance, Registration $registration) {
        global $DB;

        $plugin = self::$plugin;
        $unenrolaction = $plugin->get_config('unenrolaction', ENROL_EXT_REMOVED_UNENROL);

        $contactresource = $registration->getContact();
        if (is_null($contactresource)) {
            throw new \coding_exception('Contact is not set on Registration');
        }

        // Load Contact. TODO this block needs a tidy up.
        $user = new user(self::$trace);
        $user->load_by_resource($contactresource);
        if (!$user->exists()) {
            if ($registration->Status == RegistrationStatus::CANCELLED) {
                self::trace('Not creating new user account for cancelled registration, skipping');
                return true;
            }
            $user = $user->create();
            if ($plugin->get_config('sendnewaccountdetailsemail', 1)) {
                if ($plugin->get_config('sendemailimmediately', 1)) {
                    $status = self::email_newaccountdetails($instance, $user->get_record());
                    $deliverystatus = ($status) ? self::EMAIL_STATUS_DELIVERED : self::EMAIL_STATUS_FAILED;
                    self::add_email_to_queue($instance->id, $user->get_record()->id, self::EMAIL_TYPE_NEW_ACCOUNT, $deliverystatus);
                } else {
                    self::add_email_to_queue($instance->id, $user->get_record()->id, self::EMAIL_TYPE_NEW_ACCOUNT);
                }
            }
        }
        $userid = $user->get_user_id();
        // Build record for Moodle.
        $record = helper::resource_to_record($registration, array('userid' => $userid, 'enrolid' => $instance->id));
        // One user registration associated per enrolment instance get record in database.
        $registrationrecord = $DB->get_record('enrol_arlo_registration', array('sourceguid' => $record->sourceguid));
        if ($registrationrecord) {
            if ($registrationrecord->enrolid != $instance->id) {
                // Clean up. Potententially happen when deleted an instance while was syncing.
                $DB->delete_records('enrol_arlo_registration', array('id' => $registrationrecord->id));
            } else {
                $record->id = $registrationrecord->id;
            }
        }
        // Perform certain enrolment action based on registration status.
        if ($registration->Status == RegistrationStatus::APPROVED || $registration->Status == RegistrationStatus::COMPLETED) {
            $record->modified = time();
            if (empty($record->id)) {
                unset($record->id);
                $record->id = $DB->insert_record('enrol_arlo_registration', $record);
                self::trace(sprintf('Created registration record: %s', $record->userid));
                $timestart = time();
                $timeend = 0;
                if ($instance->enrolperiod) {
                    $timeend = $timestart + $instance->enrolperiod;
                }
                $plugin->enrol_user($instance, $userid, $instance->roleid, $timestart, $timeend, ENROL_USER_ACTIVE);
                self::trace(sprintf('User %s enrolment created', $record->userid));
                // Send course welcome email.
                if ($instance->customint8) {
                    if ($plugin->get_config('sendemailimmediately', 1)) {
                        $status = self::email_coursewelcome($instance, $user->get_record());
                        $deliverystatus = ($status) ? self::EMAIL_STATUS_DELIVERED : self::EMAIL_STATUS_FAILED;
                        self::add_email_to_queue($instance->id, $user->get_record()->id, self::EMAIL_TYPE_COURSE_WELCOME, $deliverystatus);
                    } else {
                        self::add_email_to_queue($instance->id, $user->get_record()->id, self::EMAIL_TYPE_COURSE_WELCOME);
                    }
                }
            } else {
                $DB->update_record('enrol_arlo_registration', $record);
                self::trace(sprintf('Updated registration record: %s', $record->userid));
                $ue = $DB->get_record('user_enrolments', array('enrolid' => $instance->id, 'userid' => $userid));
                if ($instance->enrolperiod) {
                    $timestart = $ue->timestart;
                    $timeend = $timestart + $instance->enrolperiod;
                    if ($timeend != $ue->timeend) {
                        $plugin->update_user_enrol($instance, $ue->userid, ENROL_USER_ACTIVE, $timestart, $timeend);
                        self::trace(sprintf('User %s enrolment updated', $record->userid));
                    }
                }
            }
            // Always add to group.
            if (!empty($instance->customint2) && $instance->customint2 != \enrol_arlo_plugin::ARLO_CREATE_GROUP) {
                groups_add_member($instance->customint2, $userid, 'enrol_arlo');
            }
        }
        if ($registration->Status == RegistrationStatus::CANCELLED) {
            if ($registration->Status == RegistrationStatus::CANCELLED && ($unenrolaction == ENROL_EXT_REMOVED_UNENROL)) {
                $plugin->unenrol_user($instance, $userid);
                self::trace(sprintf('User %s unenrolled', $record->userid));
            }
            if ($registration->Status == RegistrationStatus::CANCELLED && ($unenrolaction == ENROL_EXT_REMOVED_SUSPENDNOROLES)) {
                $plugin->suspend_and_remove_roles($instance, $userid);
                self::trace(sprintf('User %s suspended', $record->userid));
            }
            if ($registrationrecord) {
                $DB->delete_records('enrol_arlo_registration', array('id' => $registrationrecord->id));
            }
        }
        return true;
    }

    public function process_events($manualoverride = false) {
        global $DB;
        $timestart = microtime();
        if (!self::api_callable()) {
            return false;
        }
        list($platform, $apiusername, $apipassword) = self::get_connection_vars();
        self::trace("Processing Events");
        try {
            $hasnext = true; // Initialise to for multiple pages.
            while ($hasnext) {
                $hasnext = false; // Avoid infinite loop by default.
                // Get schedule information.
                $schedule = self::get_schedule('events', 0);
                if (!$schedule) {
                    self::trace('No matching schedule information');
                    break;
                }
                if (!self::can_pull($schedule , $manualoverride)) {
                    break;
                }
                // Setup RequestUri for getting Events.
                $requesturi = new RequestUri();
                $requesturi->setHost($platform);
                $requesturi->setPagingTop(250);
                $requesturi->setResourcePath('events/');
                $requesturi->addExpand('Event/EventTemplate');
                $latestmodified = $schedule->latestsourcemodified;
                if (empty($latestmodified)) {
                    $servertimezone = \core_date::get_server_timezone();
                    $tz = new \DateTimeZone($servertimezone);
                    $date = \DateTime::createFromFormat('U', 0, $tz);
                    $latestmodified = $date->format(DATE_ISO8601);
                }
                $lastsourceid = isset($schedule->lastsourceid) ? $schedule->lastsourceid : 0;
                // Events end point doesn't like DateTimeOffset.
                $filter = '';
                $filter .= "(LastModifiedDateTime gt datetime('".$latestmodified."'))";
                if ($lastsourceid) {
                    $filter .= " OR (LastModifiedDateTime eq datetime('".$latestmodified."') AND EventID gt ".$lastsourceid.")";
                }
                $requesturi->setFilterBy($filter);
                $requesturi->setOrderBy("LastModifiedDateTime ASC,EventID ASC");
                $options = array();
                $options['auth'] = array(
                    $apiusername,
                    $apipassword
                );
                $headers = self::get_client_identity_headers();
                $request = new \enrol_arlo\request\collection_request($schedule, $requesturi, $headers, null, $options);
                $schedule->lastpulltime = time();
                $response = $request->execute();
                $schedule->lastpulltime = time();
                if (200 != $response->getStatusCode()) {
                    self::trace(sprintf("Bad response (%s) leaving the room.", $response->getStatusCode()));
                    return false;
                }
                $collection = self::deserialize_response_body($response);
                // Any returned.
                if (iterator_count($collection) == 0) {
                    self::update_scheduling_information($schedule);
                    self::trace("No new or updated resources found.");
                } else {
                    foreach ($collection as $event) {
                        $record = self::process_event($event);
                        $latestmodified = $event->LastModifiedDateTime;
                        $schedule->latestsourcemodified = $latestmodified;
                        $lastsourceid = $event->EventID;
                        $schedule->lastsourceid = $lastsourceid;
                    }
                    $hasnext = (bool) $collection->hasNext();
                    $schedule->updatenextpulltime = ($hasnext) ? false : true;
                    self::update_scheduling_information($schedule);
                }
            }
        } catch (\Exception $exception) {
            if (isset($schedule)) {
                $errorcount = (int) $schedule->errorcount;
                $schedule->errorcount = ++$errorcount;
                $schedule->lasterror = $exception->getMessage();
                self::update_scheduling_information($schedule);
            }
            debugging($exception->getMessage(), DEBUG_DEVELOPER, $exception->getTrace());
            return false;
        }
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        self::trace("Execution took {$difftime} seconds");
        return true;
    }

    /**
     * Overrides parent implementation to allow user notification on enrolment expiration.
     *
     * @param $instance
     * @param $userenrolment
     */
    public function process_expiration($instance, $userenrolment) {
        // Deal with expired accounts.
        $action = self::$plugin->get_config('expiredaction', ENROL_EXT_REMOVED_KEEP);
        if ($action == ENROL_EXT_REMOVED_SUSPENDNOROLES or $action == ENROL_EXT_REMOVED_SUSPEND) {
            if ($action == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
                if (!self::$plugin->roles_protected()) {
                    // Let's just guess what roles should be removed.
                    $count = $DB->count_records('role_assignments',
                        array('userid' => $userenrolment->userid, 'contextid' => $userenrolment->contextid));
                    if ($count == 1) {
                        role_unassign_all(array('userid' => $userenrolment->userid,
                            'contextid' => $userenrolment->contextid,
                            'component' => '',
                            'itemid' => 0));

                    } else if ($count > 1 and $instance->roleid) {
                        role_unassign($instance->roleid, $userenrolment->userid, $userenrolment->contextid, '', 0);
                    }
                }
                // In any case remove all roles that belong to this instance and user.
                role_unassign_all(array('userid' => $userenrolment->userid,
                    'contextid' => $userenrolment->contextid,
                    'component' => 'enrol_'.$name,
                    'itemid' => $instance->id), true);
                // Final cleanup of subcontexts if there are no more course roles.
                if (0 == $DB->count_records('role_assignments', ['userid' => $userenrolment->userid, 'contextid' => $userenrolment->contextid])) {
                    role_unassign_all(array('userid' => $userenrolment->userid,
                        'contextid' => $userenrolment->contextid,
                        'component' => '',
                        'itemid' => 0), true);
                }
            }
            // Update the users enrolment status.
            self::$plugin->update_user_enrol($instance, $userenrolment->userid, ENROL_USER_SUSPENDED);
            self::add_email_to_queue($instance->id, $userenrolment->userid, self::EMAIL_TYPE_NOTIFY_EXPIRY);
        }
    }
    /**
     * Process enrolment expirations.
     *
     * TODO - Do we really need this? External source a.k.a Arlo should be
     * in control of enrolment expiration.
     */
    public function process_expirations() {
        global $DB;
        $instances = array(); // Cache.
        $sql = "SELECT ue.*, e.courseid, c.id AS contextid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = :enrol)
                  JOIN {context} c ON (c.instanceid = e.courseid AND c.contextlevel = :courselevel)
                 WHERE ue.timeend > 0 AND ue.timeend < :now
                   AND ue.status = :useractive";
        $conditions = array(
            'now' => time(),
            'courselevel' => CONTEXT_COURSE,
            'useractive' => ENROL_USER_ACTIVE,
            'enrol' => 'arlo'
        );
        $rs = $DB->get_recordset_sql($sql, $conditions);
        foreach ($rs as $ue) {
            if (empty($instances[$ue->enrolid])) {
                $instances[$ue->enrolid] = $DB->get_record('enrol', array('id' => $ue->enrolid));
                $instance = $instances[$ue->enrolid];
                self::process_expiration($instance, $ue);
            }
        }
        $rs->close();
    }

    public function process_onlineactivities($manualoverride = false) {
        global $DB;
        $timestart = microtime();
        if (!self::api_callable()) {
            return false;
        }
        list($platform, $apiusername, $apipassword) = self::get_connection_vars();
        self::trace("Processing Online Activities");
        try {
            $hasnext = true; // Initialise to for multiple pages.
            while ($hasnext) {
                $hasnext = false; // Avoid infinite loop by default.
                // Get schedule information.
                $schedule = self::get_schedule('onlineactivities', 0);
                if (!$schedule) {
                    self::trace('No matching schedule information');
                    break;
                }
                if (!self::can_pull($schedule , $manualoverride)) {
                    break;
                }
                // Setup RequestUri for getting Events.
                $requesturi = new RequestUri();
                $requesturi->setHost($platform);
                $requesturi->setPagingTop(250);
                $requesturi->setResourcePath('onlineactivities/');
                $requesturi->addExpand('OnlineActivity/EventTemplate');
                $latestmodified = $schedule->latestsourcemodified;
                if (empty($latestmodified)) {
                    $servertimezone = \core_date::get_server_timezone();
                    $tz = new \DateTimeZone($servertimezone);
                    $date = \DateTime::createFromFormat('U', 0, $tz);
                    $latestmodified = $date->format(DATE_ISO8601);
                }
                $lastsourceid = isset($schedule->lastsourceid) ? $schedule->lastsourceid : 0;
                // Online Activity end point doesn't like DateTimeOffset.
                $filter = '';
                $filter .= "(LastModifiedDateTime gt datetime('".$latestmodified."'))";
                if ($lastsourceid) {
                    $filter .= " OR (LastModifiedDateTime eq datetime('".$latestmodified."') AND OnlineActivityID gt ".$lastsourceid.")";
                }
                $requesturi->setFilterBy($filter);
                $requesturi->setOrderBy("LastModifiedDateTime ASC,OnlineActivityID ASC");
                $options = array();
                $options['auth'] = array(
                    $apiusername,
                    $apipassword
                );
                $schedule->lastpulltime = time();
                $headers = self::get_client_identity_headers();
                $request = new \enrol_arlo\request\collection_request($schedule, $requesturi, $headers, null, $options);
                $schedule->lastpulltime = time();
                $response = $request->execute();
                if (200 != $response->getStatusCode()) {
                    self::trace(sprintf("Bad response (%s) leaving the room.", $response->getStatusCode()));
                    return false;
                }
                $collection = self::deserialize_response_body($response);
                // Any returned.
                if (iterator_count($collection) == 0) {
                    self::update_scheduling_information($schedule);
                    self::trace("No new or updated resources found.");
                } else {
                    foreach ($collection as $onlineactivity) {
                        $record = self::process_onlineactivity($onlineactivity);
                        $latestmodified = $onlineactivity->LastModifiedDateTime;
                        $schedule->latestsourcemodified = $latestmodified;
                        $lastsourceid = $onlineactivity->OnlineActivityID;
                        $schedule->lastsourceid = $lastsourceid;
                    }
                    $hasnext = (bool) $collection->hasNext();
                    $schedule->updatenextpulltime = ($hasnext) ? false : true;
                    self::update_scheduling_information($schedule);
                }
            }
        } catch (\Exception $exception) {
            if (isset($schedule)) {
                $errorcount = (int) $schedule->errorcount;
                $schedule->errorcount = ++$errorcount;
                $schedule->lasterror = $exception->getMessage();
                self::update_scheduling_information($schedule);
            }
            debugging($exception->getMessage(), DEBUG_DEVELOPER, $exception->getTrace());
            return false;
        }
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        self::trace("Execution took {$difftime} seconds");
        return true;
    }

    public function process_templates($manualoverride = false) {
        global $DB;
        $timestart = microtime();
        if (!self::api_callable()) {
            return false;
        }
        list($platform, $apiusername, $apipassword) = self::get_connection_vars();
        self::trace("Processing Event Templates");
        try {
            $hasnext = true; // Initialise to for multiple pages.
            while ($hasnext) {
                $hasnext = false; // Avoid infinite loop by default.
                // Get schedule information.
                $schedule = self::get_schedule('eventtemplates', 0);
                if (!$schedule) {
                    self::trace('No matching schedule information');
                    break;
                } else {
                    $schedule->lastpulltime = time();
                }
                if (!self::can_pull($schedule , $manualoverride)) {
                    break;
                }
                // Setup RequestUri for getting Templates.
                $requesturi = new RequestUri();
                $requesturi->setHost($platform);
                $requesturi->setResourcePath('eventtemplates/');
                $requesturi->addExpand('EventTemplate');
                $latestmodified = $schedule->latestsourcemodified;
                if (empty($latestmodified)) {
                    $servertimezone = \core_date::get_server_timezone();
                    $tz = new \DateTimeZone($servertimezone);
                    $date = \DateTime::createFromFormat('U', 0, $tz);
                    $latestmodified = $date->format(DATE_ISO8601);
                }
                $lastsourceid = isset($schedule->lastsourceid) ? $schedule->lastsourceid : 0;
                $filter = '';
                $filter .= "(LastModifiedDateTime gt datetimeoffset('".$latestmodified."'))";
                if ($lastsourceid) {
                    $filter .= " OR (LastModifiedDateTime eq datetimeoffset('".$latestmodified."') AND TemplateID gt ".$lastsourceid.")";
                }
                $requesturi->setFilterBy($filter);
                $requesturi->setOrderBy("LastModifiedDateTime ASC,TemplateID ASC");
                $options = array();
                $options['auth'] = array(
                    $apiusername,
                    $apipassword
                );
                $headers = self::get_client_identity_headers();
                $request = new \enrol_arlo\request\collection_request($schedule, $requesturi, $headers, null, $options);
                $schedule->lastpulltime = time();
                $response = $request->execute();
                if (200 != $response->getStatusCode()) {
                    self::trace(sprintf("Bad response (%s) leaving the room.", $response->getStatusCode()));
                    return false;
                }
                $collection = self::deserialize_response_body($response);
                // Any returned.
                if (iterator_count($collection) == 0) {
                    self::update_scheduling_information($schedule);
                    self::trace("No new or updated resources found.");
                } else {
                    foreach ($collection as $template) {
                        $record = self::process_template($template);
                        $latestmodified = $template->LastModifiedDateTime;
                        $schedule->latestsourcemodified = $latestmodified;
                        $lastsourceid = $template->TemplateID;
                        $schedule->lastsourceid = $lastsourceid;
                    }
                    $hasnext = (bool) $collection->hasNext();
                    $schedule->updatenextpulltime = ($hasnext) ? false : true;
                    self::update_scheduling_information($schedule);
                }
            }
        } catch (\Exception $exception) {
            if (isset($schedule)) {
                $errorcount = (int) $schedule->errorcount;
                $schedule->errorcount = ++$errorcount;
                $schedule->lasterror = $exception->getMessage();
                self::update_scheduling_information($schedule);
            }
            debugging($exception->getMessage(), DEBUG_DEVELOPER, $exception->getTrace());
            return false;
        }
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        self::trace("Execution took {$difftime} seconds");
        return true;
    }

    private function deserialize_response_body(Response $response) {
        // Incorrect content-type.
        $contenttype = $response->getHeaderLine('content-type');
        if (strpos($contenttype, 'application/xml') === false) {
            throw new invalidcontent_exception(
                array('contenttype' => $contenttype)
            );
        }
        // Deserialize response body.
        $deserializer = new XmlDeserializer('\enrol_arlo\Arlo\AuthAPI\Resource\\');
        $stream = $response->getBody();
        $contents = $stream->getContents();
        if ($stream->eof()) {
            $stream->rewind(); // Rewind stream.
        }
        // If everything went OK a resource class will be returned.
        return $deserializer->deserialize($contents);
    }

    public function process_event(Event $event) {
        global $DB, $USER;

        $platform               = self::$plugin->get_config('platform');
        $record                 = new \stdClass();
        $record->platform       = $platform;
        $record->sourceid       = $event->EventID;
        $record->sourceguid     = $event->UniqueIdentifier;

        $record->code           = \core_text::substr($event->Code, 0, 32);
        $record->startdatetime  = $event->StartDateTime;
        $record->finishdatetime = $event->FinishDateTime;

        $record->sourcestatus   = $event->Status;
        $record->sourcecreated  = $event->CreatedDateTime;
        $record->sourcemodified = $event->LastModifiedDateTime;
        $record->usermodified   = $USER->id;
        $record->timecreated    = time();
        $record->timemodified   = time();

        $template = $event->getEventTemplate();
        if ($template) {
            $record->sourcetemplateid       = $template->TemplateID;
            $record->sourcetemplateguid     = $template->UniqueIdentifier;
        }

        $params = array(
            'platform'      => $platform,
            'sourceid'      => $record->sourceid,
            'sourceguid'    => $record->sourceguid
        );
        $record->id = $DB->get_field('enrol_arlo_event', 'id', $params);
        if (empty($record->id)) {
            unset($record->id);
            $record->id = $DB->insert_record('enrol_arlo_event', $record);
            self::trace(sprintf('Created: %s', $record->code));
            $systemevent = \enrol_arlo\event\event_created::create(array(
                'objectid' => 1,
                'context' => \context_system::instance(),
                'other' => array(
                    'id' => $record->id,
                    'sourceid' => $record->sourceid,
                    'sourceguid' => $record->sourceguid,
                    'sourcestatus' => $record->sourcestatus,
                    'sourcetemplateid' => $record->sourcetemplateid,
                    'sourcetemplateguid' => $record->sourcetemplateguid
                )
            ));
        } else {
            $DB->update_record('enrol_arlo_event', $record);
            self::trace(sprintf('Updated: %s', $record->code));
            $systemevent = \enrol_arlo\event\event_updated::create(array(
                'objectid' => 1,
                'context' => \context_system::instance(),
                'other' => array(
                    'id' => $record->id,
                    'sourceid' => $record->sourceid,
                    'sourceguid' => $record->sourceguid,
                    'sourcestatus' => $record->sourcestatus,
                    'sourcetemplateid' => $record->sourcetemplateid,
                    'sourcetemplateguid' => $record->sourcetemplateguid
                )
            ));
        }
        $systemevent->trigger();
        return $record;
    }

    public function process_template(EventTemplate $template) {
        global $DB, $USER;

        $platform               = self::$plugin->get_config('platform');
        $record                 = new \stdClass();
        $record->platform       = $platform;
        $record->sourceid       = $template->TemplateID;
        $record->sourceguid     = $template->UniqueIdentifier;
        $record->name           = \core_text::substr($template->Name, 0, 128);
        $record->code           = \core_text::substr($template->Code, 0, 32);
        $record->sourcestatus   = $template->Status;
        $record->sourcecreated  = $template->CreatedDateTime;
        $record->sourcemodified = $template->LastModifiedDateTime;
        $record->usermodified   = $USER->id;
        $record->timecreated    = time();
        $record->timemodified   = time();

        $params = array(
            'platform'      => $platform,
            'sourceid'      => $record->sourceid,
            'sourceguid'    => $record->sourceguid
        );
        $record->id = $DB->get_field('enrol_arlo_template', 'id', $params);
        if (empty($record->id)) {
            unset($record->id);
            $record->id = $DB->insert_record('enrol_arlo_template', $record);
            self::trace(sprintf('Created: %s', $record->name));
        } else {
            $DB->update_record('enrol_arlo_template', $record);
            self::trace(sprintf('Updated: %s', $record->name));
        }
        return $record;
    }

    public function process_onlineactivity(OnlineActivity $onlineactivity) {
        global $DB, $USER;

        $platform               = self::$plugin->get_config('platform');
        $record                 = new \stdClass();
        $record->platform       = $platform;
        $record->sourceid       = $onlineactivity->OnlineActivityID;
        $record->sourceguid     = $onlineactivity->UniqueIdentifier;
        $record->name           = \core_text::substr($onlineactivity->Name, 0, 128);
        $record->code           = \core_text::substr($onlineactivity->Code, 0, 32);
        $record->contenturi     = \core_text::substr($onlineactivity->ContentUri, 0, 256);
        $record->sourcestatus   = $onlineactivity->Status;
        $record->sourcecreated  = $onlineactivity->CreatedDateTime;
        $record->sourcemodified = $onlineactivity->LastModifiedDateTime;
        $record->usermodified   = $USER->id;
        $record->timecreated    = time();
        $record->timemodified   = time();

        $template = $onlineactivity->getEventTemplate();
        if ($template) {
            $record->sourcetemplateid       = $template->TemplateID;
            $record->sourcetemplateguid     = $template->UniqueIdentifier;
        }

        $params = array(
            'platform'      => $platform,
            'sourceid'      => $record->sourceid,
            'sourceguid'    => $record->sourceguid
        );
        $record->id = $DB->get_field('enrol_arlo_onlineactivity', 'id', $params);
        if (empty($record->id)) {
            unset($record->id);
            $record->id = $DB->insert_record('enrol_arlo_onlineactivity', $record);
            self::trace(sprintf('Created: %s', $record->name));
            $systemevent = \enrol_arlo\event\onlineactivity_created::create(array(
                'objectid' => 1,
                'context' => \context_system::instance(),
                'other' => array(
                    'id' => $record->id,
                    'sourceid' => $record->sourceid,
                    'sourceguid' => $record->sourceguid,
                    'sourcestatus' => $record->sourcestatus,
                    'sourcetemplateid' => $record->sourcetemplateid,
                    'sourcetemplateguid' => $record->sourcetemplateguid
                )
            ));
        } else {
            $DB->update_record('enrol_arlo_onlineactivity', $record);
            self::trace(sprintf('Updated: %s', $record->name));
            $systemevent = \enrol_arlo\event\onlineactivity_updated::create(array(
                'objectid' => 1,
                'context' => \context_system::instance(),
                'other' => array(
                    'id' => $record->id,
                    'sourceid' => $record->sourceid,
                    'sourceguid' => $record->sourceguid,
                    'sourcestatus' => $record->sourcestatus,
                    'sourcetemplateid' => $record->sourcetemplateid,
                    'sourcetemplateguid' => $record->sourcetemplateguid
                )
            ));
        }
        $systemevent->trigger();
        return $record;
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
     * Output a progress message.
     *
     * @param $message the message to output.
     * @param int $depth indent depth for this message.
     */
    private function trace($message, $depth = 0) {
        self::$trace->output($message, $depth);
    }
}
