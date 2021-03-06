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
use enrol_arlo\local\config\arlo_plugin_config;
use enrol_arlo\persistent;

class request_log_persistent extends persistent {

    use enrol_arlo_persistent_trait;

    /** Table name. */
    const TABLE = 'enrol_arlo_requestlog';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     * @throws coding_exception
     */
    protected static function define_properties() {
        $pluginconfig = new arlo_plugin_config();
        return [
            'platform' => [
                'type' => PARAM_TEXT,
                'default' => $pluginconfig->get('platform')
            ],
            'timelogged' => [
                'type' => PARAM_INT
            ],
            'uri' => [
                'type' => PARAM_TEXT
            ],
            'status' => [
                'type' => PARAM_TEXT,
                'default' => 0
            ],
            'extra' => [
                'type' => PARAM_RAW,
                'default' => ''
            ]
        ];
    }
}
