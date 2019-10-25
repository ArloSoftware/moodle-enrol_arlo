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
 * Contact persistent.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\persistent;

defined('MOODLE_INTERNAL') || die();

use coding_exception;
use enrol_arlo\api;
use enrol_arlo\Arlo\AuthAPI\Enum\ContactStatus;
use enrol_arlo\persistent;

class contact_persistent extends persistent {

    /** Table name. */
    const TABLE = 'enrol_arlo_contact';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     * @throws \coding_exception
     */
    protected static function define_properties() {
        $pluginconfig = api::get_enrolment_plugin()->get_plugin_config();
        return [
            'platform' => [
                'type' => PARAM_TEXT,
                'default' => $pluginconfig->get('platform')
            ],
            'userid' => [
                'type' => PARAM_INT,
                'default' => 0
            ],
            'sourceid' => [
                'type' => PARAM_INT,
            ],
            'sourceguid' => [
                'type' => PARAM_TEXT
            ],
            'firstname' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
            ],
            'lastname' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
            ],
            'email' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
            ],
            'phonework' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
            ],
            'phonemobile' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
            ],
            'codeprimary' => [
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
            ],
            'sourcestatus' => [
                'type' => PARAM_TEXT,
                'default' => ContactStatus::UNKNOWN
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
            'usercreationfailure' => [
                'type' => PARAM_INT,
                'default' => 0
            ],
            'userassociationfailure' => [
                'type' => PARAM_INT,
                'default' => 0
            ],
            'errormessage' => array(
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => ''
            ),
            'errorcounter' => array(
                'type' => PARAM_INT,
                'default' => 0
            )
        ];
    }

    /**
     * Return the associated Moodle user account linked to this Arlo contact.
     *
     * @return bool|user_persistent
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function get_associated_user() {
        return user_persistent::get_record_and_unset(['id' => $this->raw_get('userid')]);
    }

}
