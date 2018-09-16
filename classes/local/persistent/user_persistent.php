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
 * User persistent.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\persistent;

defined('MOODLE_INTERNAL') || die();

use coding_exception;
use core_user;
use enrol_arlo\api;
use enrol_arlo\persistent;

class user_persistent extends persistent {
    /** Table name. */
    const TABLE = 'user';

    protected static function define_properties() {
        $pluginconfig = api::get_enrolment_plugin()->get_plugin_config();
        return [
            'auth' => [
                'type' => PARAM_TEXT,
                'default' => function() use($pluginconfig) {
                    return $pluginconfig->get('authplugin');
                }
            ],
            'mnethostid' => [
                'type' => PARAM_INT,
                'default' => function() {
                    return get_config('core', 'mnet_localhost_id');
                }
            ],
            'firstname' => [
                'type' => PARAM_TEXT,
            ],
            'lastname' => [
                'type' => PARAM_TEXT,
            ],
            'email' => [
                'type' => PARAM_TEXT,
            ],
            'phone1' => [
                'type' => PARAM_TEXT,
                'default' => ''
            ],
            'phone2' => [
                'type' => PARAM_TEXT,
                'default' => ''
            ],
            'idnumber' => [
                'type' => PARAM_TEXT,
                'default' => ''
            ],
        ];
    }

    protected function before_create() {}
    protected function after_create() {}
    protected function before_update() {}
    protected function after_update($result) {}

}