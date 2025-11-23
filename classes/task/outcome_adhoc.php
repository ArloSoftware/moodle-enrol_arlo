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

use core\task\adhoc_task;
use enrol_arlo\local\job\outcomes_job;
use null_progress_trace;
use text_progress_trace;

defined('MOODLE_INTERNAL') || die();

/**
 * Processes Arlo Outcomes.
 *
 * @package     enrol_arlo
 * @author      2023 Oscar Nadjar <oscar.nadjar@moodle.com>
 * @copyright   Moodle US
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class outcome_adhoc extends adhoc_task {

    /**
     * Get schedule task human readable name.
     *
     * @return string
     * @throws \coding_exception
     */
    public function get_name() {
        return get_string('outcomesadhoctask', 'enrol_arlo');
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
        $registrationinfo = $this->get_custom_data();
        outcomes_job::process_single_outcome($registrationinfo, $trace);

        return true;
    }
    
    /**
     * Queues this task to run ASAP.
     * 
     * @param string $registrationid
     */
    public static function queue_task(object $event) {
        $task = new self();
        $task->set_custom_data($event);
        $task->set_next_run_time(time());
        \core\task\manager::queue_adhoc_task($task);
    }
}
