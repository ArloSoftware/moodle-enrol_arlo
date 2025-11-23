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

/**
 * Arlo Retry counter reset Task
 *
 * Scheduled task class for resetting the Arlo retries error count.
 *
 * @copyright   Moodle US
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package     enrol_arlo
 * @author      2025 Oscar Nadjar <oscar.nadjar@moodle.com>
 */
class reset_retries_task extends \core\task\scheduled_task {

    /**
     * Get the name of the task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('reset_retries_task', 'enrol_arlo');
    }

    /**
     * Execute the task.
     *
     * The tasks cleans the Arlo retries error count.
     */
    public function execute() {
        global $DB;

        set_config('redirectcount', 0, 'enrol_arlo');
        $resetregretretries = get_config('enrol_arlo', 'resetregretries');
        if ($resetregretretries) {
            $DB->set_field_select('enrol_arlo_registration', 'redirectcounter', 0, 'redirectcounter > 0');
        }
    }
        
}