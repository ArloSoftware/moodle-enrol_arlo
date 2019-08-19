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

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

// Observe events configuration.
$observers = array(
    array(
        'eventname' => '\core\event\course_module_completion_updated',
        'callback' => '\enrol_arlo\event\observer::course_module_completion_updated'
    ),
    array(
        'eventname' => '\core\event\course_viewed',
        'callback' => '\enrol_arlo\event\observer::course_viewed'
    ),
    array(
        'eventname' => '\core\event\user_deleted',
        'callback' => '\enrol_arlo\event\observer::user_deleted'
    ),
    array(
        'eventname' => '\core\event\user_graded',
        'callback' => '\enrol_arlo\event\observer::user_graded'
    ),
    array(
        'eventname'   => '\enrol_arlo\event\fqdn_updated',
        'callback'    => '\enrol_arlo\event\observer::fqdn_updated',
    ),
    array(
        'eventname'   => '\enrol_arlo\event\event_created',
        'callback'    => '\enrol_arlo\event\observer::event_created',
    ),
    array(
        'eventname'   => '\enrol_arlo\event\onlineactivity_created',
        'callback'    => '\enrol_arlo\event\observer::onlineactivity_created',
    )
);
