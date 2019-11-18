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
 * Event persistent model.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\persistent;

defined('MOODLE_INTERNAL') || die();

use coding_exception;
use core_date;
use core_text;
use context_system;
use DateTime;
use enrol_arlo\api;
use enrol_arlo\Arlo\AuthAPI\Enum\EventStatus;
use enrol_arlo\event\event_created;
use enrol_arlo\event\event_updated;
use enrol_arlo\local\enum\arlo_type;
use enrol_arlo\persistent;

class event_persistent extends persistent {

    /** Table name. */
    const TABLE = 'enrol_arlo_event';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     * @throws coding_exception
     */
    protected static function define_properties() {
        $pluginconfig = api::get_enrolment_plugin()->get_plugin_config();
        return [
            'platform' => [
                'type' => PARAM_TEXT,
                'default' => $pluginconfig->get('platform')
            ],
            'sourceid' => [
                'type' => PARAM_INT,
                'default' => 0
            ],
            'sourceguid' => [
                'type' => PARAM_TEXT
            ],
            'code' => [
                'type' => PARAM_TEXT
            ],
            'startdatetime' => [
                'type' => PARAM_TEXT
            ],
            'finishdatetime' => [
                'type' => PARAM_TEXT
            ],
            'contenturi' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
            ],
            'sourcestatus' => [
                'type' => PARAM_TEXT
            ],
            'sourcecreated' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
            ],
            'sourcemodified' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
            ],
            'sourcetemplateid' => [
                'type' => PARAM_INT,
                'default' => 0
            ],
            'sourcetemplateguid' => [
                'type' => PARAM_TEXT
            ]
        ];
    }

    /**
     * Method that works out timenorequestafter.
     *
     * @return int
     * @throws coding_exception
     */
    public function get_time_norequests_after() {
        $status = $this->get('sourcestatus');
        $nowepoch = time();
        $finishdatetime = $this->get('finishdatetime');
        if (!empty($status) && !empty($finishdatetime)) {
            if ($status == EventStatus::ACTIVE) {
                $finishdate = new DateTime(
                    $finishdatetime,
                    core_date::get_user_timezone_object()
                );
                $finishepoch = $finishdate->getTimestamp();
                if ($finishepoch > $nowepoch) {
                    return $finishepoch;
                }
            }
        }
        return $nowepoch;
    }

    /**
     * Custom setter for code, just for now until implement truncate support in persistent.
     *
     * @param $value
     * @return $this
     * @throws coding_exception
     */
    protected function set_code($value) {
        $truncated = core_text::substr($value, 0, 32);
        return $this->raw_set('code', $truncated);
    }

    /**
     * Custom setter for contenturi, just for now until implement truncate support in persistent.
     *
     * @param $value
     * @return $this
     * @throws coding_exception
     */
    protected function set_contenturi($value) {
        $truncated = core_text::substr($value, 0, 256);
        return $this->raw_set('contenturi', $truncated);
    }

    /**
     * Fire event created event.
     *
     * @throws \dml_exception
     * @throws coding_exception
     */
    protected function after_create() {
        $data = [
            'objectid' => 1,
            'context' => context_system::instance(),
            'other' => [
                'id' => $this->raw_get('id'),
                'sourceid' => $this->raw_get('sourceid'),
                'sourceguid' => $this->raw_get('sourceguid'),
                'sourcestatus' => $this->raw_get('sourcestatus'),
                'sourcetemplateid' => $this->raw_get('sourcetemplateid'),
                'sourcetemplateguid' => $this->raw_get('sourcetemplateguid')
            ]
        ];
        event_created::create($data)->trigger();
    }

    /**
     * Fire event updated event.
     *
     * @param bool $result
     * @throws \dml_exception
     * @throws coding_exception
     */
    protected function after_update($result) {
        if ($result) {
            $data = [
                'objectid' => 1,
                'context' => context_system::instance(),
                'other' => [
                    'id' => $this->raw_get('id'),
                    'sourceid' => $this->raw_get('sourceid'),
                    'sourceguid' => $this->raw_get('sourceguid'),
                    'sourcestatus' => $this->raw_get('sourcestatus'),
                    'sourcetemplateid' => $this->raw_get('sourcetemplateid'),
                    'sourcetemplateguid' => $this->raw_get('sourcetemplateguid')
                ]
            ];
            event_updated::create($data)->trigger();
        }
    }

}