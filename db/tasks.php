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
 * Definition for scheduled tasks.
 *
 * @author      Troy Williams
 * @package     local_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright   2017 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'enrol_arlo\task\core',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '*/1',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
        'disabled' => 0
    ],
    [
        'classname' => 'enrol_arlo\task\enrolments',
        'blocking' => 0,
        'minute' => '59',
        'hour' => '23',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
        'disabled' => 0
    ],
    [
        'classname' => 'enrol_arlo\task\outcomes',
        'blocking' => 0,
        'minute' => '*',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
        'disabled' => 0
    ],
    [
        'classname' => 'enrol_arlo\task\contacts',
        'blocking' => 0,
        'minute' => '*',
        'hour' => '5',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
        'disabled' => 0
    ],
    [
        'classname' => 'enrol_arlo\task\daily',
        'blocking' => 0,
        'minute' => '*',
        'hour' => '5',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
        'disabled' => 0
    ],
    [
        'classname' => 'enrol_arlo\task\api_retry_notification',
        'blocking' => 0,
        'minute' => '0', // Specifies that the task should run when the minute is 0 (midnight).
        'hour' => '0', // Specifies that the task should run when the hour is 0 (midnight).
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ]
];
