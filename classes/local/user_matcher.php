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

namespace enrol_arlo\local;

defined('MOODLE_INTERNAL') || die();

use coding_exception;
use enrol_arlo\api;
use enrol_arlo\local\enum\user_matching;
use enrol_arlo\local\persistent\contact_persistent;

class user_matcher {

    /**
     * Get matches based on plugin configuration setting.
     *
     * @param contact_persistent $contact
     * @return array
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function get_matches_based_on_preference(contact_persistent $contact) {
        $pluginconfig = api::get_enrolment_plugin()->get_plugin_config();
        $matchuseraccountsby = $pluginconfig->get('matchuseraccountsby');
        $firstname = $contact->get('firstname');
        $lastname = $contact->get('lastname');
        $email = $contact->get('email');
        $idnumber = $contact->get('codeprimary');
        // Match by user details.
        if ($matchuseraccountsby == user_matching::MATCH_BY_USER_DETAILS) {
            return static::match_against_user_details($firstname, $lastname, $email);
        }
        // Match by code primary.
        if ($matchuseraccountsby == user_matching::MATCH_BY_CODE_PRIMARY) {
            return static::match_against_idnumber($idnumber);
        }
        // Auto matching.
        if ($matchuseraccountsby == user_matching::MATCH_BY_AUTO) {
            $matches = static::match_against_user_details($firstname, $lastname, $email);
            if (empty($matches)) {
                return static::match_against_idnumber($idnumber);
            }
        }
        return [];
    }

    /**
     * Match against Firstname, Lastname and Email information. Uses LOWER to case insensitive matching.
     *
     * @param $firstname
     * @param $lastname
     * @param $email
     * @return array
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function match_against_user_details($firstname, $lastname, $email) {
        global $DB;
        $firstname  = clean_param($firstname, PARAM_TEXT);
        $lastname   = clean_param($lastname, PARAM_TEXT);
        $email      = clean_param($email, PARAM_EMAIL);
        if (empty($firstname)) {
            throw new coding_exception('Firstname parameter is empty after being cleaned.');
        }
        if (empty($lastname)) {
            throw new coding_exception('Lastname parameter is empty after being cleaned.');
        }
        if (empty($email)) {
            throw new coding_exception('Email parameter is empty after being cleaned.');
        }
        $select = "LOWER(firstname) = LOWER(:firstname) AND
                   LOWER(lastname) = LOWER(:lastname) AND
                   LOWER(email) = LOWER(:email) AND
                   deleted <> :deleted";
        $conditions = [
            'firstname' => $firstname,
            'lastname' => $lastname,
            'email' => $email,
            'deleted' => 1
        ];
        return $DB->get_records_select('user', $select, $conditions);
    }

    /**
     * Match against idnumber.
     *
     * @param $idnumber
     * @return array
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function match_against_idnumber($idnumber) {
        global $DB;
        $idnumber = clean_param($idnumber, PARAM_TEXT);
        if (empty($idnumber)) {
            return [];
        }
        return $DB->get_records('user', ['idnumber' => $idnumber, 'deleted' => 0]);
    }

}
