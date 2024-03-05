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

use core\message\message;


/**
 * Arlo Retry Log Monitor Task
 *
 * Scheduled task class for monitoring the Arlo API retry log and notifying administrators
 * about new entries.
 *
 * @copyright   Moodle US
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *@package   enrol_arlo
 * @author      2024 Felicia Wilkes <felicia.wilkes@moodle.com>
 */
class api_retry_notification extends \core\task\scheduled_task {

    /**
     * Get the name of the task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('api_retry_notification', 'enrol_arlo');
    }

    /**
     * Execute the task.
     *
     * This task checks for new entries in the Arlo API retry log table enrol_arlo_retrylog and sends notifications to administrators if any are found.
     */
    public function execute() {
        global $CFG;
        require_once($CFG->dirroot . '/enrol/arlo/locallib.php');
        $newentries = check_arlo_api_retry_log();

        if (!empty($newentries)) {
            // Notify all Moodle administrators about the new entries.
            $admins = get_admins();
            $apiretrylogurl = new \moodle_url('/enrol/arlo/admin/apiretries.php');
            $manager = new \enrol_arlo\manager();
            $manager->add_max_redirect_notification_to_queue();
            $message = new message();
            $message->component = 'enrol_arlo';
            $message->name = 'arlo_retry_log_notification';
            $message->userfrom = \core_user::get_noreply_user();
            $message->subject = get_string('arlo_retry_log_subject', 'enrol_arlo');
            $message->fullmessage = get_string('arlo_retry_log_message', 'enrol_arlo', $apiretrylogurl->out());
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml   = get_string('arlo_retry_log_message', 'enrol_arlo', $apiretrylogurl->out());
            foreach ($admins as $admin) {
                $message->userto = $admin;
                message_send($message);
                sendfailurenotification($admin);
            }
        }
    }
}