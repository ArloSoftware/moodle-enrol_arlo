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
 * @copyright 2023 Moodle US
 * @author    Nathan Hunt {nathan.hunt@moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\persistent;

defined('MOODLE_INTERNAL') || die();

use coding_exception;
use core_text;
use enrol_arlo\local\config\arlo_plugin_config;
use enrol_arlo\persistent;

class retry_log_persistent extends persistent {

    use enrol_arlo_persistent_trait;

    /** Table name. */
    const TABLE = 'enrol_arlo_retrylog';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     * @throws coding_exception
     */
    protected static function define_properties() {
        $pluginconfig = new arlo_plugin_config();
        return [
            'timelogged' => [
                'type' => PARAM_INT
            ],
            'userid' => [
                'type' => PARAM_INT
            ],
            'participantname' => [
                'type' => PARAM_TEXT
            ],
            'courseid' => [
                'type' => PARAM_INT,
                'default' => 0
            ],
            'coursename' => [
                'type' => PARAM_TEXT,
                'default' => ''
            ]
        ];
    }
}
