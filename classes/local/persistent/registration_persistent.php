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
use enrol_arlo\api;
use enrol_arlo\persistent;

class registration_persistent extends persistent {

    /** Table name. */
    const TABLE = 'enrol_arlo_registration';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     * @throws coding_exception
     */
    protected static function define_properties() {
        $pluginconfig = api::get_enrolment_plugin()->get_plugin_config();
        return array(
            'platform' => array(
                'type' => PARAM_TEXT,
                'default' => $pluginconfig->get('platform')
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
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'grade' => array(
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'outcome' => array(
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'lastactivity' => array(
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'progressstatus' => array(
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'progresspercent' => array(
                'type' => PARAM_INT,
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'completeddatetime' => array(
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'sourcestatus' => array(
                'type' => PARAM_TEXT
            ),
            'sourcecreated' => array(
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'sourcemodified' => array(
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
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
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'sourceonlineactivityid' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'sourceonlineactivityguid' => array(
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'updatesource' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'enrolmentfailure' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'timelastrequest' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'errormessage' => array(
                'type' => PARAM_RAW,
                'null' => NULL_ALLOWED,
                'default' => null
            ),
            'errorcounter' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
        );
    }

    /**
     * Add error message and increment counter.
     *
     * @param $value
     * @throws coding_exception
     */
    public function add_error_message($value) {
        $counter = $this->raw_get('errorcounter');
        $this->raw_set('errormessage', $value);
        $this->raw_set('errorcounter', ++$counter);
    }

    /**
     * Get associated contact persistent.
     *
     * @return contact_persistent|false
     * @throws coding_exception
     */
    public function get_contact() {
        if (empty($this->raw_get('sourcecontactguid'))) {
            throw new coding_exception('Property sourcecontactguid not set');
        }
        return contact_persistent::get_record(
            ['sourceguid' => $this->raw_get('sourcecontactguid')]
        );
    }

    /**
     * Get associated event persistent.
     *
     * @return bool|contact_persistent|false
     * @throws coding_exception
     */
    public function get_event() {
        if (empty($this->raw_get('sourceeventguid'))) {
            return false;
        }
        return event_persistent::get_record(
            ['sourceguid' => $this->raw_get('sourceeventguid')]
        );
    }

    /**
     * Get associated online activity persistent.
     *
     * @return bool|contact_persistent|false
     * @throws coding_exception
     */
    public function get_online_activity() {
        if (empty($this->raw_get('sourceonlineactivityguid'))) {
            return false;
        }
        return online_activity_persistent::get_record(
            ['sourceguid' => $this->raw_get('sourceonlineactivityguid')]
        );
    }

    /**
     * Round incoming float.
     *
     * @param $value
     * @return $this
     * @throws coding_exception
     */
    protected function set_progresspercent($value) {
        return $this->raw_set('progresspercent', round($value, 0));
    }

    /**
     * Use format Arlo likes.
     *
     * @param $value
     * @return registration_persistent
     * @throws coding_exception
     */
    protected function set_lastactivity($value) {
        if (is_numeric($value)) {
            $value = date('Y-m-d\TH:i:s.0000000+00:00', $value);
        }
        return $this->raw_set('lastactivity', $value);
    }

}
