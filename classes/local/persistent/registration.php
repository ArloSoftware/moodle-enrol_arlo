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

namespace enrol_arlo\local\config;

defined('MOODLE_INTERNAL') || die();

use coding_exception;
use enrol_arlo\persistent;


class registration extends persistent {
    /** Table name. */
    const TABLE = 'enrol_arlo_registration';

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
                'type' => PARAM_INT,
                'default' => 0
            ),
            'userid' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'sourceid' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'sourceguid' => array(
                'type' => PARAM_TEXT
            ),
            'attendance' => array(
                'type' => PARAM_TEXT
            ),
            'grade' => array(
                'type' => PARAM_TEXT
            ),
            'outcome' => array(
                'type' => PARAM_TEXT
            ),
            'lastactivity' => array(
                'type' => PARAM_TEXT
            ),
            'progressstatus' => array(
                'type' => PARAM_TEXT
            ),
            'progresspercent' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'sourcestatus' => array(
                'type' => PARAM_TEXT
            ),
            'sourcecreated' => array(
                'type' => PARAM_TEXT,
            ),
            'sourcemodified' => array(
                'type' => PARAM_TEXT
            ),
            'sourcecontactid' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'sourcecontactguid' => array(
                'type' => PARAM_TEXT
            ),
            'sourceeventid' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'sourceeventguid' => array(
                'type' => PARAM_TEXT
            ),
            'sourceonlineactivityid' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'sourceonlineactivityguid' => array(
                'type' => PARAM_TEXT
            ),
            'updateinternal' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'updatesource' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'lastpulltime' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'lastpushtime' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'lasterror' => array(
                'type' => PARAM_TEXT
            ),
            'errorcount' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
        );
    }
}
