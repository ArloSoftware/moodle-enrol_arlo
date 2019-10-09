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

defined('MOODLE_INTERNAL') || die();

/**
 * Observer configuration file.
 *
 * @package     enrol_arlo
 * @author      Troy Williams
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
$observers = [
    // Watch for a course module being marked complete for a user.
    [
        'eventname'   => '\core\event\course_completed',
        'callback'    => '\enrol_arlo\local\observer::course_completed',
        'includefile' => null,
        'internal'    => false,
        'priority'    => 100
    ],
    // Watch for a course module being marked complete for a user.
    [
        'eventname'   => '\core\event\course_module_completion_updated',
        'callback'    => '\enrol_arlo\local\observer::course_module_completion_updated',
        'includefile' => null,
        'internal'    => false,
        'priority'    => 100
    ],
    // Watch for a course be viewed by a user.
    [
        'eventname'   => '\core\event\course_viewed',
        'callback'    => '\enrol_arlo\local\observer::course_viewed',
        'includefile' => null,
        'internal'    => false,
        'priority'    => 100
    ],
    // Watch for when a user is deleted from system.
    [
        'eventname'   => '\core\event\user_deleted',
        'callback'    => '\enrol_arlo\local\observer::user_deleted',
        'includefile' => null,
        'internal'    => false,
        'priority'    => 100
    ],
    // Watch for when a user recieves grade for a grade item in grade book.
    [
        'eventname'   => '\core\event\user_graded',
        'callback'    => '\enrol_arlo\local\observer::user_graded',
        'includefile' => null,
        'internal'    => false,
        'priority'    => 100
    ],
    // Plugin event: A new Event fetched from Arlo and created in Moodle.
    [
        'eventname'   => '\enrol_arlo\event\event_created',
        'callback'    => '\enrol_arlo\local\observer::event_created',
        'includefile' => null,
        'internal'    => true,
        'priority'    => 9999
    ],
    // Plugin event: Arlo platform changed.
    [
        'eventname'   => '\enrol_arlo\event\fqdn_updated',
        'callback'    => '\enrol_arlo\local\observer::fqdn_updated',
        'includefile' => null,
        'internal'    => true,
        'priority'    => 9999
    ],
    // Plugin event: A new OnlineActivity fetched from Arlo and created in Moodle.
    [
        'eventname'   => '\enrol_arlo\event\onlineactivity_created',
        'callback'    => '\enrol_arlo\local\observer::onlineactivity_created',
        'includefile' => null,
        'internal'    => true,
        'priority'    => 9999
    ]

];
