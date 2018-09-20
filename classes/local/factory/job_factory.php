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
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\factory;

use coding_exception;
use enrol_arlo\local\persistent\job_persistent;
use enrol_arlo\persistent;

defined('MOODLE_INTERNAL') || die();

class job_factory {

    /**
     * Construct and return associated worker class based off persistent.
     *
     * @param persistent $persistent
     * @return mixed
     * @throws \coding_exception
     */
    public static function create_from_persistent(persistent $persistent) {
        $classname = $persistent->get('type') . '_job';
        $namespaceclassname = "enrol_arlo\\local\\job\\{$classname}";
        return new $namespaceclassname($persistent);
    }

    /**
     * Get job based on persistent properties.
     *
     * @param array $parameters
     * @return mixed
     * @throws coding_exception
     */
    public static function get_job(array $parameters) {
        $jobpersistent = job_persistent::get_record($parameters);
        if (!$jobpersistent) {
            throw new coding_exception('Job persistent not found.');
        }
        return static::create_from_persistent($jobpersistent);
    }

}
