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
 * @package   enrol_arlo
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\generator;

defined('MOODLE_INTERNAL') || die();

use coding_exception;
use core_text;
use enrol_arlo\local\persistent\contact_persistent;
use stdClass;
use enrol_arlo\local\config\arlo_plugin_config;

/**
 * Moodle username generator.
 *
 * @package   enrol_arlo
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class username_generator {

    /**
     * @var int Maximum value to use with rand function.
     */
    const MAXRANDMAX = 999;

    /**
     * Defines generator patterns.
     *
     * Each pattern requires method, userfieldsrequired, name, and description.
     *
     * Default order are items top to bottom order in array.
     *
     * @return array
     * @throws coding_exception
     */
    public static function get_patterns() {
        return [
            'firstnamelastnamerandomnumber' => [
                'method' => 'from_firstname_and_lastname_and_randomnumber',
                'userfieldsrequired' => ['firstname', 'lastname'],
                'name' => get_string('firstnamelastnamerandomnumber', 'enrol_arlo'),
                'description' => get_string('firstnamelastnamerandomnumber_desc', 'enrol_arlo')
            ],
            'emaillocalpart' => [
                'method' => 'from_email_local_part',
                'userfieldsrequired' => ['email'],
                'name' => get_string('emaillocalpart', 'enrol_arlo'),
                'description' => get_string('emaillocalpart_desc', 'enrol_arlo')
            ],
            'emaillocalpartrandomnumber' => [
                'method' => 'from_email_local_part_and_randomnumber',
                'userfieldsrequired' => ['email'],
                'name' => get_string('emaillocalpartrandomnumber', 'enrol_arlo'),
                'description' => get_string('emaillocalpartrandomnumber_desc', 'enrol_arlo')
            ],
            'email' => [
                'method' => 'from_email',
                'userfieldsrequired' => ['email'],
                'name' => get_string('email', 'enrol_arlo'),
                'description' => get_string('email_desc', 'enrol_arlo')
            ],
            'emailrandomnumber' => [
                'name' => get_string('email', 'enrol_arlo'),
                'method' => 'from_email_and_randomnumber',
                'userfieldsrequired' => ['email'],
                'description' => get_string('email_desc', 'enrol_arlo')
            ]
        ];
    }

    /**
     * Get default generator order, return as array or comma separated string.
     *
     * @param bool $returncommaseparated
     * @return array|string
     * @throws coding_exception
     */
    final public static function get_default_order($returncommaseparated = false) {
        $patterns = array_keys(static::get_patterns());
        if ($returncommaseparated) {
            return implode(',', $patterns);
        }
        return $patterns;
    }

    /**
     * @param $pattern
     * @return mixed
     * @throws coding_exception
     */
    final public static function get_pattern($pattern) {
        if (!static::has_pattern($pattern)) {
            throw new coding_exception("Pattern $pattern not found");
        }
        $patterns = static::get_patterns();
        return $patterns[$pattern];
    }

    /**
     * @param $pattern
     * @return bool
     * @throws coding_exception
     */
    final public static function has_pattern($pattern) {
        $patterns = static::get_patterns();
        return isset($patterns[$pattern]);
    }

    /**
     * @param stdClass $userinformation
     * @return bool|string
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function generate(stdClass $userinformation) {
        global $DB;
        $pluginconfig = new arlo_plugin_config();
        $usernamepatternorder = explode(',', $pluginconfig->get('usernamepatternorder'));
        if (count($usernamepatternorder) != count(static::get_patterns())) {
            throw new coding_exception("Incorrect number of username patterns in order");
        }
        while ($usernamepatternorder) {
            $name = array_shift($usernamepatternorder);
            $pattern = static::get_pattern($name);
            $patternmethod = isset($pattern['method']) ? $pattern['method'] : null;
            $userfieldsrequired = isset($pattern['userfieldsrequired']) ? $pattern['userfieldsrequired'] : null;
            if (is_null($patternmethod) || is_null($userfieldsrequired)) {
                throw new coding_exception("Incoorect pattern definition for $name");
            }
            foreach ($userfieldsrequired as $userfieldrequired) {
                if (!isset($userinformation->{$userfieldrequired})) {
                    throw new coding_exception("Missing required field $userfieldrequired");
                }
                $username = static::$patternmethod($userinformation);
                $username = core_text::strtolower($username);
                $exists = $DB->get_record('user', ['username' => $username]);
                if (!$exists) {
                    return $username;
                }
            }
        }
        return false;
    }

    /**
     * Convenience method.
     *
     * @param contact_persistent $contact
     * @return bool|string
     * @throws \dml_exception
     * @throws coding_exception
     */
    final public static function from_contact_persistent(contact_persistent $contact) {
        $userinformation            = new stdClass();
        $userinformation->firstname = $contact->get('firstname');
        $userinformation->lastname  = $contact->get('lastname');
        $userinformation->email     = $contact->get('email');
        return static::generate($userinformation);
    }

    /**
     * Use first 3 letters of firstname + first 3 letters of lastname + random number.
     *
     * @param stdClass $userinformation
     * @param int $length
     * @param int $randmax
     * @return string
     * @throws coding_exception
     */
    public static function from_firstname_and_lastname_and_randomnumber(stdClass $userinformation, $length = 3, $randmax = 9) {
        if (!isset($userinformation->firstname)) {
            throw new coding_exception("Require field firstname missing");
        }
        if (!isset($userinformation->lastname)) {
            throw new coding_exception("Require field lastname missing");
        }
        if ($randmax > static::MAXRANDMAX) {
            $randmax = static::MAXRANDMAX;
        }
        $firstname = trim($userinformation->firstname);
        $firstname = clean_param($firstname, PARAM_USERNAME);
        $lastname = trim($userinformation->lastname);
        $lastname = clean_param($lastname, PARAM_USERNAME);
        $username = core_text::substr($firstname, 0 , $length) .
            core_text::substr($lastname, 0 , $length) . rand(0, $randmax);
        return core_text::strtolower($username);
    }

    /**
     * Use email username address before @ symbol.
     *
     * @param $userinformation
     * @return string
     * @throws coding_exception
     */
    public static function from_email_local_part($userinformation) {
        if (!isset($userinformation->email)) {
            throw new coding_exception("Require field email missing");
        }
        $email = trim($userinformation->email);
        $email = clean_param($email, PARAM_USERNAME);
        $position = core_text::strpos($email, '@');
        $username = core_text::substr($email, 0, $position);
        return core_text::strtolower($username);
    }

    /**
     * Use email username address before @ symbol + random number.
     *
     * @param $userinformation
     * @param int $randmax
     * @return string
     * @throws coding_exception
     */
    public static function from_email_local_part_and_randomnumber($userinformation, $randmax = 9) {
        if (!isset($userinformation->email)) {
            throw new coding_exception("Require field email missing");
        }
        if ($randmax > static::MAXRANDMAX) {
            $randmax = static::MAXRANDMAX;
        }
        $email = trim($userinformation->email);
        $email = clean_param($email, PARAM_USERNAME);
        $position = core_text::strpos($email, '@');
        $localpart = core_text::substr($email, 0, $position);
        $username = $localpart . rand(0, $randmax);
        return core_text::strtolower($username);
    }

    /**
     * Use full email address.
     *
     * @param $userinformation
     * @return string
     * @throws coding_exception
     */
    public static function from_email($userinformation) {
        if (!isset($userinformation->email)) {
            throw new coding_exception("Require field email missing");
        }
        $email = trim($userinformation->email);
        $username = clean_param($email, PARAM_USERNAME);
        return core_text::strtolower($username);
    }

    /**
     * Use full email address + random number.
     *
     * @param $userinformation
     * @param int $randmax
     * @return string
     * @throws coding_exception
     */
    public static function from_email_and_randomnumber($userinformation, $randmax = 9) {
        if (!isset($userinformation->email)) {
            throw new coding_exception("Require field email missing");
        }
        if ($randmax > static::MAXRANDMAX) {
            $randmax = static::MAXRANDMAX;
        }
        $email = trim($userinformation->email);
        $email = clean_param($email, PARAM_USERNAME);
        $username = $email . rand(0, $randmax);
        return core_text::strtolower($username);
    }
}
