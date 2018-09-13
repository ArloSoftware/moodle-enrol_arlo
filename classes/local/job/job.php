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
 * Job interface.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\job;

use core\lock\lock_config;
use enrol_arlo\local\persistent\job_persistent;
use enrol_arlo\persistent;
use null_progress_trace;

defined('MOODLE_INTERNAL') || die();

abstract class job {

    /** @var TIME_PERIOD_DELAY time in seconds to delay next request. */
    const TIME_PERIOD_DELAY = 900; // 15 Minutes.

    /** @var TIME_PERIOD_EXTENSION time in seconds to extend pass no more requests time. */
    const TIME_PERIOD_EXTENSION = 259200; // 72 Hours.

    /** @var TIME_LOCK_TIMEOUT time in seconds to wait for a lock before giving up. */
    const TIME_LOCK_TIMEOUT = 5; // 5 Seconds.

    protected $errors;

    protected $lasterror;

    protected $jobpersistent;

    protected $reasons;

    public function __construct(persistent $jobpersistent) {
        $this->errors = [];
        $this->jobpersistent = $jobpersistent;
    }

    public function add_error($error) {
        $this->errors[] = $this->lasterror = $error;
    }

    public function get_errors() {
        return $this->errors;
    }

    public function get_last_error() {
        return $this->lasterror;
    }

    public function has_errors() {
        return 0 !== count($this->errors);
    }

    public function add_reasons($reason) {
        $this->reasons[] = $reason;
    }

    public function get_reasons() {
        return $this->reasons;
    }

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

    public function get_job_persistent() {
        return $this->jobpersistent;
    }

    abstract public function run();

    /**
     * Register in DB a scheduled job.
     *
     * @param $area
     * @param $type
     * @param $instanceid
     * @param $endpoint
     * @param $collection
     * @param int $timenorequestsafter
     * @return job_persistent
     * @throws \coding_exception
     */
    public static function register_scheduled_job($area,
                                                  $type,
                                                  $instanceid,
                                                  $endpoint,
                                                  $collection,
                                                  $timenorequestsafter = 0) {
        $job = new job_persistent();
        $conditions = [
            'area' => $area,
            'type' => $type,
            'instanceid' => $instanceid
        ];
        $job->from_record_properties($conditions);
        $job->set('collection', $collection);
        $job->set('endpoint', $endpoint);
        $job->set('timenextrequestdelay', self::TIME_PERIOD_DELAY);
        $job->set('timenorequestsafter', $timenorequestsafter);
        $job->set('timerequestsafterextension', self::TIME_PERIOD_EXTENSION);
        $job->save();
        return $job;
    }

    /**
     * Register site level syncronisation jobs.
     */
    public static function register_site_level_scheduled_jobs() {
        global $SITE;
        // Register Event Templates job.
        static::register_scheduled_job(
            'site',
            'event_templates',
            $SITE->id,
            'eventtemplates/',
            'EventTemplates'
        );
        // Register Events job.
        static::register_scheduled_job(
            'site',
            'events',
            $SITE->id,
            'events/',
            'Events'
        );
        // Register Online Activities job.
        static::register_scheduled_job(
            'site',
            'online_activities',
            $SITE->id,
            'onlineactivities/',
            'OnlineActivities'
        );
        // Register Contact Merge Requests job.
        static::register_scheduled_job(
            'site',
            'contact_merge_requests',
            $SITE->id,
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
}
