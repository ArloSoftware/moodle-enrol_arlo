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

    protected $jobpersistent;

    public function __construct(persistent $jobpersistent) {
        $this->errors = [];
        $this->jobpersistent = $jobpersistent;
    }

    public function add_error($error) {
        $this->errors[] = $error;
    }

    public function get_errors() {
        return $this->errors;
    }

    public function has_errors() {
        return 0 !== count($this->errors);
    }

    public function get_job_persistent() {
        return $this->jobpersistent;
    }

    abstract public function run();
}
