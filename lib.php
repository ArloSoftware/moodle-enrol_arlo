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
 * Arlo enrolment plugin class.
 *
 * @package     enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright   2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/vendor/autoload.php');
require_once($CFG->dirroot . '/group/lib.php');

use enrol_arlo\Arlo\AuthAPI\Enum\EventStatus;
use enrol_arlo\Arlo\AuthAPI\Enum\OnlineActivityStatus;
use enrol_arlo\Arlo\AuthAPI\Enum\EventTemplateStatus;
use enrol_arlo\local\external;
use enrol_arlo\manager;
use enrol_arlo\local\config\arlo_plugin_config;
use enrol_arlo\local\enum\arlo_type;
use enrol_arlo\local\factory\job_factory;
use enrol_arlo\local\persistent\job_persistent;
use enrol_arlo\local\persistent\event_persistent;
use enrol_arlo\local\persistent\online_activity_persistent;
use enrol_arlo\local\job\memberships_job;
use enrol_arlo\local\job\contacts_job;
use enrol_arlo\local\job\outcomes_job;

/**
 * DateTimeOffset format yyyy-mm-ddThh:mm:ss.fffffffzzzz.
 *
 * API requires 7 digits of precision for microtime however PHP supports 6 digits
 * of precision so we pad the 7th with a zero.
 */
define('ENROL_ARLO_DATETIME_OFFSET_FORMAT', 'Y-m-d\TH:i:s.u0P');

/**
 * Arlo enrolment plugin class.
 *
 * @package     enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright   2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_arlo_plugin extends enrol_plugin {

    /** @var int CREATE_GROUP */
    const CREATE_GROUP = -1;

    /**
     * Get the release version.
     *
     * @return mixed
     */
    public function get_plugin_release() {
        $pluginmanager = core_plugin_manager::instance();
        $information = $pluginmanager->get_plugin_info('enrol_arlo');
        return $information->release;
    }

    /**
     * Easy access to plugin config.
     *
     * @return arlo_plugin_config
     */
    public function get_plugin_config() {
        static $pluginconfig;
        if (is_null($pluginconfig)) {
            $pluginconfig = new arlo_plugin_config();
        }
        return $pluginconfig;
    }

    /**
     * Mapped custom fields to human readables.
     *
     * @return array
     */
    public static function get_custom_property_map() {
        return [
            'customint2' => 'groupid',
            'customint8' => 'sendcoursewelcomemessage',
            'customchar1' => 'platform',
            'customchar2' => 'arlotype',
            'customchar3' => 'sourceguid',
            'customtext1' => 'customcoursewelcomemessage'
        ];
    }

    /**
     * Wrapper for the parent enrol user method.
     *
     * @param stdClass $instance
     * @param stdClass $user
     * @throws coding_exception
     * @throws dml_exception
     */
    public function enrol(stdClass $instance, stdClass $user) {
        global $DB;
        $pluginconfig = $this->get_plugin_config();
        $timestart = time();
        $timeend = 0;
        // Handle enrolment period.
        if ($instance->enrolperiod) {
            $timeend = $timestart + $instance->enrolperiod;
        }
        // Check if there existing enrolment before running parent method.
        $conditions = ['enrolid' => $instance->id, 'userid' => $user->id];
        $existinguserenrolment = $DB->record_exists('user_enrolments', $conditions);
        // Always update enrolment status, times and group.
        parent::enrol_user($instance,  $user->id, $instance->roleid, $timestart, $timeend, ENROL_USER_ACTIVE);
        if (!empty($instance->customint2) && $instance->customint2 != self::CREATE_GROUP) {
            $exists = $DB->record_exists('groups', ['id' => $instance->customint2]);
            if ($exists) {
                groups_add_member($instance->customint2, $user->id, 'enrol_arlo');
            }
        }
        // Do not send welcome email for users that have a user enrolment both active and inactive.
        if (!$existinguserenrolment) {
            $manager = new manager();
            if ($instance->customint8) {
                if ($pluginconfig->get('emailsendimmediately')) {
                    $status = $manager->email_coursewelcome($instance, $user);
                    $deliverystatus = ($status) ? manager::EMAIL_STATUS_DELIVERED : manager::EMAIL_STATUS_FAILED;
                    $manager->add_email_to_queue('enrolment', $instance->id, $user->id,
                        manager::EMAIL_TYPE_COURSE_WELCOME, $deliverystatus);
                } else {
                    $manager->add_email_to_queue('enrolment', $instance->id, manager::EMAIL_TYPE_COURSE_WELCOME);
                }
            }
        }
    }

    /**
     * Wrapper to direct to appropriate parent method.
     *
     * @param stdClass $instance
     * @param stdClass $user
     * @param int $unenrolactionoverride
     * @throws coding_exception
     */
    public function unenrol(stdClass $instance, stdClass $user, $unenrolactionoverride = null) {
        $pluginconfig = $this->get_plugin_config();
        $unenrolaction = $pluginconfig->get('unenrolaction');
        if (!is_null($unenrolactionoverride)) {
            $unenrolaction = $unenrolactionoverride;
        }
        if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
            parent::unenrol_user($instance, $user->id);
        }
        if ($unenrolaction == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
            $this->suspend_and_remove_roles($instance, $user->id);
        }
    }

    /**
     * Get a Arlo instance enrolment record based on id.
     *
     * @param $id
     * @param int $strictness
     * @return mixed
     * @throws dml_exception
     */
    public static function get_instance_record($id, $strictness = IGNORE_MISSING) {
        global $DB;
        $conditions = [
            'id' => $id,
            'enrol' => 'arlo'
        ];
        return $DB->get_record('enrol', $conditions, '*', $strictness);
    }

    /**
     * Check if instance exists based on passed in conditions. @TODO fix.
     *
     * @param $conditions
     * @return bool
     * @throws dml_exception
     */
    public static function instance_exists($conditions) {
        global $DB;
        $conditions = array_merge($conditions, ['enrol' => 'arlo']);
        return $DB->record_exists('enrol', $conditions);
    }

    /**
     * Add new instance of enrol plugin with default settings.
     *
     * @param object $course
     * @return int
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function add_default_instance($course) {
        $fields = $this->get_instance_defaults();
        return $this->add_instance($course, $fields);
    }

    /**
     * Add new instance of enrol plugin.
     *
     * @param object $course
     * @param array|null $fields
     * @return int
     * @throws ReflectionException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function add_instance($course, array $fields = null) {
        $pluginconfig = new arlo_plugin_config();
        $fields['roleid'] = $pluginconfig->get('roleid');
        $fields['customchar1'] = $pluginconfig->get('platform');
        if (empty($fields['arlotype'])) {
            throw new moodle_exception('Field arlotype is empty.');
        }
        // Get information we need from Event to save against instance record.
        if ($fields['arlotype'] == arlo_type::EVENT) {
            if (empty($fields['arloevent'])) {
                throw new moodle_exception('Field arloevent is empty.');
            }
            $conditions = [
                'sourceguid' => $fields['arloevent']
            ];
            $persistent = event_persistent::get_record($conditions);
            if (!$persistent) {
                throw new moodle_exception('No related record');
            }
            $fields['name'] = $persistent->get('code');
            $endpoint = 'events/' . $persistent->get('sourceid') . '/registrations/';
            $collection = 'Events';
            $fields['customchar2'] = $fields['arlotype'];
            $fields['customchar3'] = $persistent->get('sourceguid');
        }
        // Get information we need from Online Activity to save against instance record.
        if ($fields['arlotype'] == arlo_type::ONLINEACTIVITY) {
            if (empty($fields['arloonlineactivity'])) {
                throw new moodle_exception('Field arloonlineactivity is empty.');
            }
            $conditions = [
                'sourceguid' => $fields['arloonlineactivity']
            ];
            $persistent = online_activity_persistent::get_record($conditions);
            if (!$persistent) {
                throw new moodle_exception('No related record');
            }
            $fields['name'] = $persistent->get('code');
            $endpoint = 'onlineactivities/' . $persistent->get('sourceid') . '/registrations/';
            $collection = 'OnlineActivities';
            $fields['customchar2'] = $fields['arlotype'];
            $fields['customchar3'] = $persistent->get('sourceguid');
        }
        // Sanity check, make sure isn't already linked to another instance.
        $conditions = [
            'customchar1' => $pluginconfig->get('platform'),
            'customchar2' => $persistent->get('sourceguid')
        ];
        if (static::instance_exists($conditions)) {
            throw new coding_exception('An enrolment instance is already linked to this resource.');
        }
        // Create a new course group if required.
        if (!empty($fields['customint2']) && $fields['customint2'] == self::CREATE_GROUP) {
            $context = context_course::instance($course->id);
            require_capability('moodle/course:managegroups', $context);
            $groupid = static::create_course_group($course->id, $persistent->get('code')); // Pass code to use as name.
            // Map group id to customint2.
            $fields['customint2'] = $groupid;
        }
        // Use parent to create enrolment instance.
        $instanceid = parent::add_instance($course, $fields);

        // Register enrolment instance jobs.
        memberships_job::register_job_instance(
            $instanceid,
            $endpoint,
            $collection,
            $persistent->get_time_norequests_after()
        );
        outcomes_job::register_job_instance(
            $instanceid,
            'registrations/',
            'Registrations',
            $persistent->get_time_norequests_after()
        );
        contacts_job::register_job_instance(
            $instanceid,
            $endpoint,
            $collection,
            $persistent->get_time_norequests_after()
        );

        // No external API call during PHPUnit test. Return immediately.
        if (PHPUNIT_TEST) {
            return $instanceid;
        }

        // Update Content Uri and Manage Uri on Arlo. @TODO move to EventAPI.
        if ($pluginconfig->get('allowportalintegration')) {
            $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
            external::update_contenturi($fields['customchar2'], $fields['customchar3'], $courseurl);
            $instance = $this->get_instance_record($instanceid, MUST_EXIST);
            external::update_manageuri($fields['customchar2'], $fields['customchar3'], $instance);
        }
        return $instanceid;
    }

    /**
     * Create course group based on Arlo Code.
     *
     * @param $courseid
     * @param $code
     * @return int
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public static function create_course_group($courseid, $code) {
        global $DB;
        // Check code.
        if (empty($code)) {
            throw new coding_exception('Arlo code is empty cannot create course group');
        }
        // Format group name.
        $groupname = get_string('defaultgroupnametext', 'enrol_arlo', array('name' => $code));
        // Check if group exists and return group id.
        $group = $DB->get_record('groups', array('idnumber' => $code, 'courseid' => $courseid));
        if ($group) {
            return $group->id;
        }
        // Create a new group for the for event or online activity.
        $groupdata              = new stdClass();
        $groupdata->courseid    = $courseid;
        $groupdata->name        = $groupname;
        $groupdata->idnumber    = $code;
        $groupid                = groups_create_group($groupdata);
        return $groupid;
    }

    /**
     * Delete plugin specific information.
     *
     * @param object $instance
     * @throws coding_exception
     * @throws dml_exception
     */
    public function delete_instance($instance) {
        global $DB;
        // Delete associated registrations.
        $DB->delete_records('enrol_arlo_registration', array('enrolid' => $instance->id));
        // Delete job scheduling information.
        $conditions = [
            'area' => 'enrolment',
            'instanceid' => $instance->id
        ];
        $DB->delete_records(
            'enrol_arlo_scheduledjob',
            $conditions
        );
        // Delete email queue information.
        $DB->delete_records('enrol_arlo_emailqueue', $conditions);
        // Time for the parent to do it's thang, yeow.
        parent::delete_instance($instance);
    }

    /**
     * Update instance of enrol plugin.
     *
     * @param stdClass $instance
     * @param stdClass $data
     * @return bool
     * @throws ReflectionException
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function update_instance($instance, $data) {

        $arlotype = $instance->customchar2;
        $sourceguid = $instance->customchar3;
        if ($arlotype == arlo_type::EVENT) {
            $persistent = event_persistent::get_record(
                ['sourceguid' => $sourceguid]
            );
        }
        if ($arlotype == arlo_type::ONLINEACTIVITY) {
            $persistent = online_activity_persistent::get_record(
                ['sourceguid' => $sourceguid]
            );
        }
        if (!$persistent) {
            throw new coding_exception('Invalid persistent.');
        }
        $timenorequestsafter = $persistent->get_time_norequests_after();

        $membershipsjob = job_persistent::get_record(
            ['area' => 'enrolment', 'type' => 'memberships', 'instanceid' => $instance->id]
        );
        $membershipsjob->reset_sync_state_information();
        $membershipsjob->set('timenorequestsafter', $timenorequestsafter);
        $membershipsjob->set('timenextrequestdelay', enrol_arlo\local\job\job::TIME_PERIOD_DELAY);
        $membershipsjob->set('timerequestsafterextension', enrol_arlo\local\job\job::TIME_PERIOD_EXTENSION);
        $membershipsjob->save();

        $outcomesjob = job_persistent::get_record(
            ['area' => 'enrolment', 'type' => 'outcomes', 'instanceid' => $instance->id]
        );
        $outcomesjob->reset_sync_state_information();
        $outcomesjob->set('timenorequestsafter', $timenorequestsafter);
        $outcomesjob->set('timenextrequestdelay', enrol_arlo\local\job\job::TIME_PERIOD_DELAY);
        $outcomesjob->set('timerequestsafterextension', enrol_arlo\local\job\job::TIME_PERIOD_EXTENSION);
        $outcomesjob->save();

        $contactsjob = job_persistent::get_record(
            ['area' => 'enrolment', 'type' => 'contacts', 'instanceid' => $instance->id]
        );
        $contactsjob->reset_sync_state_information();
        $contactsjob->set('timenorequestsafter', $timenorequestsafter);
        $contactsjob->set('timenextrequestdelay', enrol_arlo\local\job\contacts_job::TIME_PERIOD_DELAY);
        $contactsjob->save();

        // Create a new course group if required.
        $course = get_course($instance->courseid);
        if (!empty($data->customint2) && $data->customint2 == self::CREATE_GROUP) {
            $context = context_course::instance($course->id);
            require_capability('moodle/course:managegroups', $context);
            $groupid = static::create_course_group($course->id, $instance->name);
            // Map group id to customint2.
            $data->customint2 = $groupid;
        }

        $updatestatus = parent::update_instance($instance, $data);

        // Update Content Uri and Manage Uri on Arlo.
        $pluginconfig = new arlo_plugin_config();
        if ($pluginconfig->get('allowportalintegration')) {
            $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
            external::update_contenturi($instance->customchar2, $instance->customchar3, $courseurl);
            external::update_manageuri($instance->customchar2, $instance->customchar3, $instance);
        }
        return $updatestatus;
    }

    /**
     * Does this plugin allow manual changes in user_enrolments table?
     *
     * All plugins allowing this must implement 'enrol/xxx:manage' capability
     *
     * @param stdClass $instance course enrol instance
     * @return bool - true means it is possible to change enrol period and status in user_enrolments table
     */
    public function allow_manage(stdClass $instance) {
        return true;
    }

    /**
     * Does this plugin allow manual unenrolment of all users?
     * All plugins allowing this must implement 'enrol/xxx:unenrol' capability
     *
     * @param stdClass $instance course enrol instance
     * @return bool - true means user with 'enrol/xxx:unenrol' may unenrol others freely,
     * false means nobody may touch user_enrolments
     */
    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    /**
     * Does this plugin allow manual unenrolment of a specific user?
     * Yes, but only if user suspended...
     *
     * @param stdClass $instance course enrol instance
     * @param stdClass $ue record from user_enrolments table
     *
     * @return bool - true means user with 'enrol/xxx:unenrol' may unenrol this user,
     * false means nobody may touch this user enrolment
     * @throws coding_exception
     */
    public function allow_unenrol_user(stdClass $instance, stdClass $ue) {
        if ($this->get_plugin_config()->get('allowunenrolactiveenrolmentsui')) {
            return true;
        }
        if ($ue->status == ENROL_USER_SUSPENDED) {
            return true;
        }
        return false;
    }

    /**
     * Return true if we can add a new instance to this course.
     *
     * @param int $courseid
     * @return bool
     * @throws coding_exception
     */
    public function can_add_instance($courseid) {
        $context = context_course::instance($courseid);
        if (!has_capability('moodle/course:enrolconfig', $context) || !has_capability('enrol/arlo:config', $context)) {
            return false;
        }
        return true;
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     * @throws coding_exception
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/arlo:config', $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     * @throws coding_exception
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/arlo:config', $context);
    }

    /**
     * Get Event Templates options for form select.
     *
     * @param $courseid
     * @return array
     * @throws dml_exception
     */
    public function get_template_options($courseid) {
        global $DB;
        $sql = "SELECT sourceid, sourceguid, name, code
                  FROM {enrol_arlo_template}
                 WHERE sourcestatus = :sourcestatus
                   AND sourceguid NOT IN (SELECT sourcetemplateguid
                                            FROM {enrol_arlo_templateassociate}
                                           WHERE courseid <> :courseid AND sourcetemplateid IS NOT NULL)
              ORDER BY code";
        $conditions = array(
            'platform' => self::get_config('platform', null),
            'sourcestatus' => EventTemplateStatus::ACTIVE,
            'courseid' => $courseid
        );
        $options = array();
        $records = $DB->get_records_sql($sql, $conditions);
        foreach ($records as $record) {
            $options[$record->sourceguid] = $record->code . ' ' . shorten_text($record->name, 40);
        }
        return $options;
    }

    /**
     * Get Event options for form select.
     *
     * @return array
     * @throws coding_exception
     * @throws dml_exception
     */
    public function get_event_options() {
        global $DB;
        $options = array();
        $pluginconfig = $this->get_plugin_config();
        $statuses = [EventStatus::ACTIVE];
        if ($pluginconfig->get('allowcompletedevents')) {
            array_push($statuses, EventStatus::COMPLETED);
        }
        list($insql, $inparams) = $DB->get_in_or_equal($statuses, SQL_PARAMS_NAMED);
        $conditions = array(
            'platform' => $pluginconfig->get('platform'),
        );
        $conditions = array_merge($conditions, $inparams);
        $sql = "SELECT ae.sourceguid, ae.code, aet.name
                  FROM {enrol_arlo_event} ae
                  JOIN {enrol_arlo_template} aet ON aet.sourceguid = ae.sourcetemplateguid
                 WHERE ae.platform = :platform
                   AND ae.sourcestatus $insql
                   AND ae.sourceguid NOT IN (SELECT customchar3
                                               FROM {enrol} WHERE customchar3 IS NOT NULL)
              ORDER BY code";

        $records = $DB->get_records_sql($sql, $conditions);
        foreach ($records as $record) {
            $options[$record->sourceguid] = $record->code . ' ' . shorten_text($record->name, 40);
        }
        return $options;
    }

    /**
     * Get Online Activity options for form select.
     *
     * @return array
     */
    public function get_onlineactivity_options() {
        global $DB;
        $options = array();
        $pluginconfig = $this->get_plugin_config();
        $statuses = [OnlineActivityStatus::ACTIVE];
        if ($pluginconfig->get('allowcompletedonlineactivities')) {
            array_push($statuses, OnlineActivityStatus::COMPLETED);
        }
        list($insql, $inparams) = $DB->get_in_or_equal($statuses, SQL_PARAMS_NAMED);
        $conditions = array(
            'platform' => $pluginconfig->get('platform'),
        );
        $conditions = array_merge($conditions, $inparams);
        $sql = "SELECT sourceguid, code, name
                  FROM {enrol_arlo_onlineactivity}
                 WHERE platform = :platform
                   AND sourcestatus $insql
                   AND sourceguid NOT IN (SELECT customchar3
                                            FROM {enrol} WHERE customchar3 IS NOT NULL)
              ORDER BY code";
        $records = $DB->get_records_sql($sql, $conditions);
        foreach ($records as $record) {
            $options[$record->sourceguid] = $record->code . ' ' . shorten_text($record->name, 40);
        }
        return $options;
    }

    /**
     * Returns defaults for new instances.
     *
     * @return array
     */
    public function get_instance_defaults() {
        $fields = array();
        $fields['status']               = ENROL_INSTANCE_ENABLED;
        $fields['roleid']               = $this->get_config('roleid');
        $fields['enrolperiod']          = 0;
        $fields['expirynotify']         = 0;
        $fields['customint2']           = self::CREATE_GROUP;  // Auto create group by default.
        $fields['customint8']           = 1; // Send course welcome.
        $fields['arlotype']             = '';
        $fields['arloevent']            = '';
        $fields['arloonlineactivity']   = '';
        return $fields;
    }

    public function get_type_options() {
        $options = array(
            arlo_type::EVENT => get_string('event', 'enrol_arlo'),
            arlo_type::ONLINEACTIVITY => get_string('onlineactivity', 'enrol_arlo')
        );
        return $options;
    }

    /**
     * Add elements to the edit instance form.
     *
     * @param stdClass $instance
     * @param MoodleQuickForm $mform
     * @param context $context
     * @return bool
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {
        global $DB;

        // Editing existing instance.
        if (!is_null($instance->id)) {
            $arlotype = $instance->customchar2;
            // Setup read-only Event.
            if ($arlotype == arlo_type::EVENT) {
                $typeoptions = array(
                    arlo_type::EVENT => get_string('event', 'enrol_arlo')
                );
                $mform->addElement(
                    'select',
                    'arlotype',
                    get_string('type', 'enrol_arlo'),
                    $typeoptions
                );
                $mform->setConstant('arlotype', $arlotype);
                $mform->hardFreeze('arlotype', $arlotype);
                $persistent = event_persistent::get_record(['sourceguid' => $instance->customchar3]);
                $eventoptions = [
                    $persistent->get('sourceguid') => $persistent->get('code')
                ];
                $mform->addElement('select', 'arloevent', get_string('event', 'enrol_arlo'),
                    $eventoptions);
                $mform->setConstant('arloevent', $instance->customchar3);
                $mform->hardFreeze('arloevent', $instance->customchar3);
            }
            // Setup read-only Online Activity.
            if ($arlotype == arlo_type::ONLINEACTIVITY) {
                $typeoptions = array(
                    arlo_type::ONLINEACTIVITY => get_string('onlineactivity', 'enrol_arlo')
                );
                $mform->addElement(
                    'select',
                    'arlotype',
                    get_string('type', 'enrol_arlo'),
                    $typeoptions
                );
                $mform->setConstant('arlotype', $arlotype);
                $mform->hardFreeze('arlotype', $arlotype);
                $persistent = online_activity_persistent::get_record(['sourceguid' => $instance->customchar3]);
                $eventoptions = [
                    $persistent->get('sourceguid') => $persistent->get('code')
                ];
                $mform->addElement('select', 'arloonlineactivity', get_string('onlineactivity',
                    'enrol_arlo'), $eventoptions);
                $mform->setConstant('arloonlineactivity', $instance->customchar3);
                $mform->hardFreeze('arloonlineactivity', $instance->customchar3);
            }
        } else { // New instance.
            $typeoptions = $this->get_type_options();
            $eventoptions = $this->get_event_options();
            $onlineactivityoptions = $this->get_onlineactivity_options();
            // If there are no Active Events or Online Activities redirect.
            if (!$onlineactivityoptions && !$eventoptions) {
                $redirect = new moodle_url('/enrol/instances.php');
                $redirect->param('id', $instance->courseid);
                redirect($redirect, get_string('noeventsoractivitiesfound', 'enrol_arlo'), 1);
            }
            // Type options.
            array_unshift($typeoptions, get_string('choose') . '...');
            $mform->addElement('select', 'arlotype', get_string('type', 'enrol_arlo'), $typeoptions);
            // Event selector.
            array_unshift($eventoptions, get_string('choose') . '...');
            $mform->addElement('select', 'arloevent', get_string('event', 'enrol_arlo'), $eventoptions);
            $mform->disabledIf('arloevent', 'arlotype', 'eq', arlo_type::ONLINEACTIVITY);
            $mform->disabledIf('arloevent', 'arlotype', 'eq', 0);
            // Online Activity selector.
            array_unshift($onlineactivityoptions, get_string('choose') . '...');
            $mform->addElement('select', 'arloonlineactivity',
                get_string('onlineactivity', 'enrol_arlo'), $onlineactivityoptions);
            $mform->disabledIf('arloonlineactivity', 'arlotype', 'eq', arlo_type::EVENT);
            $mform->disabledIf('arloonlineactivity', 'arlotype', 'eq', 0);
        }
        // Settings that are editable be instance new or existing.
        $options = $this->get_status_options();
        $mform->addElement('select', 'status', get_string('status', 'enrol_arlo'), $options);
        // Groups.
        $groups = array(0 => get_string('none'));
        if (has_capability('moodle/course:managegroups', $context)) {
            $groups[self::CREATE_GROUP] = get_string('creategroup', 'enrol_arlo');
        }
        foreach (groups_get_all_groups($context->instanceid) as $group) {
            $groups[$group->id] = format_string($group->name, true, array('context' => $context));
        }
        $mform->addElement('select', 'customint2', get_string('assignedgroup', 'enrol_arlo'), $groups);
        if ($instance->customint2 != self::CREATE_GROUP || $instance->customint2 == '0') {
            $mform->setConstant('customint2', $instance->customint2);
            $mform->hardFreeze('customint2', $instance->customint2);
        }
        $mform->addElement('advcheckbox', 'customint8', get_string('sendcoursewelcomemessage', 'enrol_arlo'));
        $mform->addHelpButton('customint8', 'sendcoursewelcomemessage', 'enrol_arlo');
        $mform->addElement('textarea', 'customtext1',
            get_string('customwelcomemessage', 'enrol_arlo'),
            array('cols' => '60', 'rows' => '8'));
        $mform->addHelpButton('customtext1', 'customwelcomemessage', 'enrol_arlo');
        $options = array('optional' => true, 'defaultunit' => 86400);
        $mform->addElement('duration', 'enrolperiod', get_string('enrolperiod', 'enrol_arlo'), $options);
        $mform->addHelpButton('enrolperiod', 'enrolperiod', 'enrol_self');
        $options = array(0 => get_string('no'), 1 => get_string('yes'));
        $mform->addElement('select', 'expirynotify', get_string('expirynotify', 'enrol_arlo'), $options);
        $mform->addHelpButton('expirynotify', 'expirynotify', 'enrol_arlo');
    }

    /**
     * Perform custom validation of the data used to edit the instance.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @param object $instance The instance loaded from the DB
     * @param context $context The context of the instance we are editing
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK.
     * @return void
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        $errors = array();
        if (empty($instance->id)) {
            if (empty($data['arlotype'])) {
                $errors['arlotype'] = get_string('errorselecttype', 'enrol_arlo');
            }
            if ($data['arlotype'] == arlo_type::EVENT && empty($data['arloevent'])) {
                $errors['arloevent'] = get_string('errorselectevent', 'enrol_arlo');
            }
            if ($data['arlotype'] == arlo_type::ONLINEACTIVITY && empty($data['arloonlineactivity'])) {
                $errors['arloonlineactivity'] = get_string('errorselectonlineactivity', 'enrol_arlo');
            }
        }
        return $errors;
    }

    /**
     * Return an array of valid options for the status.
     *
     * @return array
     */
    protected function get_status_options() {
        $options = array(
            ENROL_INSTANCE_ENABLED  => get_string('yes'),
            ENROL_INSTANCE_DISABLED => get_string('no')
        );
        return $options;
    }

    /**
     * Returns action icons for the page with list of instances.
     *
     * @param stdClass $instance
     * @return array
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        $courseid = $instance->courseid;
        $context = context_course::instance($courseid);

        $icons = array();
        if (has_capability('enrol/arlo:synchronizeinstance', $context)) {
            $link = new moodle_url('arlo/synchronizeinstance.php', array('sesskey' => sesskey(), 'id' => $instance->id));
            $icon = new pix_icon('synchronize', get_string('synchronize', 'enrol_arlo'),
                'enrol_arlo', array('class' => 'iconsmall'));
            $icons[] = $OUTPUT->action_icon($link, $icon);
        }
        $parenticons = parent::get_action_icons($instance);
        $icons = array_merge($icons, $parenticons);

        return $icons;
    }

    /**
     * We are a good plugin and don't invent our own UI/validation code path.
     *
     * @return boolean
     */
    public function use_standard_editing_ui() {
        return true;
    }

    /**
     * Returns Arlo Code for Event or Online Activity instance.
     *
     * @param object $instance
     * @return string
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function get_instance_name($instance) {
        global $DB;

        $enrol = $this->get_name();

        if (empty($instance->name)) {
            // Shouldn't happen.
            $instance->name = 'Arlo code missing, remove this instance.';
        }

        if (!empty($instance->roleid) and $role = $DB->get_record('role', array('id' => $instance->roleid))) {
            $role = ' (' . role_get_name($role, context_course::instance($instance->courseid, IGNORE_MISSING)) . ')';
        } else {
            $role = '';
        }

        return get_string('pluginname', 'enrol_' . $enrol) .  ' : ' . format_string($instance->name) . $role;
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     *
     * @param int $courseid
     * @return moodle_url|null
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function get_newinstance_link($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);
        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/arlo:config', $context)) {
            return null;
        }
        // Multiple instances supported.
        return new moodle_url('/enrol/arlo/edit.php', array('courseid' => $courseid));
    }

    /**
     * Gets an array of the user enrolment actions
     *
     * @param course_enrolment_manager $manager
     * @param stdClass $ue
     * @return array
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue) {
        $actions = array();
        $context = $manager->get_context();
        $instance = $ue->enrolmentinstance;
        $params = $manager->get_moodlepage()->url->params();
        $params['ue'] = $ue->id;
        if ($this->allow_unenrol_user($instance, $ue) && has_capability("enrol/arlo:unenrol", $context)) {
            $url = new moodle_url('/enrol/unenroluser.php', $params);
            $actions[] = new user_enrolment_action(
                new pix_icon('t/delete', ''),
                get_string('unenrol', 'enrol'),
                $url, array('class' => 'unenrollink', 'rel' => $ue->id));
        }
        if ($this->allow_manage($instance) && has_capability("enrol/arlo:manage", $context)) {
            $url = new moodle_url('/enrol/editenrolment.php', $params);
            $actions[] = new user_enrolment_action(
                new pix_icon('t/edit', ''),
                get_string('edit'),
                $url,
                array('class' => 'editenrollink', 'rel' => $ue->id));
        }
        return $actions;
    }

    /**
     * Suspend and remove all roles in a course.
     *
     * @param stdClass $instance
     * @param $userid
     * @throws coding_exception
     * @throws dml_exception
     */
    public function suspend_and_remove_roles(stdClass $instance, $userid) {
        global $DB;

        if ($DB->record_exists('user_enrolments', array('enrolid' => $instance->id, 'userid' => $userid))) {
            parent::update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
            // Remove all users groups linked to this enrolment instance.
            $conditions = array('userid' => $userid, 'component' => 'enrol_arlo', 'itemid' => $instance->id);
            if ($gms = $DB->get_records('groups_members', $conditions)) {
                foreach ($gms as $gm) {
                    groups_remove_member($gm->groupid, $gm->userid);
                }
            }
            $context = context_course::instance($instance->courseid);
            $unenrolparams = array();
            $unenrolparams['userid'] = $userid;
            $unenrolparams['contextid'] = $context->id;
            $unenrolparams['component'] = 'enrol_arlo';
            $unenrolparams['itemid'] = $instance->id;
            role_unassign_all($unenrolparams);
        }
    }
}

/**
 * Display the associate Arlo template link in the course administration menu.
 *
 * @param $navigation
 * @param $course
 * @param $context
 * @throws coding_exception
 * @throws moodle_exception
 */
function enrol_arlo_extend_navigation_course($navigation, $course, $context) {
    // Check that the Arlo plugin is enabled.
    if (enrol_is_enabled('arlo')) {
        // Check that they can add an instance.
        $plugin = enrol_get_plugin('arlo');
        if ($plugin->can_add_instance($course->id)) {
            $url = new moodle_url('/enrol/arlo/associatetemplate.php', array('id' => $context->instanceid));
            $label = get_string('associatearlotemplate', 'enrol_arlo');
            $settingsnode = navigation_node::create($label, $url, navigation_node::TYPE_SETTING,
                null, null, new pix_icon('i/twoway', ''));
            $navigation->add_node($settingsnode);
        }
    }
}
