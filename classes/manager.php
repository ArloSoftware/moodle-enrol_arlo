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

namespace enrol_arlo;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/enrol/arlo/lib.php");

class manager {
    const EMAIL_TYPE_NEW_ACCOUNT    = 'newaccount';
    const EMAIL_TYPE_COURSE_WELCOME = 'coursewelcome';
    const EMAIL_TYPE_NOTIFY_EXPIRY  = 'notifyexpiry';
    const EMAIL_STATUS_QUEUED       = 100;
    const EMAIL_STATUS_DELIVERED    = 200;
    const EMAIL_STATUS_FAILED       = 500;
    const EMAIL_PROCESSING_LIMIT    = 250;

    private static $plugin;
    /** @var \progress_trace  */
    private static $trace;

    public function __construct(\progress_trace $trace = null) {
        // Raise limits, so this script can be interrupted without problems.
        \core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);
        // Setup trace.
        if (is_null($trace)) {
            self::$trace = new \null_progress_trace();
        } else {
            self::$trace = $trace;
        }
        self::$plugin = new \enrol_arlo_plugin();
    }

    /**
     * Queue a email type for later processing.
     *
     * @param $area
     * @param $instanceid
     * @param $userid
     * @param $type
     * @param int $status
     * @return bool|int
     * @throws \dml_exception
     */
    public function add_email_to_queue($area, $instanceid, $userid, $type, $status = self::EMAIL_STATUS_QUEUED) {
        global $DB;

        switch ($type) {
            case self::EMAIL_TYPE_NEW_ACCOUNT:
            case self::EMAIL_TYPE_COURSE_WELCOME:
            case self::EMAIL_TYPE_NOTIFY_EXPIRY:
                break;
            default: // Type not supported.
                return false;
        }
        $record               = new \stdClass();
        $record->area         = $area;
        $record->instanceid   = $instanceid;
        $record->userid       = $userid;
        $record->type         = $type;
        $record->status       = $status;
        $record->timecreated  = time();
        $record->timemodified = time();
        $record->id           = $DB->insert_record('enrol_arlo_emailqueue', $record);
        return $record->id;
    }

    /**
     * Update email log entries status in queue table.
     *
     * @param $enrolid
     * @param $userid
     * @param $type
     * @param $status
     */
    public function update_email_status_queue($area, $instanceid, $userid, $type, $status) {
        global $DB;
        $conditions = array('area' => $area, 'instanceid' => $instanceid, 'userid' => $userid, 'type' => $type);
        $record = $DB->get_record('enrol_arlo_emailqueue', $conditions);
        if ($record) {
            $record->status = $status;
            $record->modified = time();
            $DB->update_record('enrol_arlo_emailqueue', $record);
        }
        return;
    }

    /**
     * Process the email queue. Can be off loaded to php cli/processemailqueue.php
     * for sites that have courses with 1000's of Arlo registrations. This to so the plugin
     * doesn't block any other scheduled tasks.
     */
    public function process_email_queue() {
        global $DB;
        $timestart = microtime();
        $plugin = self::$plugin;
        if ($plugin->get_config('sendemailimmediately', 1)) {
            self::trace('Email processing is configured to send immediately, skipping.');
            return;
        }
        $emailprocessingviacli = $plugin->get_config('emailprocessingviacli', 0);
        if ($emailprocessingviacli && !defined('ENROL_ARLO_CLI_EMAIL_PROCESSING')) {
            self::trace('Email processing is configured to send via cli, skipping.');
            return;
        }
        // Create lock and check if locked.
        $lockfactory = \core\lock\lock_config::get_lock_factory('enrol_arlo_email_queue');
        if (!$lock = $lockfactory->get_lock('enrol_arlo_email_queue', self::LOCK_TIMEOUT_DEFAULT)) {
            throw new \moodle_exception('locktimeout');
        }
        // Setup caches.
        $instances          = array();
        $deletedinstances   = array();
        $users              = array();
        $deletedusers       = array();
        self::trace('Process new account emails');
        // Process new account emails.
        $conditions = array('type' => self::EMAIL_TYPE_NEW_ACCOUNT, 'status' => self::EMAIL_STATUS_QUEUED);
        $rs = $DB->get_recordset('enrol_arlo_emailqueue', $conditions, 'modified', '*',
            0, self::EMAIL_PROCESSING_LIMIT);
        foreach ($rs as $record) {
            $user = $DB->get_record('user', array('id' => $record->userid));
            if (!$user) {
                // Clean up.
                $DB->delete_records('enrol_arlo_emailqueue', array('userid' => $record->userid));
                continue;
            }
            $status = self::email_newaccountdetails(null, $user);
            $deliverystatus = ($status) ? self::EMAIL_STATUS_DELIVERED : self::EMAIL_STATUS_FAILED;
            self::update_email_status_queue('site', SITEID, $user->id, self::EMAIL_TYPE_NEW_ACCOUNT, $deliverystatus);
        }
        $rs->close();
        // Process course welcome emails.
        self::trace('Process course welcome emails');
        $conditions = array('type' => self::EMAIL_TYPE_COURSE_WELCOME, 'status' => self::EMAIL_STATUS_QUEUED);
        $rs = $DB->get_recordset('enrol_arlo_emailqueue', $conditions, 'modified', '*',
            0, self::EMAIL_PROCESSING_LIMIT);
        foreach ($rs as $record) {
            $instance = $DB->get_record('enrol', array('id' => $record->enrolid));
            if (!$instance) {
                // Clean up.
                $DB->delete_records('enrol_arlo_emailqueue', array('enrolid' => $record->enrolid));
                continue;
            }
            $user = $DB->get_record('user', array('id' => $record->userid));
            if (!$user) {
                // Clean up.
                $DB->delete_records('enrol_arlo_emailqueue', array('userid' => $record->userid));
                continue;
            }
            $status = self::email_coursewelcome($instance, $user);
            $deliverystatus = ($status) ? self::EMAIL_STATUS_DELIVERED : self::EMAIL_STATUS_FAILED;
            self::update_email_status_queue('enrolment', $instance->id, $user->id, self::EMAIL_TYPE_COURSE_WELCOME, $deliverystatus);
        }
        $rs->close();
        // Process expiration emails.
        self::trace('Process course expiration emails');
        $conditions = array('type' => self::EMAIL_TYPE_NOTIFY_EXPIRY, 'status' => self::EMAIL_STATUS_QUEUED);
        $rs = $DB->get_recordset('enrol_arlo_emailqueue', $conditions, 'modified', '*',
            0, self::EMAIL_PROCESSING_LIMIT);
        foreach ($rs as $record) {
            $instance = $DB->get_record('enrol', array('id' => $record->enrolid));
            if (!$instance) {
                // Clean up.
                $DB->delete_records('enrol_arlo_emailqueue', array('enrolid' => $record->enrolid));
                continue;
            }
            $user = $DB->get_record('user', array('id' => $record->userid));
            if (!$user) {
                // Clean up.
                $DB->delete_records('enrol_arlo_emailqueue', array('userid' => $record->userid));
                continue;
            }
            $status = self::email_expirynotice($instance, $user);
            $deliverystatus = ($status) ? self::EMAIL_STATUS_DELIVERED : self::EMAIL_STATUS_FAILED;
            self::update_email_status_queue('enrolment', $instance->id, $user->id, self::EMAIL_TYPE_NOTIFY_EXPIRY, $deliverystatus);
        }
        $rs->close();
        $timefinish = microtime();
        $difftime = microtime_diff($timestart, $timefinish);
        self::trace("Execution took {$difftime} seconds");
        $lock->release();
        return true;
    }

    /**
     * Generate password for new user and email.
     *
     * @param $instance
     * @param $user
     * @return bool
     */
    public function email_newaccountdetails($instance, $user) {
        global $CFG, $DB;
        // We try to send the mail in language the user understands,
        // unfortunately the filter_string() does not support alternative langs yet
        // so multilang will not work properly for site->fullname.
        $lang = empty($user->lang) ? $CFG->lang : $user->lang;
        $site  = get_site();
        $noreplyuser = \core_user::get_noreply_user();
        $newpassword = generate_password();

        update_internal_user_password($user, $newpassword);
        set_user_preference('auth_forcepasswordchange', true, $user->id);

        $a = new \stdClass();
        $a->firstname   = fullname($user, true);
        $a->sitename    = format_string($site->fullname);
        $a->username    = $user->username;
        $a->newpassword = $newpassword;
        $a->link        = $CFG->wwwroot .'/login/';
        $a->signoff     = generate_email_signoff();

        $message = get_string('newusernewpasswordtext', '', $a);
        $subject = format_string($site->fullname) .': '. get_string('newusernewpasswordsubj', '', $a);
        $status = email_to_user($user, $noreplyuser, $subject, $message);
        $deliverystatus = get_string('delivered', 'enrol_arlo');
        if (!$status) {
            $deliverystatus = get_string('failed', 'enrol_arlo');
        }
        self::trace(sprintf("New account details email to user %s %s", $user->id, $deliverystatus));
        return $status;
    }

    /**
     * Send course welcome email to specified user.
     *
     * @param $instance
     * @param $user
     * @return bool
     */
    public function email_coursewelcome($instance, $user) {
        global $CFG, $DB;

        $noreplyuser = \core_user::get_noreply_user();
        $course = $DB->get_record('course', array('id' => $instance->courseid), '*', MUST_EXIST);
        $context = \context_course::instance($course->id);
        $a = new \stdClass();
        $a->coursename = format_string($course->fullname, true, array('context' => $context));
        $a->courseurl = "$CFG->wwwroot/course/view.php?id=$course->id";
        $a->username = $user->username;
        $a->firstname = $user->firstname;
        $a->fullname = fullname($user);
        $a->email = $user->email;
        $a->forgotpasswordurl = "$CFG->wwwroot/login/forgot_password.php";
        if (trim($instance->customtext1) !== '') {
            $message = $instance->customtext1;
            $key = array(
                '{$a->coursename}',
                '{$a->courseurl}',
                '{$a->username}',
                '{$a->firstname}',
                '{$a->fullname}',
                '{$a->email}',
                '{$a->forgotpasswordurl}');
            $value = array(
                $a->coursename,
                $a->courseurl,
                $a->username,
                $a->firstname,
                $a->fullname,
                $a->email,
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

        $status = email_to_user($user, $noreplyuser, $subject, $messagetext, $messagehtml);
        $deliverystatus = get_string('delivered', 'enrol_arlo');
        if (!$status) {
            $deliverystatus = get_string('failed', 'enrol_arlo');
        }
        self::trace(sprintf("Course welcome email to user %s %s", $user->id, $deliverystatus));
        return $status;
    }

    /**
     * Notify user their course expiry. Only if notification of enrolled users (aka students) is enabled in course.
     *
     *
     * @param $instance
     * @param $user
     * @return bool
     */
    public function email_expirynotice($instance, $user) {
        global $CFG, $DB;

        $noreplyuser = \core_user::get_noreply_user();
        $course = $DB->get_record('course', array('id' => $instance->courseid), '*', MUST_EXIST);
        $context = \context_course::instance($course->id);

        $a              = new \stdClass();
        $a->coursename  = format_string($course->fullname, true, array('context' => $context));
        $a->courseurl   = "$CFG->wwwroot/course/view.php?id=$course->id";
        $a->user        = fullname($user, true);

        $subject        = get_string('expirymessagesubject', 'enrol_arlo', $a);
        $messagetext    = get_string('expirymessagetext', 'enrol_arlo', $a);
        $messagehtml    = text_to_html($messagetext, null, false, true);

        $status = email_to_user($user, $noreplyuser, $subject, $messagetext, $messagehtml);
        $deliverystatus = get_string('delivered', 'enrol_arlo');
        if (!$status) {
            $deliverystatus = get_string('failed', 'enrol_arlo');
        }
        self::trace(sprintf("Emrolment expiry email to user %s %s", $user->id, $deliverystatus));
        return $status;
    }

    /**
     * Overrides parent implementation to allow user notification on enrolment expiration.
     *
     * @param $instance
     * @param $userenrolment
     */
    public function process_expiration($instance, $userenrolment) {
        global $DB;
        // Deal with expired accounts.
        $action = self::$plugin->get_config('expiredaction', ENROL_EXT_REMOVED_KEEP);
        if ($action == ENROL_EXT_REMOVED_SUSPENDNOROLES or $action == ENROL_EXT_REMOVED_SUSPEND) {
            if ($action == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
                if (!self::$plugin->roles_protected()) {
                    // Let's just guess what roles should be removed.
                    $count = $DB->count_records('role_assignments',
                        array('userid' => $userenrolment->userid, 'contextid' => $userenrolment->contextid));
                    if ($count == 1) {
                        role_unassign_all(array('userid' => $userenrolment->userid,
                            'contextid' => $userenrolment->contextid,
                            'component' => '',
                            'itemid' => 0));

                    } else if ($count > 1 and $instance->roleid) {
                        role_unassign($instance->roleid, $userenrolment->userid, $userenrolment->contextid, '', 0);
                    }
                }
                // In any case remove all roles that belong to this instance and user.
                role_unassign_all(array('userid' => $userenrolment->userid,
                    'contextid' => $userenrolment->contextid,
                    'component' => 'enrol_arlo',
                    'itemid' => $instance->id), true);
                // Final cleanup of subcontexts if there are no more course roles.
                if (0 == $DB->count_records('role_assignments', ['userid' => $userenrolment->userid, 'contextid' => $userenrolment->contextid])) {
                    role_unassign_all(array('userid' => $userenrolment->userid,
                        'contextid' => $userenrolment->contextid,
                        'component' => '',
                        'itemid' => 0), true);
                }
            }
            // Update the users enrolment status.
            self::$plugin->update_user_enrol($instance, $userenrolment->userid, ENROL_USER_SUSPENDED);
            self::add_email_to_queue('enrolment', $instance->id, $userenrolment->userid, self::EMAIL_TYPE_NOTIFY_EXPIRY);
        }
    }
    /**
     * Process enrolment expirations.
     *
     * TODO - Do we really need this? External source a.k.a Arlo should be
     * in control of enrolment expiration.
     */
    public function process_expirations() {
        global $DB;
        $instances = array(); // Cache.
        $sql = "SELECT ue.*, e.courseid, c.id AS contextid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = :enrol)
                  JOIN {context} c ON (c.instanceid = e.courseid AND c.contextlevel = :courselevel)
                 WHERE ue.timeend > 0 AND ue.timeend < :now
                   AND ue.status = :useractive";
        $conditions = array(
            'now' => time(),
            'courselevel' => CONTEXT_COURSE,
            'useractive' => ENROL_USER_ACTIVE,
            'enrol' => 'arlo'
        );
        $rs = $DB->get_recordset_sql($sql, $conditions);
        foreach ($rs as $ue) {
            if (empty($instances[$ue->enrolid])) {
                $instances[$ue->enrolid] = $DB->get_record('enrol', array('id' => $ue->enrolid));
                $instance = $instances[$ue->enrolid];
                self::process_expiration($instance, $ue);
            }
        }
        $rs->close();
    }

    /**
     * Output a progress message.
     *
     * @param $message the message to output.
     * @param int $depth indent depth for this message.
     */
    private function trace($message, $depth = 0) {
        self::$trace->output($message, $depth);
    }

}
