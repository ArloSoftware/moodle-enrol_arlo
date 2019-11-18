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
use enrol_arlo\local\config\arlo_plugin_config;
use enrol_arlo\persistent;

class job_persistent extends persistent {

    use enrol_arlo_persistent_trait;

    /** Table name. */
    const TABLE = 'enrol_arlo_scheduledjob';

    /** @var array Supported areas. */
    private static $areas = [
        'site',
        'enrolment'
    ];

    /** @var array Map of Arlo collection/resource names. */
    private static $resources = [
        'Contacts' => 'Contact',
        'ContactMergeRequests' => 'ContactMergeRequest',
        'Events' => 'Event',
        'EventTemplates' => 'EventTemplate',
        'OnlineActivities' => 'OnlineActivity',
        'Registrations' => 'Registration'
    ];

    /** @var array Supported types. */
    private static $types = [
        'event_templates',
        'events',
        'online_activities',
        'contact_merge_requests',
        'memberships',
        'outcomes',
        'contacts'
    ];

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
            'area' => array(
                'type' => PARAM_TEXT
            ),
            'type' => array(
                'type' => PARAM_TEXT
            ),
            'instanceid' => array(
                'type' => PARAM_INT
            ),
            'collection' => array(
                'type' => PARAM_TEXT
            ),
            'endpoint' => array(
                'type' => PARAM_TEXT
            ),
            'lastsourceid' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'lastsourcetimemodified' => array(
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => '1970-01-01T00:00:00Z'
            ),
            'timelastrequest' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'timenextrequestdelay' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'timenorequestsafter' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'timerequestsafterextension' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'errormessage' => array(
                'type' => PARAM_TEXT,
                'null' => NULL_ALLOWED,
                'default' => ''
            ),
            'errorcounter' => array(
                'type' => PARAM_INT,
                'default' => 0
            ),
            'disabled' => array(
                'type' => PARAM_INT,
                'default' => 0
            )
        );
    }

    /**
     * Get resource name based of collection name.
     *
     * @return mixed|string
     * @throws coding_exception
     */
    public function get_resource_name() {
        $collection = $this->raw_get('collection');
        if (!is_null($collection)) {
            return static::$resources[$collection];
        }
        return '';
    }

    /**
     * Return valid areas.
     *
     * @return array
     */
    public static function get_valid_areas() {
        return static::$areas;
    }

    /**
     * Return array of valid types.
     *
     * @return array
     */
    public static function get_valid_types() {
        return static::$types;
    }

    /**
     * Resets all sync state information to their default
     * values.
     *
     * @throws coding_exception
     */
    public function reset_sync_state_information() {
        $properties = static::properties_definition();
        $statefields = [
            'lastsourceid',
            'lastsourcetimemodified',
            'timelastrequest'
        ];
        foreach ($statefields as $statefield) {
            if (isset($properties[$statefield]['default'])) {
                $default = $properties[$statefield]['default'];
                $this->set($statefield, $default);
            }
        }
    }

    /**
     * Set job area.
     *
     * @param $value
     * @return $this
     * @throws coding_exception
     */
    protected function set_area($value) {
        if (!in_array($value, static::$areas)) {
            throw new coding_exception('Invalid area');
        }
        return $this->raw_set('area', $value);
    }

    /**
     * Use to set errors from associated scheduled job.
     *
     * @param array $errors
     * @throws coding_exception
     */
    public function set_errors(array $errors) {
        $errorcounter = $this->get('errorcounter');
        $errorcounter += count($errors);
        $errormessage = implode(PHP_EOL, $errors);
        $this->raw_set('errormessage', $errormessage);
        $this->raw_set('errorcounter', $errorcounter);
    }

    /**
     * Set job type.
     *
     * @param $value
     * @return $this
     * @throws coding_exception
     */
    protected function set_type($value) {
        if (!in_array($value, static::$types)) {
            throw new coding_exception('Invalid type');
        }
        return $this->raw_set('type', $value);
    }

}
