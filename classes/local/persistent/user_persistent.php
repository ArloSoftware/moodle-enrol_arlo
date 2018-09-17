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
use core_text;
use enrol_arlo\api;
use enrol_arlo\persistent;
use stdClass;

class user_persistent extends persistent {
    /** Table name. */
    const TABLE = 'user';

    protected static function define_properties() {
        $pluginconfig = api::get_enrolment_plugin()->get_plugin_config();
        return  [
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
            'username' => [
                'type' => PARAM_USERNAME
            ],
            'newpassword' => [
                'type' => PARAM_TEXT
            ],
            'firstname' => [
                'type' => PARAM_TEXT
            ],
            'lastname' => [
                'type' => PARAM_TEXT
            ],
            'email' => [
                'type' => PARAM_EMAIL
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
            'calendartype' => [
                'type' => PARAM_TEXT,
                'default' => core_user::get_property_default('calendartype')
            ],
            'maildisplay' => [
                'type' => PARAM_INT,
                'default' => core_user::get_property_default('maildisplay')
            ],
            'mailformat' => [
                'type' => PARAM_INT,
                'default' => core_user::get_property_default('mailformat')
            ],
            'maildigest' => [
                'type' => PARAM_INT,
                'default' => core_user::get_property_default('maildigest')
            ],
            'autosubscribe' => [
                'type' => PARAM_INT,
                'default' => core_user::get_property_default('autosubscribe')
            ],
            'trackforums' => [
                'type' => PARAM_INT,
                'default' => core_user::get_property_default('trackforums')
            ],
            'lang' => [
                'type' => PARAM_LANG,
                'default' => core_user::get_property_default('lang')
            ],
        ];
    }

    /**
     * Workaround method to load limited record. Want to take advantage of functionality
     * in persistent but no easy way import all of core_user property definition.
     *
     * @param int $id
     * @param stdClass|null $record
     * @return static
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function create_from($id = 0, stdClass $record = null) {
        global $DB;
        if ($id > 0) {
            $record = $DB->get_record(static::TABLE, ['id' => $id]);
        }
        if (!empty($record)) {
            $compactedrecord = new stdClass();
            $properties = array_keys(static::properties_definition());
            foreach (get_object_vars($record) as $property => $value) {
                if (in_array($property, $properties)) {
                    $compactedrecord->{$property} = $value;
                }
            }
            return new static(0, $compactedrecord);

        }
        return new static();
    }

    /**
     * Clean and set username, check precision.
     *
     * @param $value
     * @return $this
     * @throws coding_exception
     */
    protected function set_username($value) {
        $cleanedvalue = clean_param($value, PARAM_USERNAME);
        if (core_text::strlen($cleanedvalue) > 100) {
            throw new coding_exception('Username exceeds length of 100.');
        }
        return $this->raw_set('username', $cleanedvalue);
    }

    /**
     * Set first name and check precision.
     *
     * @param $value
     * @return $this
     * @throws coding_exception
     */
    protected function set_firstname($value) {
        if (core_text::strlen($value) > 100) {
            throw new coding_exception('Firstname exceeds length of 100.');
        }
        return $this->raw_set('firstname', $value);
    }

    /**
     * Set last name and check precision.
     *
     * @param $value
     * @return $this
     * @throws coding_exception
     */
    protected function set_lastname($value) {
        if (core_text::strlen($value) > 100) {
            throw new coding_exception('Lastname exceeds length of 100.');
        }
        return $this->raw_set('lastname', $value);
    }

    /**
     * Set email and check precision.
     *
     * @param $value
     * @return $this
     * @throws coding_exception
     */
    protected function set_email($value) {
        if (core_text::strlen($value) > 100) {
            throw new coding_exception('Email exceeds length of 100.');
        }
        return $this->raw_set('email', $value);
    }

    /**
     * Set Phone1, truncate if required.
     *
     * @param $value
     * @return $this
     * @throws coding_exception
     */
    protected function set_phone1($value) {
        $truncated = core_text::substr($value, 0, 20);
        return $this->raw_set('phone1', $truncated);
    }

    /**
     * Set Phone2, truncate if required.
     *
     * @param $value
     * @return $this
     * @throws coding_exception
     */
    protected function set_phone2($value) {
        $truncated = core_text::substr($value, 0, 20);
        return $this->raw_set('phone2', $truncated);
    }

    /**
     * Set ID number, check precision.
     *
     * @param $value
     * @return $this
     * @throws coding_exception
     */
    protected function set_idnumber($value) {
        if (core_text::strlen($value) > 255) {
            throw new coding_exception('ID number exceeds length of 255.');
        }
        return $this->raw_set('idnumber', $value);
    }

    public function has_accessed() {}
    public function has_accessed_courses() {}
    public function has_enrolments() {}
    protected function before_create() {}
    protected function after_create() {}
    protected function before_update() {}
    protected function after_update($result) {}

}