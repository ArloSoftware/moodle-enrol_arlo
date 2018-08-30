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
use core_text;
use context_system;
use enrol_arlo\event\event_created;
use enrol_arlo\event\event_updated;
use enrol_arlo\local\config\arlo_plugin_config;
use enrol_arlo\persistent;

class event_persistent extends persistent {

    use enrol_arlo_persistent_trait;

    /** Table name. */
    const TABLE = 'enrol_arlo_event';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     * @throws coding_exception
     */
    protected static function define_properties() {
        $pluginconfig = new arlo_plugin_config();
        return array(
            'platform' => array(
                'type' => PARAM_TEXT,
                'default' => $pluginconfig->get('platform')
            ),
            'sourceid' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'sourceguid' => array(
                'type' => PARAM_TEXT
            ),
            'code' => array(
                'type' => PARAM_TEXT,
                'length' => 32,
                'truncatable' => true
            ),
            'startdatetime' => array(),
            'finishdatetime' => array(),
            'sourcestatus' => array(
                'type' => PARAM_TEXT
            ),
            'sourcecreated' => array(
                'type' => PARAM_TEXT,
            ),
            'sourcemodified' => array(
                'type' => PARAM_TEXT
            ),
            'sourcetemplateid' => array(
            'type' => PARAM_INT,
            'default' => 0
        ),
            'sourcetemplateguid' => array(
            'type' => PARAM_TEXT
        ),
        );
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
     * Fire event created event.
     *
     * @param $result
     * @throws \dml_exception
     * @throws coding_exception
     */
    protected function after_create($result) {
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
            $systemevent = event_created::create($data)->trigger();
        }
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
            $systemevent = event_updated::create($data)->trigger();
        }
    }

}