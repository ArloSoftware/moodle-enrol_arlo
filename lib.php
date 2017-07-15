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
 * Arlo enrolment plugin.
 *
 * @author      Troy Williams
 * @author      Corey Davis
 * @package     local_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright   2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/vendor/autoload.php');
require_once($CFG->dirroot . '/group/lib.php');

use enrol_arlo\Arlo\AuthAPI\Enum\EventStatus;
use enrol_arlo\Arlo\AuthAPI\Enum\OnlineActivityStatus;
use enrol_arlo\Arlo\AuthAPI\Enum\EventTemplateStatus;
use enrol_arlo\user;



class enrol_arlo_plugin extends enrol_plugin {
    const ARLO_TYPE_EVENT           = 'event';
    const ARLO_TYPE_ONLINEACTIVITY  = 'onlineactivity';
    const ARLO_CREATE_GROUP         = -1;

    public function get_config_defaults() {
        static $studentroleid;
        if (is_null($studentroleid)) {
            $student = get_archetype_roles('student');
            $student = reset($student);
            $studentroleid = $student->id;
        }
        $defaults = array(
            'apistatus' => 0,
            'apilastrequested' => 0,
            'apierrorcount' => 0,
            'platform' => '',
            'apiusername' => '',
            'apipassword' => '',
            'matchuseraccountsby' => user::MATCH_BY_DEFAULT,
            'authplugin' => 'manual',
            'roleid' => $studentroleid,
            'unenrolaction' => ENROL_EXT_REMOVED_UNENROL,
            'expiredaction' => ENROL_EXT_REMOVED_SUSPEND,
            'pushonlineactivityresults' => 1,
            'pusheventresults' => 0,
            'alertsiteadmins' => 1
        );
        return $defaults;
    }

    /**
     * Add new instance of enrol plugin with default settings.
     * @param stdClass $course
     * @return int id of new instance
     */
    public function add_default_instance($course) {
        $fields = $this->get_instance_defaults();
        return $this->add_instance($course, $fields);
    }

    /**
     * Add new instance of enrol plugin.
     *
     * @param object $course
     * @param array $fields instance fields
     * @return int id of new instance, null if can not be created
     */
    public function add_instance($course, array $fields = null) {
        global $DB;

        $instance = new stdClass();
        $instance->platform = self::get_config('platform');
        if (empty($fields['arlotype'])) {
            throw new moodle_exception('Field arlotype is empty.');
        }
        $instance->type = $fields['arlotype'];
        if ($instance->type  == self::ARLO_TYPE_EVENT) {
            if (empty($fields['arloevent'])) {
                throw new moodle_exception('Field arloevent is empty.');
            }
            $sourcetable = 'enrol_arlo_event';
            $sourceguid = $fields['arloevent'];
        }
        if ($instance->type  == self::ARLO_TYPE_ONLINEACTIVITY) {
            if (empty($fields['arloonlineactivity'])) {
                throw new moodle_exception('Field sourceguid is empty.');
            }
            $sourcetable = 'enrol_arlo_onlineactivity';
            $sourceguid = $fields['arloonlineactivity'];
        }
        // Check Event or Online Activity not already in play.
        if ($DB->record_exists('enrol_arlo_instance', array('sourceguid' => $sourceguid))) {
            return false;
        }
        $conditions = array('platform' => $instance->platform, 'sourceguid' => $sourceguid);
        $record = $DB->get_record($sourcetable, $conditions, '*', MUST_EXIST);
        $instance->sourceid     = $record->sourceid;
        $instance->sourceguid   = $record->sourceguid;
        $instance->nextpulltime = time(); // Set next pull time to current time.
        $sourcefinishdate = 0;
        if (isset($record->finishdatetime)) {
            $sourcefinishdate = date_timestamp_get(new \DateTime($record->finishdatetime));
        }
        // Create a new course group if required.
        if (!empty($fields['customint2']) && $fields['customint2'] == self::ARLO_CREATE_GROUP) {
            $context = \context_course::instance($course->id);
            require_capability('moodle/course:managegroups', $context);
            $groupid = static::create_course_group($course->id, $record->code);
            // Map group id to customint2.
            $fields['customint2']   = $groupid;
        }
        // Set name to be passed to parent.
        $fields['name']         = $record->code;
        $fields['roleid']       = self::get_config('roleid');
        // Insert enrol and enrol_arlo_instance records.
        $instance->enrolid = parent::add_instance($course, $fields);
        $DB->insert_record('enrol_arlo_instance', $instance);
        \enrol_arlo\manager::schedule('registrations', $instance->enrolid, $sourcefinishdate);
        \enrol_arlo\manager::schedule('contacts', $instance->enrolid, $sourcefinishdate);
        return $instance->enrolid;
    }

    /**
     * Create course group based on Arlo Code.
     *
     * @param $courseid
     * @param $code
     * @return id
     */
    public static function create_course_group($courseid, $code) {
        global $DB;
        // Format group name.
        $groupname = get_string('defaultgroupnametext', 'enrol_arlo', array('name' => $code));
        // Check if group exists and return group id.
        $group = $DB->get_record('groups', array('name' => $groupname, 'courseid' => $courseid));
        if ($group) {
            return $group->id;
        }
        // Create a new group for the for event or online activity.
        $groupdata = new \stdClass();
        $groupdata->courseid = $courseid;
        $groupdata->name = $groupname;
        $groupdata->idnumber = $code;
        $groupid = groups_create_group($groupdata);
        return $groupid;
    }

    /**
     * Delete plugin specific information.
     *
     * @param stdClass $instance
     * @return void
     */
    public function delete_instance($instance) {
        global $DB;
        // Delete associated registrations.
        $DB->delete_records('enrol_arlo_registration', array('enrolid' => $instance->id));
        // Delete instance mapping information.
        $DB->delete_records('enrol_arlo_instance', array('enrolid' => $instance->id));
        // Delete scheduling information.
        $DB->delete_records('enrol_arlo_schedule', array('enrolid' => $instance->id));
        // Clear our any welcome flags.
        if ($instance->customint8) {
            $DB->delete_records('user_preferences',
                array('name' => 'enrol_arlo_coursewelcome_'.$instance->id, 'value' => $instance->id));
        }
        // Time for the parent to do it's thang, yeow.
        parent::delete_instance($instance);
    }

    /**
     * Update instance of enrol plugin.
     * @param stdClass $instance
     * @param stdClass $data modified instance fields
     * @return boolean
     */
    public function update_instance($instance, $data) {
        return parent::update_instance($instance, $data);
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
     * Does this plugin allow manual unenrolment of a specific user?
     * Yes, but only if user suspended...
     *
     * @param stdClass $instance course enrol instance
     * @param stdClass $ue record from user_enrolments table
     *
     * @return bool - true means user with 'enrol/xxx:unenrol' may unenrol this user,
     * false means nobody may touch this user enrolment
     */
    public function allow_unenrol_user(stdClass $instance, stdClass $ue) {
        if ($ue->status == ENROL_USER_SUSPENDED) {
            return true;
        }
        return false;
    }

    /**
     * Return true if we can add a new instance to this course.
     *
     * @param int $courseid
     * @return boolean
     */
    public function can_add_instance($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);
        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/arlo:config', $context)) {
            return false;
        }
        return true;
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param object $instance
     * @return bool
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
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/arlo:config', $context);
    }

    /**
     * Get Event Templates options for form select.
     *
     * @return array
     */
    public function get_template_options($courseid) {
        global $DB;
        $sql = "SELECT sourceid, sourceguid, name, code
                  FROM {enrol_arlo_template}
                 WHERE sourcestatus = :sourcestatus
                   AND sourceguid NOT IN (SELECT sourcetemplateguid
                                            FROM {enrol_arlo_templateassociate}
                                           WHERE courseid <> :courseid)
              ORDER BY code";
        $conditions = array(
            'platform' => self::get_config('platform', null),
            'sourcestatus' => EventTemplateStatus::ACTIVE,
            'courseid' => $courseid
        );
        $options = array();
        $records = $DB->get_records_sql($sql, $conditions);
        foreach ($records as $record) {
            $options[$record->sourceguid] = $record->code . ' ' . $record->name;
        }
        return $options;
    }

    /**
     * Get Event options for form select.
     *
     * @return array
     */
    public function get_event_options() {
        global $DB;
        $options = array();
        $sql = "SELECT sourceguid, code 
                  FROM {enrol_arlo_event}
                 WHERE platform = :platform
                   AND sourcestatus = :sourcestatus 
                   AND sourceguid NOT IN (SELECT sourceguid 
                                            FROM {enrol_arlo_instance})
              ORDER BY code";
        $conditions = array(
            'platform' => self::get_config('platform', null),
            'sourcestatus' => EventStatus::ACTIVE
        );
        $records = $DB->get_records_sql($sql, $conditions);
        foreach ($records as $record) {
            $options[$record->sourceguid] = $record->code;
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
        $sql = "SELECT sourceguid, code 
                  FROM {enrol_arlo_onlineactivity}
                 WHERE platform = :platform
                   AND sourcestatus = :sourcestatus 
                   AND sourceguid NOT IN (SELECT sourceguid 
                                            FROM {enrol_arlo_instance})
              ORDER BY code";
        $conditions = array(
            'platform' => self::get_config('platform', null),
            'sourcestatus' => OnlineActivityStatus::ACTIVE
        );
        $records = $DB->get_records_sql($sql, $conditions);
        foreach ($records as $record) {
            $options[$record->sourceguid] = $record->code;
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
        $fields['customint2']           = self::ARLO_CREATE_GROUP;  // Group
        $fields['customint8']           = 1; // Send course welcome.
        $fields['arlotype']             = '';
        $fields['arloevent']            = '';
        $fields['arloonlineactivity']   = '';
        return $fields;
    }

    public function get_type_options() {
        $options = array(
            self::ARLO_TYPE_EVENT => get_string('event', 'enrol_arlo'),
            self::ARLO_TYPE_ONLINEACTIVITY => get_string('onlineactivity', 'enrol_arlo')
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
            $conditions = array('enrolid' => $instance->id, 'platform' => self::get_config('platform'));
            $arloinstance = $DB->get_record(
                'enrol_arlo_instance',
                $conditions,
                'type, sourceid, sourceguid',
                MUST_EXIST
            );
            // Setup read-only Event.
            if ($arloinstance->type == self::ARLO_TYPE_EVENT) {
                $typeoptions = array(
                    self::ARLO_TYPE_EVENT => get_string('event', 'enrol_arlo')
                );
                $mform->addElement(
                    'select',
                    'arlotype',
                    get_string('type', 'enrol_arlo'),
                    $typeoptions
                );
                $mform->setConstant('arlotype', $arloinstance->type);
                $mform->hardFreeze('arlotype', $arloinstance->type);
                $code = $DB->get_field(
                    'enrol_arlo_event',
                    'code',
                    array('platform' => self::get_config('platform'), 'sourceid' => $arloinstance->sourceid)
                );
                $eventoptions = array($arloinstance->sourceguid => $code);
                $mform->addElement('select', 'arloevent', get_string('event', 'enrol_arlo'),
                    $eventoptions);
                $mform->setConstant('arloevent', $arloinstance->sourceguid);
                $mform->hardFreeze('arloevent', $arloinstance->sourceguid);
            }
            // Setup read-only Online Activity.
            if ($arloinstance->type == self::ARLO_TYPE_ONLINEACTIVITY) {
                $typeoptions = array(
                    self::ARLO_TYPE_ONLINEACTIVITY => get_string('onlineactivity', 'enrol_arlo')
                );
                $mform->addElement(
                    'select',
                    'arlotype',
                    get_string('type', 'enrol_arlo'),
                    $typeoptions
                );
                $mform->setConstant('arlotype', $arloinstance->type);
                $mform->hardFreeze('arlotype', $arloinstance->type);
                $code = $DB->get_field(
                    'enrol_arlo_onlineactivity',
                    'code',
                    array('platform' => self::get_config('platform'), 'sourceid' => $arloinstance->sourceid)
                );
                $eventoptions = array($arloinstance->sourceguid => $code);
                $mform->addElement('select', 'arloonlineactivity', get_string('onlineactivity',
                    'enrol_arlo'), $eventoptions);
                $mform->setConstant('arloonlineactivity', $arloinstance->sourceguid);
                $mform->hardFreeze('arloonlineactivity', $arloinstance->sourceguid);
            }
        // New instance.
        } else {
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
            $mform->disabledIf('arloevent', 'arlotype', 'eq', self::ARLO_TYPE_ONLINEACTIVITY);
            $mform->disabledIf('arloevent', 'arlotype', 'eq', 0);
            // Online Activity selector.
            array_unshift($onlineactivityoptions, get_string('choose') . '...');
            $mform->addElement('select', 'arloonlineactivity',
                get_string('onlineactivity', 'enrol_arlo'), $onlineactivityoptions);
            $mform->disabledIf('arloonlineactivity', 'arlotype', 'eq', self::ARLO_TYPE_EVENT);
            $mform->disabledIf('arloonlineactivity', 'arlotype', 'eq', 0);
        }
        // Settings that are editable be instance new or existing.
        $options = $this->get_status_options();
        $mform->addElement('select', 'status', get_string('status', 'enrol_arlo'), $options);
        // Groups
        $groups = array(0 => get_string('none'));
        if (has_capability('moodle/course:managegroups', $context)) {
            $groups[self::ARLO_CREATE_GROUP] = get_string('creategroup', 'enrol_arlo');
        }
        foreach (groups_get_all_groups($context->instanceid) as $group) {
            $groups[$group->id] = format_string($group->name, true, array('context' => $context));
        }
        $mform->addElement('select', 'customint2', get_string('assignedgroup', 'enrol_arlo'), $groups);
        if ($instance->customint2 != self::ARLO_CREATE_GROUP || $instance->customint2 == '0') {
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
        if(empty($data['arlotype'])) {
            $errors['arlotype'] = get_string('errorselecttype', 'enrol_arlo');
        }
        if ($data['arlotype'] == self::ARLO_TYPE_EVENT && empty($data['arloevent'])) {
            $errors['arloevent'] = get_string('errorselectevent', 'enrol_arlo');
        }
        if ($data['arlotype'] == self::ARLO_TYPE_ONLINEACTIVITY && empty($data['arloonlineactivity'])) {
            $errors['arloonlineactivity'] = get_string('errorselectonlineactivity', 'enrol_arlo');
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

    public function email_newpassword(\stdClass $user) {
        global $CFG, $DB;

        // We try to send the mail in language the user understands,
        // unfortunately the filter_string() does not support alternative langs yet
        // so multilang will not work properly for site->fullname.
        $lang = empty($user->lang) ? $CFG->lang : $user->lang;
        $emailtype = 'enrol_arlo_createpassword';
        if (!get_user_preferences($emailtype, false, $user->id)) {
            return;
        }

        $site  = get_site();
        $noreplyuser = core_user::get_noreply_user();
        $newpassword = generate_password();

        update_internal_user_password($user, $newpassword);

        $a = new \stdClass();
        $a->firstname   = fullname($user, true);
        $a->sitename    = format_string($site->fullname);
        $a->username    = $user->username;
        $a->newpassword = $newpassword;
        $a->link        = $CFG->wwwroot .'/login/';
        $a->signoff     = generate_email_signoff();

        $message = (string)new lang_string('newusernewpasswordtext', '', $a, $lang);

        $subject = format_string($site->fullname) .': '. (string)new lang_string('newusernewpasswordsubj', '', $a, $lang);

        $status = email_to_user($user, $noreplyuser, $subject, $message);
        unset_user_preference($emailtype, $user);

        // Log delivery.
        $log = new \stdClass();
        $log->timelogged    = time();
        $log->type          = $emailtype;
        $log->userid        = $user->id;
        $log->delivered     = $status;
        $DB->insert_record('enrol_arlo_emaillog', $log);

        return $status;
    }

    /**
     * Notify user their course expiry. it is called only if notification of enrolled users (aka students) is enabled in course.
     *
     * @param stdClass $instance
     * @param stdClass $user
     */
    private function email_expiry_message($instance, $user) {
        global $CFG, $DB;

        $course = $DB->get_record('course', array('id' => $instance->courseid), '*', MUST_EXIST);
        $context = context_course::instance($course->id);
        // Get contact user.
        $contact = self::get_contact_user($instance);

        $a = new stdClass();
        $a->coursename = format_string($course->fullname, true, array('context' => $context));
        $a->courseurl  = "$CFG->wwwroot/course/view.php?id=$course->id";
        $a->user       = fullname($user, true);
        $a->contact    = fullname($contact, has_capability('moodle/site:viewfullnames', $context, $user));

        $subject = get_string('expirymessagesubject', 'enrol_arlo', $a);
        $messagetext = get_string('expirymessagetext', 'enrol_arlo', $a);
        $messagehtml = text_to_html($messagetext, null, false, true);

        // Directly emailing welcome message rather than using messaging.
        email_to_user($user, $contact, $subject, $messagetext, $messagehtml);
    }

    /**
     * Send welcome email to specified user.
     *
     * @param stdClass $instance
     * @param stdClass $user user record
     * @return void
     */
    public function email_welcome_message($instance, $user) {
        global $CFG, $DB;

        $emailtype = 'enrol_arlo_coursewelcome_'.$instance->id;
        if (!get_user_preferences($emailtype, false, $user->id)) {
            return;
        }

        $course = $DB->get_record('course', array('id' => $instance->courseid), '*', MUST_EXIST);
        $context = context_course::instance($course->id);

        $a = new stdClass();
        $a->coursename = format_string($course->fullname, true, array('context' => $context));
        $a->courseurl = "$CFG->wwwroot/course/view.php?id=$course->id";
        $a->username = $user->username;
        $a->forgotpasswordurl = "$CFG->wwwroot/login/forgot_password.php";

        if (trim($instance->customtext1) !== '') {
            $message = $instance->customtext1;
            $key = array(
                '{$a->coursename}',
                '{$a->courseurl}',
                '{$a->fullname}',
                '{$a->email}',
                '{$a->username}',
                '{$a->forgotpasswordurl}');
            $value = array(
                $a->coursename,
                $a->courseurl,
                fullname($user),
                $user->email,
                $user->username,
                $a->forgotpasswordurl
            );
            $message = str_replace($key, $value, $message);
            if (strpos($message, '<') === false) {
                // Plain text only.
                $messagetext = $message;
                $messagehtml = text_to_html($messagetext, null, false, true);
            } else {
                // This is most probably the tag/newline soup known as FORMAT_MOODLE.
                $messagehtml = format_text($message, FORMAT_MOODLE,
                    array('context' => $context, 'para' => false, 'newlines' => true, 'filter' => true));
                $messagetext = html_to_text($messagehtml);
            }
        } else {
            $messagetext = get_string('welcometocoursetext', 'enrol_arlo', $a);
            $messagehtml = text_to_html($messagetext, null, false, true);
        }

        $subject = get_string('welcometocourse', 'enrol_arlo',
            format_string($course->fullname, true, array('context' => $context)));

        // Get contact user.
        $contact = self::get_contact_user($instance);

        // Directly emailing welcome message rather than using messaging.
        $status = email_to_user($user, $contact, $subject, $messagetext, $messagehtml);
        unset_user_preference($emailtype, $user);

        // Log delivery.
        $log = new \stdClass();
        $log->timelogged    = time();
        $log->type          = $emailtype;
        $log->userid        = $user->id;
        $log->delivered     = $status;
        $DB->insert_record('enrol_arlo_emaillog', $log);

        return $status;
    }

    /**
     * Get user account to send emails out from.
     *
     * @return bool|mixed|stdClass
     */
    public function get_contact_user($instance) {
        global $CFG;

        if (!empty($CFG->arlocontactuserid)) {
            $contact = core_user::get_user($CFG->arlocontactuserid, '*', MUST_EXIST);
        } else {
            $rusers = array();
            if (!empty($CFG->coursecontact)) {
                $context = context_course::instance($instance->courseid);
                $croles = explode(',', $CFG->coursecontact);
                list($sort, $sortparams) = users_order_by_sql('u');
                // We only use the first user.
                $i = 0;
                do {
                    $rusers = get_role_users($croles[$i], $context, true, '',
                        'r.sortorder ASC, ' . $sort, null, '', '', '', '', $sortparams);
                    $i++;
                } while (empty($rusers) && !empty($croles[$i]));
            }
            if ($rusers) {
                $contact = reset($rusers);
            } else {
                $contact = core_user::get_support_user();
            }
        }
        // Unset emailstop to make sure support message is sent.
        $contact->emailstop = 0;
        return $contact;
    }

    /**
     * Returns action icons for the page with list of instances.
     *
     * @param stdClass $instance
     * @return array
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
            throw new moodle_exception('Arlo code does not exist.');
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
     * @return moodle_url page url
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
     * @param stdClass $ue A user enrolment object
     * @return array An array of user_enrolment_actions
     */
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue) {
        $actions = array();
        $context = $manager->get_context();
        $instance = $ue->enrolmentinstance;
        $params = $manager->get_moodlepage()->url->params();
        $params['ue'] = $ue->id;
        if ($this->allow_unenrol($instance) && has_capability("enrol/arlo:unenrol", $context)) {
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
     * Overrides parent implementation to allow user notification on enrolment expiration.
     *
     *
     * @param progress_trace $trace
     * @param int $courseid one course, empty mean all
     * @return bool true if any data processed, false if not
     */
    public function process_expirations(progress_trace $trace, $courseid = null) {
        global $DB;

        $name = $this->get_name();
        if (!enrol_is_enabled($name)) {
            $trace->finished();
            return false;
        }

        $processed = false;
        $params = array();
        $coursesql = "";
        if ($courseid) {
            $coursesql = "AND e.courseid = :courseid";
        }

        // Deal with expired accounts.
        $action = $this->get_config('expiredaction', ENROL_EXT_REMOVED_KEEP);

        if ($action == ENROL_EXT_REMOVED_UNENROL) {
            $instances = array();
            $sql = "SELECT ue.*, e.courseid, c.id AS contextid
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = :enrol)
                      JOIN {context} c ON (c.instanceid = e.courseid AND c.contextlevel = :courselevel)
                     WHERE ue.timeend > 0 AND ue.timeend < :now $coursesql";
            $params = array('now' => time(), 'courselevel' => CONTEXT_COURSE, 'enrol' => $name, 'courseid' => $courseid);

            $rs = $DB->get_recordset_sql($sql, $params);
            foreach ($rs as $ue) {
                if (!$processed) {
                    $trace->output("Starting processing of enrol_$name expirations...");
                    $processed = true;
                }
                if (empty($instances[$ue->enrolid])) {
                    $instances[$ue->enrolid] = $DB->get_record('enrol', array('id' => $ue->enrolid));
                }
                $instance = $instances[$ue->enrolid];
                if (!$this->roles_protected()) {
                    // Let's just guess what extra roles are supposed to be removed.
                    if ($instance->roleid) {
                        role_unassign($instance->roleid, $ue->userid, $ue->contextid);
                    }
                }
                // The unenrol cleans up all subcontexts if this is the only course enrolment for this user.
                $this->unenrol_user($instance, $ue->userid);
                $trace->output("Unenrolling expired user $ue->userid from course $instance->courseid", 1);
                if ($instance->expirynotify) {
                    $user = $DB->get_record('user', array('id' => $ue->userid));
                    $this->email_expiry_message($instance, $user );
                }
            }
            $rs->close();
            unset($instances);

        } else if ($action == ENROL_EXT_REMOVED_SUSPENDNOROLES or $action == ENROL_EXT_REMOVED_SUSPEND) {

            $instances = array();
            $sql = "SELECT ue.*, e.courseid, c.id AS contextid
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = :enrol)
                      JOIN {context} c ON (c.instanceid = e.courseid AND c.contextlevel = :courselevel)
                     WHERE ue.timeend > 0 AND ue.timeend < :now
                           AND ue.status = :useractive $coursesql";
            $params = array('now' => time(),
                'courselevel' => CONTEXT_COURSE, 'useractive' => ENROL_USER_ACTIVE, 'enrol' => $name, 'courseid' => $courseid);
            $rs = $DB->get_recordset_sql($sql, $params);
            foreach ($rs as $ue) {
                if (!$processed) {
                    $trace->output("Starting processing of enrol_$name expirations...");
                    $processed = true;
                }
                if (empty($instances[$ue->enrolid])) {
                    $instances[$ue->enrolid] = $DB->get_record('enrol', array('id' => $ue->enrolid));
                }
                $instance = $instances[$ue->enrolid];

                if ($action == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
                    if (!$this->roles_protected()) {
                        // Let's just guess what roles should be removed.
                        $count = $DB->count_records('role_assignments',
                            array('userid' => $ue->userid, 'contextid' => $ue->contextid));
                        if ($count == 1) {
                            role_unassign_all(array('userid' => $ue->userid,
                                'contextid' => $ue->contextid,
                                'component' => '',
                                'itemid' => 0));

                        } else if ($count > 1 and $instance->roleid) {
                            role_unassign($instance->roleid, $ue->userid, $ue->contextid, '', 0);
                        }
                    }
                    // In any case remove all roles that belong to this instance and user.
                    role_unassign_all(array('userid' => $ue->userid,
                        'contextid' => $ue->contextid,
                        'component' => 'enrol_'.$name,
                        'itemid' => $instance->id), true);
                    // Final cleanup of subcontexts if there are no more course roles.
                    if (0 == $DB->count_records('role_assignments', ['userid' => $ue->userid, 'contextid' => $ue->contextid])) {
                        role_unassign_all(array('userid' => $ue->userid,
                            'contextid' => $ue->contextid,
                            'component' => '',
                            'itemid' => 0), true);
                    }
                }

                $this->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
                $trace->output("Suspending expired user $ue->userid in course $instance->courseid", 1);
                if ($instance->expirynotify) {
                    $user = $DB->get_record('user', array('id' => $ue->userid));
                    $this->email_expiry_message($instance, $user );
                }

            }
            $rs->close();
            unset($instances);

        }

        if ($processed) {
            $trace->output("...finished processing of enrol_$name expirations");
        } else {
            $trace->output("No expired enrol_$name enrolments detected");
        }
        $trace->finished();

        return $processed;
    }

    public function enrol_user(stdClass $instance, $userid, $roleid = null, $timestart = 0, $timeend = 0, $status = null, $recovergrades = null) {
        global $DB;
        // Enrolment period handling.
        $timestart = time();
        if ($instance->enrolperiod) {
            $timeend = $timestart + $instance->enrolperiod;
        } else {
            $timeend = 0;
        }
        // Send course welcome email.
        $conditions = array('enrolid' => $instance->id, 'userid' => $userid, 'status' => ENROL_USER_ACTIVE);
        $enrolmentexists = $DB->record_exists('user_enrolments', $conditions);
        if (!$enrolmentexists) {
            if ($instance->customint8) {
                set_user_preference('enrol_arlo_coursewelcome_'.$instance->id, $instance->id, $userid);
            }
        }
        parent::enrol_user($instance, $userid, $instance->roleid, $timestart, $timeend, ENROL_USER_ACTIVE);
        if (!empty($instance->customint2) && $instance->customint2 != self::ARLO_CREATE_GROUP) {
            groups_add_member($instance->customint2, $userid, 'enrol_arlo');
        }
    }

    /**
     * Handles un-enrolling a user.
     *
     * @param stdClass $instance
     * @param int $userid
     * @return void
     */
    public function unenrol_user(stdClass $instance, $userid) {
        global $DB;
        if ($DB->record_exists('user_enrolments', array('enrolid' => $instance->id, 'userid' => $userid))) {
            parent::unenrol_user($instance, $userid);
        }
    }

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
 * @param settings_navigation $navigation The settings navigation object
 * @param stdClass $course The course
 * @param stdclass $context Course context
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
