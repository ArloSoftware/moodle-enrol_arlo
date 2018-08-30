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

defined('MOODLE_INTERNAL') || die();

abstract class job {

    protected $errors;

    protected $jobpersistent;

    protected $processed;

    public function add_error($error) {
        $this->errors[] = $error;
    }

    public function __construct(persistent $jobpersistent) {
        $this->errors = [];
        $this->jobpersistent = $jobpersistent;
        $this->processed = 0;
    }

    public function get_job_persistent() {
        return $this->jobpersistent;
    }

    public function get_errors() {
        return $this->errors;
    }

    abstract protected function run();
}
