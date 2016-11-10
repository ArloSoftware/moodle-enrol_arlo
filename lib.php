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

/**
 * ARLO_CREATE_GROUP constant for automatically creating a group matched to enrolment instance.
 */
define('ARLO_CREATE_GROUP', -1);
/**
 * ARLO_TYPE_EVENT constant for matched event type to enrolment instance.
 */
define('ARLO_TYPE_EVENT', 0);
/**
 * ARLO_TYPE_ONLINEACTIVITY constant for matched online activity type to enrolment instance.
 */
define('ARLO_TYPE_ONLINEACTIVITY', 1);


class enrol_arlo_plugin extends enrol_plugin {
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

        if (trim($instance->customtext1) !== '') {
            $message = $instance->customtext1;
            $key = array('{$a->coursename}', '{$a->courseurl}', '{$a->fullname}', '{$a->email}');
            $value = array($a->coursename, $a->courseurl, fullname($user), $user->email);
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
        self::get_contact_user($instance);

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
     * Returns edit icons for the page with list of instances.
     *
     * @param stdClass $instance
     * @return array
     * @throws coding_exception
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'arlo') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context =  context_course::instance($instance->courseid);

        $icons = array();

        if (has_capability('enrol/arlo:config', $context)) {
            $editlink = new moodle_url("/enrol/arlo/edit.php",
                array('courseid' => $instance->courseid, 'id' => $instance->id));
            $icons[] = $OUTPUT->action_icon($editlink,
                new pix_icon('t/edit', get_string('edit'), 'core', array('class' => 'iconsmall')));
        }
        
        return $icons;
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
            $actions[] = new user_enrolment_action(new pix_icon('t/delete', ''), get_string('unenrol', 'enrol'), $url, array('class'=>'unenrollink', 'rel'=>$ue->id));
        }
        if ($this->allow_manage($instance) && has_capability("enrol/arlo:manage", $context)) {
            $url = new moodle_url('/enrol/editenrolment.php', $params);
            $actions[] = new user_enrolment_action(new pix_icon('t/edit', ''), get_string('edit'), $url, array('class'=>'editenrollink', 'rel'=>$ue->id));
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
            $params = array('now'=>time(), 'courselevel'=>CONTEXT_COURSE, 'enrol'=>$name, 'courseid'=>$courseid);

            $rs = $DB->get_recordset_sql($sql, $params);
            foreach ($rs as $ue) {
                if (!$processed) {
                    $trace->output("Starting processing of enrol_$name expirations...");
                    $processed = true;
                }
                if (empty($instances[$ue->enrolid])) {
                    $instances[$ue->enrolid] = $DB->get_record('enrol', array('id'=>$ue->enrolid));
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
                    $user = $DB->get_record('user', array('id'=>$ue->userid));
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
            $params = array('now'=>time(), 'courselevel'=>CONTEXT_COURSE, 'useractive'=>ENROL_USER_ACTIVE, 'enrol'=>$name, 'courseid'=>$courseid);
            $rs = $DB->get_recordset_sql($sql, $params);
            foreach ($rs as $ue) {
                if (!$processed) {
                    $trace->output("Starting processing of enrol_$name expirations...");
                    $processed = true;
                }
                if (empty($instances[$ue->enrolid])) {
                    $instances[$ue->enrolid] = $DB->get_record('enrol', array('id'=>$ue->enrolid));
                }
                $instance = $instances[$ue->enrolid];

                if ($action == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
                    if (!$this->roles_protected()) {
                        // Let's just guess what roles should be removed.
                        $count = $DB->count_records('role_assignments', array('userid'=>$ue->userid, 'contextid'=>$ue->contextid));
                        if ($count == 1) {
                            role_unassign_all(array('userid'=>$ue->userid, 'contextid'=>$ue->contextid, 'component'=>'', 'itemid'=>0));

                        } else if ($count > 1 and $instance->roleid) {
                            role_unassign($instance->roleid, $ue->userid, $ue->contextid, '', 0);
                        }
                    }
                    // In any case remove all roles that belong to this instance and user.
                    role_unassign_all(array('userid'=>$ue->userid, 'contextid'=>$ue->contextid, 'component'=>'enrol_'.$name, 'itemid'=>$instance->id), true);
                    // Final cleanup of subcontexts if there are no more course roles.
                    if (0 == $DB->count_records('role_assignments', array('userid'=>$ue->userid, 'contextid'=>$ue->contextid))) {
                        role_unassign_all(array('userid'=>$ue->userid, 'contextid'=>$ue->contextid, 'component'=>'', 'itemid'=>0), true);
                    }
                }

                $this->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
                $trace->output("Suspending expired user $ue->userid in course $instance->courseid", 1);
                if ($instance->expirynotify) {
                    $user = $DB->get_record('user', array('id'=>$ue->userid));
                    $this->email_expiry_message($instance, $user );
                }

            }
            $rs->close();
            unset($instances);

        } else {
            // ENROL_EXT_REMOVED_KEEP means no changes.
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
 * Prevent removal of enrol roles.
 *
 * @param int $itemid
 * @param int $groupid
 * @param int $userid
 * @return bool
*/
function enrol_arlo_allow_group_member_remove($itemid, $groupid, $userid) {
    return false;
}

/**
 * Create new group or returns the existing group id based on matching
 * code to group idnumber.
 *
 * @param $courseid - course where to create group.
 * @param $table - resource table local_arlo_events or local_arlo_onlineactivities.
 * @param $field - guid field eventguid or onlineactivityguid.
 * @param $identifier - resource guid.
 * @return id
 * @throws coding_exception
 * @throws moodle_exception
 */
function enrol_arlo_create_new_group($courseid, $table, $field, $identifier) {
    global $DB;

    $code = $DB->get_field($table, 'code', array($field => $identifier), MUST_EXIST);

    // Already have group, return id.
    $exists = $DB->get_record('groups', array('idnumber' => $code, 'courseid' => $courseid));
    if ($exists) {
        return $exists->id;
    }

    $a = new stdClass();
    $a->name = $code;
    $a->increment = '';
    $groupname = trim(get_string('defaultgroupnametext', 'enrol_arlo', $a));

    // Create a new group for the for event or online activity.
    $groupdata = new stdClass();
    $groupdata->courseid = $courseid;
    $groupdata->name = $groupname;
    $groupdata->idnumber = $code;

    $groupid = groups_create_group($groupdata);

    return $groupid;
}

/**
 * Break key string which is combination of Arlo resource type and
 * guid identifier into their separate parts.
 *
 * @param $key
 * @return array
 */
function enrol_arlo_break_apart_key($key) {
    $type = '';
    $guid = '';
    $pattern = '/(\d.*):(.*)/'; // Break apart (type) : (guid).
    preg_match($pattern, $key, $matches);
    if (!empty($matches)) {
        $type = $matches[1];
        $guid = $matches[2];
    }
    return array($type, $guid);
}

/**
 * Make key based on Arlo resource type ans guid identifier.
 *
 * @param $type
 * @param $guid
 * @return string
 */
function enrol_arlo_make_select_key($type, $guid) {
    return $type . ':' . $guid;
}
