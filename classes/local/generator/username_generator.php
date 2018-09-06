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
    public static function create($firstname, $lastname, $email) {
        global $DB;

        $firstname = trim($firstname);
        $firstname = clean_param($firstname, PARAM_USERNAME);
        if (empty($firstname)) {
            throw new coding_exception('Parameter firstname is invalid');
        }
        $lastname = trim($lastname);
        $lastname = clean_param($lastname, PARAM_USERNAME);
        if (empty($lastname)) {
            throw new coding_exception('Parameter lastname is invalid');
        }
        $email = trim($email);
        $email = clean_param($email, PARAM_USERNAME);
        if (empty($email)) {
            throw new coding_exception('Parameter email is invalid');
        }
        $position = core_text::strpos($email, '@');
        $emailusername = core_text::substr($email, 0, $position);
        $tries = 0;
        $exists = true;
        while ($exists) {
            ++$tries;
            switch($tries) {
                case 1;
                    $username = core_text::substr($firstname, 0 , 3) .
                        core_text::substr($lastname, 0 , 3) . rand(0, 3);
                    break;
                case 2:
                    $username = $emailusername;
                    break;
                case 3:
                    $username = $emailusername . rand(0, 3);
                    break;
                case 4:
                    $username = $email;
                    break;
                case 5:
                    $username = $email . rand(0, 3);
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
        return static::create($firstname, $lastname, $email);
    }

}
