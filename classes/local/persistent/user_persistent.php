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
use context_helper;
use context_user;
use core_user;
use core_text;
use moodle_exception;
use enrol_arlo\api;
use enrol_arlo\manager;
use enrol_arlo\persistent;
use stdClass;

/**
 * User persistent.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => null
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
            'firstnamephonetic' => [
                'type' => PARAM_TEXT,
                'default' => '',
                'null' => NULL_ALLOWED
            ],
            'lastnamephonetic' => [
                'type' => PARAM_TEXT,
                'default' => '',
                'null' => NULL_ALLOWED
            ],
            'middlename' => [
                'type' => PARAM_TEXT,
                'default' => '',
                'null' => NULL_ALLOWED
            ],
            'alternatename' => [
                'type' => PARAM_TEXT,
                'default' => '',
                'null' => NULL_ALLOWED
            ],
            'confirmed' => [
                'type' => PARAM_INT,
                'default' => 1
            ],
            'deleted' => [
                'type' => PARAM_INT,
                'default' => 0
            ],
            'suspended' => [
                'type' => PARAM_INT,
                'default' => 0
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
            'lastaccess' => [
                'type' => PARAM_INT,
                'default' => 0
            ]
        ];
    }

    /**
     * Cheating the AR pattern here. Want to take advantage of functionality
     * in persistent but no easy way import all of core_user property definition.
     *
     * user_persistent constructor.
     * @param int $id
     * @param stdClass|null $record
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function __construct($id = 0, stdClass $record = null) {
        global $DB;

        if ($id > 0) {
            $record = $DB->get_record(static::TABLE, ['id' => $id]);
        }
        if (!empty($record)) {
            static::unset_unused_properties($record);
        }
        parent::__construct(0, $record);
    }

    /**
     * Unset unused user properties.
     *
     * @param stdClass $record
     * @throws coding_exception
     */
    protected static function unset_unused_properties(stdClass $record) {
        $properties = array_keys(static::properties_definition());
        foreach (get_object_vars($record) as $property => $value) {
            if (!in_array($property, $properties)) {
                unset($record->{$property});
            }
        }
    }

    /**
     * Cheating the AR pattern here. Want to take advantage of functionality
     * in persistent but no easy way import all of core_user property definition.
     *
     * @param array $filters
     * @return bool|user_persistent
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function get_record_and_unset($filters = array()) {
        global $DB;

        $record = $DB->get_record(static::TABLE, $filters);
        if (!empty($record)) {
            static::unset_unused_properties($record);
        }
        return $record ? new static(0, $record) : false;
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
            throw new coding_exception('invalidpropertyprecision', 'user_persistent:username');
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
            throw new coding_exception('invalidpropertyprecision', 'user_persistent:firstname');
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
            throw new coding_exception('invalidpropertyprecision', 'user_persistent:lastname');
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
            throw new coding_exception('invalidpropertyprecision', 'user_persistent:email');
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
            throw new coding_exception('invalidpropertyprecision', 'user_persistent:idnumber');
        }
        return $this->raw_set('idnumber', $value);
    }

    /**
     * Deleted property is read-only.
     *
     * @param $value
     */
    protected function set_deleted($value) {
        return;
    }

    /**
     * Has user accessed Moodle.
     *
     * @return bool
     * @throws coding_exception
     */
    public function has_accessed() {
        return ($this->raw_get('lastaccess')) ? true : false;
    }

    /**
     * Has user accessed courses.
     *
     * @return bool
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function has_accessed_courses() {
        global $DB;
        if ($this->raw_get('id') <= 0) {
            throw new coding_exception('reguiredfieldmissing', 'user_persistent:id');
        }
        $coursesaccessed = $DB->count_records('user_lastaccess', ['userid' => $this->raw_get('id')]);
        return ($coursesaccessed) ? true : false;
    }

    /**
     * Wrapper method for count enrolled courses.
     *
     * @return bool
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function has_course_enrolments() {
        return ($this->count_enrolled_courses(false)) ? true : false;
    }

    /**
     * Count the users enrolled courses.
     *
     * @param bool $onlyactive
     * @return int
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function count_enrolled_courses($onlyactive = false) {
        global $DB;
        if ($this->raw_get('id') <= 0) {
            throw new coding_exception('requiredfieldmissing');
        }
        $userid = $this->get('id');
        $params = ['siteid' => SITEID, 'userid' => $userid];
        if ($onlyactive) {
            $subwhere = "WHERE ue.status = :active AND e.status = :enabled";
            $params['active']  = ENROL_USER_ACTIVE;
            $params['enabled'] = ENROL_INSTANCE_ENABLED;
        } else {
            $subwhere = "";
        }
        $ccjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
        $params['contextlevel'] = CONTEXT_COURSE;
        // SQL sub select due to Oracle and MS limitations.
        $sql = "SELECT COUNT(1)
                  FROM {course} c
                  JOIN (SELECT DISTINCT e.courseid
                          FROM {enrol} e
                          JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = :userid)
                          $subwhere
                        ) en ON (en.courseid = c.id)
                  $ccjoin
                 WHERE c.id <> :siteid";
        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Get courses the user is enrolled in.
     *
     * @param bool $onlyactive
     * @param bool $onlyarlo
     * @return array
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function get_enrolled_courses($onlyactive = false, $onlyarlo = false) {
        global $DB;
        if ($this->raw_get('id') <= 0) {
            throw new coding_exception('requiredfieldmissing');
        }
        $userid = $this->get('id');
        $params = ['siteid' => SITEID, 'userid' => $userid];
        $coursefields = [
            'id',
            'category',
            'sortorder',
            'shortname',
            'fullname',
            'idnumber',
            'startdate',
            'visible'
        ];
        $subwheres = [];
        if ($onlyactive) {
            $subwheres[] = "ue.status = :active AND e.status = :enabled";
            $params['active']  = ENROL_USER_ACTIVE;
            $params['enabled'] = ENROL_INSTANCE_ENABLED;
        }
        if ($onlyarlo) {
            $subwheres[] = "e.enrol = :enrol";
            $params['enrol'] = 'arlo';
        }
        $subwhere = '';
        if ($subwheres) {
            $subwhere = "WHERE " . implode(" AND ", $subwheres);
        }
        $coursefields = 'c.' .join(',c.', $coursefields);
        $ccselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
        $ccjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
        $params['contextlevel'] = CONTEXT_COURSE;
        $params['ulauserid']  = $userid;
        // SQL sub select due to Oracle and MS limitations.
        $sql = "SELECT $coursefields $ccselect, ula.timeaccess
                  FROM {course} c
                  JOIN (SELECT DISTINCT e.courseid
                          FROM {enrol} e
                          JOIN {user_enrolments} ue ON (ue.enrolid = e.id AND ue.userid = :userid)
                          $subwhere
                        ) en ON (en.courseid = c.id)
                  $ccjoin
             LEFT JOIN {user_lastaccess} ula ON ula.courseid = c.id AND ula.userid = :ulauserid
                 WHERE c.id <> :siteid
              ORDER BY c.sortorder ASC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Make sure properties are set to coorect value before creation.
     *
     * @throws coding_exception
     */
    protected function before_create() {
        $this->set('confirmed', 1);
    }

    /**
     * Set user context, email and trigger new user event.
     *
     * @throws \dml_exception
     * @throws coding_exception
     */
    protected function after_create() {
        $pluginconfig = api::get_enrolment_plugin()->get_plugin_config();
        $newuserid = $this->get('id');
        // Create a context for this user.
        context_user::instance($newuserid);
        // Send email. TODO refactor messaging.
        $manager = new manager();
        if ($pluginconfig->get('emailsendnewaccountdetails')) {
            if ($pluginconfig->get('emailsendimmediately')) {
                $status = $manager->email_newaccountdetails(null, $this->to_record());
                $deliverystatus = ($status) ? manager::EMAIL_STATUS_DELIVERED : manager::EMAIL_STATUS_FAILED;
                $manager->add_email_to_queue('site', SITEID, $this->to_record()->id,
                    manager::EMAIL_TYPE_NEW_ACCOUNT, $deliverystatus);
            } else {
                $manager->add_email_to_queue('site', SITEID, $this->to_record()->id,
                    manager::EMAIL_TYPE_NEW_ACCOUNT);
            }
        }
        // Trigger new user event.
        \core\event\user_created::create_from_userid($newuserid)->trigger();
    }

    /**
     * Fire update event.
     *
     * @param bool $result
     * @throws coding_exception
     */
    protected function after_update($result) {
        if ($result) {
            $userid = $this->get('id');
            // Trigger updated user event.
            \core\event\user_updated::create_from_userid($userid)->trigger();
        }
    }

}
