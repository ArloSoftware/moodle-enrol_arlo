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

namespace enrol_arlo\local\persistent;

defined('MOODLE_INTERNAL') || die();

use coding_exception;
use enrol_arlo\persistent;

class schedule extends persistent {
    /** Table name. */
    const TABLE = 'enrol_arlo_schedule';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return array(
            'platform' => array(
                'type' => PARAM_TEXT
            ),
            'enrolid' => array(
                'type' => PARAM_TEXT
            ),
            'resourcetype' => array(
                'type' => PARAM_TEXT
            ),
            'latestsourcemodified' => array(
                'type' => PARAM_TEXT
            ),
            'lastsourceid' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'nextpulltime' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'lastpulltime' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'endpulltime' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'nextpushtime' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'lastpushtime' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'endpushtime' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'lasterror' => array(
                'type' => PARAM_TEXT
            ),
            'errorcount' => array(
                'type' => PARAM_INT,
                'default' => 0
            )
        );
    }
}