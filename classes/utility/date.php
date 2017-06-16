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
 * @copyright 2017 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\utility;

class date {
    /**
     * Sets and returns a DateTime class, if Epoch will use timezone as set in Moodle configuration else
     * defaults to UTC timezone which Arlo uses.
     *
     * @param $value
     * @return bool|\DateTime
     */
    public static function create($value) {
        // Epoch or UTC.
        if (is_int($value)){
            $date = \DateTime::createFromFormat('U', $value, \core_date::get_server_timezone());
        } else {
            $date = new \DateTime($value, new \DateTimeZone('UTC'));
        }
        return $date;
    }
}
