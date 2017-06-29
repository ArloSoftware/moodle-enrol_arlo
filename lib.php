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

use enrol_arlo\Arlo\AuthAPI\Enum\EventStatus;
use enrol_arlo\Arlo\AuthAPI\Enum\OnlineActivityStatus;

/**
 * ARLO_CREATE_GROUP constant for automatically creating a group matched to enrolment instance.
 */
define('ARLO_CREATE_GROUP', -1);


class enrol_arlo_plugin extends enrol_plugin {
    const ARLO_TYPE_EVENT           = 'event';
    const ARLO_TYPE_ONLINEACTIVITY  = 'onlineactivity';
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
        $instance->platform = self::get_config('platformname');
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
                throw new moodle_exception('Field arloonlineactivity is empty.');
            }
            $sourcetable = 'enrol_arlo_onlineactivity';
            $sourceguid = $fields['arloonlineactivity'];
        }
        $conditions = array('platform' => $instance->platform, 'sourceguid' => $sourceguid);
        $record = $DB->get_record($sourcetable, $conditions, '*', MUST_EXIST);
        $instance->sourceid = $record->sourceid;
        $instance->sourceguid = $record->sourceguid;
        // Set name to be passed to parent.
        $fields['name'] = $record->code;
        // TODO Group creation.
        $instance->enrolid = parent::add_instance($course, $fields);
        $DB->insert_record('enrol_arlo_instance', $instance);
        return $instance->enrolid;
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
        global $DB;

        //parent::update_instance($instance, $data);
        die('Add to Arlo instance table.');

        //return parent::update_instance($instance, $data);
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

    public function get_event_options() {
        global $DB;
        $options = array();
        $conditions = array(
            'platform' => self::get_config('platformname', null),
            'sourcestatus' => EventStatus::ACTIVE);
        // TODO - Remove Events currently attached to enrol instances for list.
        $records = $DB->get_records('enrol_arlo_event', $conditions, 'code', 'sourceguid, code');
        foreach ($records as $record) {
            $options[$record->sourceguid] = $record->code;
        }
        return $options;
    }

    public function get_onlineactivity_options() {
        global $DB;
        $options = array();
        $conditions = array(
            'platform' => self::get_config('platformname', null),
            'sourcestatus' => OnlineActivityStatus::ACTIVE);
        // TODO - Remove Events currently attached to enrol instances for list.
        $records = $DB->get_records('enrol_arlo_onlineactivity', $conditions, 'code', 'sourceguid, code');
        foreach ($records as $record) {
            $options[$record->sourceguid] = $record->code;
        }
        return $options;
    }

    /**
     * Returns defaults for new instances.
     * @return array
     */
    public function get_instance_defaults() {
        $fields = array();
        $fields['status']               = $this->get_config('status');
        $fields['roleid']               = $this->get_config('roleid');
        $fields['enrolperiod']          = 0;
        $fields['expirynotify']         = 0;
        $fields['customint8']           = 1; //$this->get_config('sendcoursewelcomemessage');
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
        global $CFG;

        $eventoptions = $this->get_event_options();
        $onlineactivityoptions = $this->get_onlineactivity_options();

        // If there are no Active Events or Online Activities hide form elements.
        if (!$onlineactivityoptions && !$eventoptions) {
            $mform->addElement('static', 'noeventsoractivitiesfound', null,
                get_string('noeventsoractivitiesfound', 'enrol_arlo'));
            $mform->addElement('hidden', 'disable');
            $mform->setType('disable', PARAM_INT);
            $mform->setDefault('disable', 1);
            $mform->disabledIf('submitbutton', 'disable', 'eq', 1);
        } else {
            $options = $this->get_status_options();
            $mform->addElement('select', 'status', get_string('status', 'enrol_arlo'), $options);

            $typeoptions = $this->get_type_options();
            $mform->addElement('select', 'arlotype', get_string('type', 'enrol_arlo'), $typeoptions);
            // Event selector.
            array_unshift($eventoptions, get_string('choose') . '...');
            $mform->addElement('select', 'arloevent', get_string('event', 'enrol_arlo'), $eventoptions);
            $mform->disabledIf('arloevent', 'arlotype', 'eq', self::ARLO_TYPE_ONLINEACTIVITY);
            // Online Activity selector.
            array_unshift($onlineactivityoptions, get_string('choose') . '...');
            $mform->addElement('select', 'arloonlineactivity',
                get_string('onlineactivity', 'enrol_arlo'), $onlineactivityoptions);
            $mform->disabledIf('arloonlineactivity', 'arlotype', 'eq', self::ARLO_TYPE_EVENT);

            $options = array('optional' => true, 'defaultunit' => 86400);
            $mform->addElement('duration', 'enrolperiod', get_string('enrolperiod', 'enrol_arlo'), $options);
            $mform->addHelpButton('enrolperiod', 'enrolperiod', 'enrol_self');

            $options = array(0 => get_string('no'), 1 => get_string('yes'));
            $mform->addElement('select', 'expirynotify', get_string('expirynotify', 'enrol_arlo'), $options);
            $mform->addHelpButton('expirynotify', 'expirynotify', 'enrol_arlo');
            $mform->addElement('advcheckbox', 'customint8', get_string('sendcoursewelcomemessage', 'enrol_arlo'));
            $mform->addHelpButton('customint8', 'sendcoursewelcomemessage', 'enrol_arlo');
            $mform->addElement('textarea', 'customtext1',
                get_string('customwelcomemessage', 'enrol_arlo'),
                array('cols' => '60', 'rows' => '8'));
            $mform->addHelpButton('customtext1', 'customwelcomemessage', 'enrol_arlo');
        }

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
        email_to_user($user, $contact, $subject, $messagetext, $messagehtml);
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

        $context = context_course::instance($instance->courseid);

        $icons = array();
        if (has_capability('enrol/arlo:synchronizeinstance', $context)) {
            $link = new moodle_url('enrol/arlo/synchronizeinstance.php', array('id' => $instance->id));
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
            $url = new moodle_url('/enrol/arlo/linktemplate.php', array('courseid' => $context->instanceid));
            $label = get_string('associatearlotemplate', 'enrol_arlo');
            $settingsnode = navigation_node::create($label, $url, navigation_node::TYPE_SETTING,
                null, null, new pix_icon('i/twoway', ''));
            $navigation->add_node($settingsnode);
        }
    }
}
