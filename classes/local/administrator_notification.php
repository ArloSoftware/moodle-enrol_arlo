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
 * Administrator notifications.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local;

defined('MOODLE_INTERNAL') || die();

use core_user;
use core\message\message;
use moodle_url;
use stdClass;
use coding_exception;

class administrator_notification {

    /** @var stdClass $mainadmin */
    protected static $mainadmin;

    /**
     * Get main administrator.
     *
     * @return mixed
     * @throws coding_exception
     */
    public static function get_main_admin() {
        if (is_null(static::$mainadmin)) {
            $mainadmin = get_admin();
            if (!$mainadmin) {
                throw new coding_exception('mainadminmissing');
            }
            static::$mainadmin = $mainadmin;
        }
        return static::$mainadmin;
    }

    /**
     * Unsuccessful enrolment administrator notification.
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public static function send_unsuccessful_enrolment_message() {
        $admins = get_admins();
        if (empty($admins)) {
            return;
        }
        $extendedproperties = true;
        if (moodle_major_version() < 3.4) {
            $extendedproperties = false;
        }
        $url = new moodle_url('/enrol/arlo/admin/unsuccessfulenrolments.php');
        $params = ['report' => $url->out()];
        foreach ($admins as $admin) {
            $message                    = new message();
            $message->component         = 'enrol_arlo';
            $message->name              = 'administratornotification';
            $message->userfrom          = core_user::get_noreply_user();
            $message->userto            = $admin;
            $message->subject           = get_string('unsuccessfulenrolment_subject', 'enrol_arlo');
            $message->fullmessage       = get_string('unsuccessfulenrolment_fullmessage', 'enrol_arlo', $params);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml   = get_string('unsuccessfulenrolment_fullmessagehtml', 'enrol_arlo', $params);
            $message->smallmessage      = get_string('unsuccessfulenrolment_smallmessage', 'enrol_arlo', $params);
            $message->notification      = 1;
            if ($extendedproperties) {
                $message->courseid          = SITEID;
                $message->contexturl        = $url;
                $message->contexturlname    = 'Report';
            }
            message_send($message);
        }
    }

    /**
     * Invalid credentials administrator notification.
     *
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public static function send_invalid_credentials_message() {
        $admins = get_admins();
        if (empty($admins)) {
            return;
        }
        $extendedproperties = true;
        if (moodle_major_version() < 3.4) {
            $extendedproperties = false;
        }
        $url = new moodle_url('/admin/settings.php', ['section' => 'enrolsettingsarlo']);
        $params = ['url' => $url->out()];
        foreach ($admins as $admin) {
            $message                    = new message();
            $message->component         = 'enrol_arlo';
            $message->name              = 'administratornotification';
            $message->userfrom          = core_user::get_noreply_user();
            $message->userto            = $admin;
            $message->subject           = get_string('invalidcredentials_subject', 'enrol_arlo');
            $message->fullmessage       = get_string('invalidcredentials_fullmessage', 'enrol_arlo', $params);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml   = get_string('invalidcredentials_fullmessagehtml', 'enrol_arlo', $params);
            $message->smallmessage      = get_string('invalidcredentials_smallmessage', 'enrol_arlo', $params);
            $message->notification      = 1;
            if ($extendedproperties) {
                $message->courseid          = SITEID;
                $message->contexturl        = $url;
                $message->contexturlname    = 'Connection';
            }
            message_send($message);
        }
    }

    /**
     * Suspended user account administrator notification.
     *
     * @param stdClass $usersuspended
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public static function send_user_account_suspended_message(stdClass $usersuspended) {
        $admins = get_admins();
        if (empty($admins)) {
            return;
        }
        $extendedproperties = true;
        if (moodle_major_version() < 3.4) {
            $extendedproperties = false;
        }
        $url = new moodle_url('/user/profile.php', ['id' => $usersuspended->id]);
        $params = ['fullname' => fullname($usersuspended), 'profileurl' => $url->out()];
        foreach ($admins as $admin) {
            $message                    = new message();
            $message->component         = 'enrol_arlo';
            $message->name              = 'administratornotification';
            $message->userfrom          = core_user::get_noreply_user();
            $message->userto            = $admin;
            $message->subject           = get_string('suspendeduser_subject', 'enrol_arlo');
            $message->fullmessage       = get_string('suspendeduser_fullmessage', 'enrol_arlo', $params);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml   = get_string('suspendeduser_fullmessagehtml', 'enrol_arlo', $params);
            $message->smallmessage      = get_string('suspendeduser_smallmessage', 'enrol_arlo', $params);
            $message->notification      = 1;
            if ($extendedproperties) {
                $message->courseid          = SITEID;
                $message->contexturl        = $url->out();
                $message->contexturlname    = get_string('browseuserprofile', 'enrol_arlo');
            }
            message_send($message);
        }
    }

}
