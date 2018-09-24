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
 * Adminstrator notifications.
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

class administrator_notification {

    /**
     * Unsuccessful enrolment adminstrator notification.
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
        if (moodle_major_version() < 3.5) {
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
            $message->fullmessagehtml   = get_string('unsuccessfulenrolment_fullmessage', 'enrol_arlo', $params);
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
     * Invalid credentials adminstrator notification.
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
        if (moodle_major_version() < 3.5) {
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

}
