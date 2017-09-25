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

/**
 * Alert admin on integration problems.
 *
 * Each alert must define following locale lang strings based on passed in identifier:
 *
 *  - $identifier_subject
 *  - $identifier_smallmessage
 *  - $identifier_full
 *  - $identifier_fullhtml
 *
 * An associative array of can be passed to provide more context specific information
 * to the lang string.
 *
 * Example:
 *          $params['configurl' => 'someurl', 'level' => 'warning'];
 */
class alert {
    private $identifier;
    private $params;
    private $applog;
    public static function create($identifier, $params) {
        if (empty($identifier) && !is_string($identifier)) {
            throw new \Exception('Alert identifier is empty or not a string.');
        }
        if (empty($params) && !is_array($params)) {
            throw new \Exception('Alert params is empty or not an array.');
        }
        $alert = new alert();
        $alert->identifier = $identifier;
        $alert->params     = $params;
        return $alert;
    }
    public function send() {
        global $DB;
        $alert = (bool) get_config('enrol_arlo', 'alertsiteadmins');
        if (!$alert) {
            return false;
        }
        $identifier = $this->identifier;
        $params     = $this->params;
        // Setup message class.
        $message                    = new \core\message\message();
        $message->component         = 'enrol_arlo';
        $message->name              = 'alerts';
        $message->notification      = 1;
        $message->userfrom          = \core_user::get_noreply_user();
        $message->subject           = get_string($identifier . '_subject', 'enrol_arlo', $params);
        $message->fullmessage       = get_string($identifier . '_full', 'enrol_arlo', $params);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessagehtml   = get_string($identifier . '_fullhtml', 'enrol_arlo', $params);
        $message->smallmessage      = get_string($identifier . '_smallmessage', 'enrol_arlo', $params);
        // Send to all administrators.
        foreach (get_admins() as $admin) {
            $messagecopy = clone($message);
            $messagecopy->userto = $admin;
            message_send($messagecopy);
        }
        return true;
    }

}
