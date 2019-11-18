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

/**
 * Job abstract base class.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\job;

use core\lock\lock_config;
use enrol_arlo\local\persistent\job_persistent;
use enrol_arlo\persistent;
use coding_exception;
use null_progress_trace;
use progress_trace;

defined('MOODLE_INTERNAL') || die();

/**
 * Job abstract base class.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class job {

    /** @var int TIME_PERIOD_DELAY time in seconds to delay next request. */
    const TIME_PERIOD_DELAY = 900; // 15 Minutes.

    /** @var int TIME_PERIOD_EXTENSION time in seconds to extend pass no more requests time. */
    const TIME_PERIOD_EXTENSION = 604800; // 7 Days.

    /** @var int TIME_LOCK_TIMEOUT time in seconds to wait for a lock before giving up. */
    const TIME_LOCK_TIMEOUT = 5; // 5 Seconds.

    /** @var string area */
    const AREA = null;

    /** @var string type */
    const TYPE = null;

    /** @var array */
    protected $errors = [];

    /** @var string */
    protected $lasterror;

    /** @var persistent */
    protected $jobpersistent;

    /** @var array */
    protected $reasons = [];

    /** @var null_progress_trace */
    protected $trace;

    /**
     * job constructor.
     *
     * @param persistent $jobpersistent
     */
    public function __construct(persistent $jobpersistent) {
        $this->jobpersistent = $jobpersistent;
        $this->trace = new null_progress_trace();
    }

    /**
     * Return instance of trace class.
     *
     * @return null_progress_trace
     */
    public function get_trace() {
        return $this->trace;
    }

    /**
     * Set type of trace to use.
     *
     * @param progress_trace $trace
     */
    public function set_trace(progress_trace $trace) {
        $this->trace = $trace;
    }

    /**
     * Add error message to stack.
     *
     * @param $error
     */
    public function add_error($error) {
        $this->errors[] = $this->lasterror = $error;
    }

    /**
     * Disable job.
     *
     * @return bool
     * @throws \coding_exception
     * @throws \enrol_arlo\invalid_persistent_exception
     */
    public function disable() {
        $this->jobpersistent->set('disabled', 1);
        return $this->jobpersistent->update();
    }

    /**
     * Enable job.
     *
     * @return bool
     * @throws \coding_exception
     * @throws \enrol_arlo\invalid_persistent_exception
     */
    public function enable() {
        $this->jobpersistent->set('disabled', 0);
        return $this->jobpersistent->update();
    }

    /**
     * Get all error meessages.
     *
     * @return array
     */
    public function get_errors() {
        return $this->errors;
    }

    /**
     * Return last error added.
     *
     * @return mixed
     */
    public function get_last_error() {
        return $this->lasterror;
    }

    /**
     * Check for any errors.
     *
     * @return bool
     */
    public function has_errors() {
        return 0 !== count($this->errors);
    }

    /**
     * @param $reason
     */
    public function add_reasons($reason) {
        $this->reasons[] = $reason;
    }

    /**
     * Can be overridden by to add extra checks.
     *
     */
    public function can_run() {
        return true;
    }

    /**
     * Add reason why did not run. Not an error, due to configuration.
     *
     * @return array
     */
    public function get_reasons() {
        return $this->reasons;
    }

    /**
     * Are there reasons for now running.
     *
     * @return bool
     */
    public function has_reasons() {
        return 0 !== count($this->reasons);
    }

    /**
     * Return an instance of lock factory.
     *
     * @return \core\lock\lock_factory
     * @throws \coding_exception
     */
    public static function get_lock_factory() {
        return lock_config::get_lock_factory('enrol_arlo');
    }

    /**
     * Use type and instanceid from persistent to make a lock resource name.
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_lock_resource() {
        $area = $this->jobpersistent->get('area');
        $type = $this->jobpersistent->get('type');
        $instanceid = $this->jobpersistent->get('instanceid');
        // Enrolments share resource to avoid race conditions.
        if ($area == 'enrolment') {
            return $area . ':' . $instanceid;
        }
        return $area . '/'. $type . ':' . $instanceid;
    }

    /**
     * Return associated job persistent.
     *
     * @return persistent
     */
    public function get_job_persistent() {
        return $this->jobpersistent;
    }

    /**
     * Method job types must implement.
     *
     * @return mixed
     */
    abstract public function run();

    /**
     * Method for registering an job instance.
     *
     * @param $instanceid
     * @param int $timenorequestsafter
     * @param null $timenextrequestdelay
     * @param null $timerequestsafterextension
     * @return job_persistent
     * @throws coding_exception
     */
    public static function register_job_instance($instanceid,
                                                 $endpoint,
                                                 $collection,
                                                 $timenorequestsafter = 0,
                                                 $timenextrequestdelay = null,
                                                 $timerequestsafterextension = null) {
        // Check that all required constants are defined in child classes.
        if (is_null(static::AREA)) {
            throw new coding_exception('Child job class must define area constant');
        }
        if (is_null(static::TYPE)) {
            throw new coding_exception('Child job class must define type constant');
        }
        if (!is_numeric($instanceid)) {
            throw new coding_exception('Parameter instanceid must be a integer');
        }
        $job = new job_persistent();
        $conditions = [
            'area' => static::AREA,
            'type' => static::TYPE,
            'instanceid' => $instanceid
        ];
        $job->from_record_properties($conditions);
        $job->set('collection', $collection);
        $job->set('endpoint', $endpoint);
        $job->set('timenorequestsafter', $timenorequestsafter);
        if (is_null($timenextrequestdelay)) {
            $timenextrequestdelay = static::TIME_PERIOD_DELAY;
        }
        $job->set('timenextrequestdelay', $timenextrequestdelay);
        if (is_null($timerequestsafterextension)) {
            $timerequestsafterextension = static::TIME_PERIOD_EXTENSION;
        }
        $job->set('timerequestsafterextension', $timerequestsafterextension);
        $job->save();
        return $job;
    }

    /**
     * Register site level jobs.
     *
     * @throws coding_exception
     */
    public static function register_site_jobs() {
        event_templates_job::register_job_instance(
            SITEID,
            'eventtemplates/',
            'EventTemplates'
        );
        events_job::register_job_instance(
            SITEID,
            'events/',
            'Events'
        );
        online_activities_job::register_job_instance(
            SITEID,
            'onlineactivities/',
            'OnlineActivities'
        );
        contact_merge_requests_job::register_job_instance(
            SITEID,
            'contactmergerequests/',
            'ContactMergeRequests'
        );
    }

    /**
     * Access attached persistent area.
     *
     * @return mixed
     * @throws \coding_exception
     */
    public function get_area() {
        return $this->jobpersistent->get('area');
    }

    /**
     * Access attached persistent type.
     *
     * @return mixed
     * @throws \coding_exception
     */
    public function get_type() {
        return $this->jobpersistent->get('type');
    }

    /**
     * Access persistent instanceid.
     *
     * @return mixed
     * @throws \coding_exception
     */
    public function get_instanceid() {
        return $this->jobpersistent->get('instanceid');
    }

    /**
     * Job type string identifier.
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_job_run_identifier() {
        return $this->get_area() . '/' . $this->get_type() . ':' . $this->get_instanceid();
    }
}
