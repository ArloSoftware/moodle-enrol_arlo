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
use core_text;
use enrol_arlo\api;
use enrol_arlo\persistent;

class event_template_persistent extends persistent {

    /** Table name. */
    const TABLE = 'enrol_arlo_template';

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
            'sourceid' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'sourceguid' => array(
                'type' => PARAM_TEXT
            ),
            'name' => array(
                'type' => PARAM_TEXT,
                'length' => 128,
                'truncatable' => true
            ),
            'code' => array(
                'type' => PARAM_TEXT,
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
            )
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
     * Custom setter for name, just for now until implement truncate support in persistent.
     *
     * @param $value
     * @return $this
     * @throws coding_exception
     */
    protected function set_name($value) {
        $truncated = core_text::substr($value, 0, 128);
        return $this->raw_set('name', $truncated);
    }
}
