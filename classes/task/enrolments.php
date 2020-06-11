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

namespace enrol_arlo\task;

use core\task\scheduled_task;
use enrol_arlo\api;
use enrol_arlo\manager;
use null_progress_trace;
use text_progress_trace;

defined('MOODLE_INTERNAL') || die();

/**
 * Create Moodle enrolments based off Arlo registrations.
 *
 * @package     enrol_arlo
 * @copyright   2020 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolments extends scheduled_task {

    /**
     * Get schedule task human readable name.
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_name() {
        return get_string('enrolmentstask', 'enrol_arlo');
    }

    /**
     * Execute the task.
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/enrol/arlo/lib.php');
        if (!enrol_is_enabled('arlo')) {
            return;
        }
        $trace = new null_progress_trace();
        if ($CFG->debug == DEBUG_DEVELOPER) {
            $trace = new text_progress_trace();
        }
        api::run_scheduled_jobs('enrolment', 'memberships', null, 1000, $trace);
        $manager = new manager();
        $manager->process_email_queue();
        return true;
    }

}
