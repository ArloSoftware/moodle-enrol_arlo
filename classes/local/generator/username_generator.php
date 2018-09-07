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
 * Moodle username generator.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\generator;

use coding_exception;
use core_text;
use enrol_arlo\local\persistent\contact_persistent;
use moodle_exception;

class username_generator {

    /**
     * Generate username based on passed in firstname, lastname and email.
     *
     * Order:
     *
     *  1. Use first 3 letters of firstname + first 3 letters of lastname + random 3 digit number.
     *  2. Use email username address before @ symbol.
     *  3. Use email username address before @ symbol + random 3 digit number.
     *  4. Use full email address.
     *  5. Use full email address + random 3 digit number.
     *
     * @param $firstname
     * @param $lastname
     * @param $email
     * @return int|mixed|string
     * @throws \dml_exception
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function generate($firstname, $lastname, $email) {
        global $DB;

        $tries = 0;
        $randmax = 999;
        $exists = true;
        while ($exists) {
            ++$tries;
            switch($tries) {
                case 1;
                    $username = static::create_from_first_and_last_names($firstname, $lastname, 3, $randmax);
                    break;
                case 2:
                    $username = static::create_from_email_address_local_part($email);
                    break;
                case 3:
                    $username = static::create_from_email_address_local_part($email, $randmax);
                    break;
                case 4:
                    $username = static::create_from_email_address($email);
                    break;
                case 5:
                    $username = static::create_from_email_address($email, $randmax);
                    break;
                default:
                    throw new moodle_exception('Failed to generate username');
            }
            $username = core_text::strtolower($username);
            $exists = $DB->get_record('user', ['username' => $username]);
        }
        return $username;
    }

    /**
     * Create from contact persistent information.
     *
     * @param contact_persistent $contact
     * @return int|mixed|string
     * @throws \dml_exception
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function create_from_contact_persistent(contact_persistent $contact) {
        $firstname = $contact->get('firstname');
        $lastname = $contact->get('lastname');
        $email = $contact->get('email');
        return static::generate($firstname, $lastname, $email);
    }

    /**
     * Create username from email.
     *
     * @param $email
     * @param null $randmax
     * @return string
     * @throws coding_exception
     */
    public static function create_from_email_address($email, $randmax = null) {
        $username = '';
        $email = trim($email);
        $email = clean_param($email, PARAM_USERNAME);
        if (is_null($randmax) && !is_number($randmax)) {
            $username = $email;
        } else {
            $username = $email . rand(0, $randmax);
        }
        return core_text::strtolower($username);
    }

    /**
     * Create username from local part of email.
     *
     * @param $email
     * @param null $randmax
     * @return string
     * @throws coding_exception
     */
    public static function create_from_email_address_local_part($email, $randmax = null) {
        $username = '';
        $email = trim($email);
        $email = clean_param($email, PARAM_USERNAME);
        $position = core_text::strpos($email, '@');
        $localpart = core_text::substr($email, 0, $position);
        if (is_null($randmax) && !is_number($randmax)) {
            $username = $localpart;
        } else {
            $username = $localpart . rand(0, $randmax);
        }
        return core_text::strtolower($username);
    }

    /**
     * Create username using firstname and lastname.
     *
     * @param $firstname
     * @param $lastname
     * @param int $length
     * @param null $randmax
     * @return string
     * @throws coding_exception
     */
    public static function create_from_first_and_last_names($firstname, $lastname, $length = 3, $randmax = null) {
        $username = '';
        $firstname = trim($firstname);
        $firstname = clean_param($firstname, PARAM_USERNAME);
        $lastname = trim($lastname);
        $lastname = clean_param($lastname, PARAM_USERNAME);
        if (is_null($randmax) && !is_number($randmax)) {
            $username = core_text::substr($firstname, 0 , $length) .
                core_text::substr($lastname, 0 , $length);
        } else {
            $username = core_text::substr($firstname, 0 , $length) .
                core_text::substr($lastname, 0 , $length) . rand(0, $randmax);
        }
        return core_text::strtolower($username);
    }

}
