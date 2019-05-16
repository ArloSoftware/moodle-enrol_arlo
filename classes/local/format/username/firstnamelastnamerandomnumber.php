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
 * @package   enrol_arlo
 * @copyright 2019 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\format\username;

use coding_exception;
use core_text;

defined('MOODLE_INTERNAL') || die();

class firstnamelastnamerandomnumber implements username_format_interface {

    /**
     * @var int MAXRANDMAX Maximum value to use with rand function.
     */
    const MAXRANDMAX = 999;

    /** @var stdClass $data Data used by format, could be persons first name, last name, email etc. */
    protected $data;

    /** @var int $length  */
    protected $length = 3;

    /** @var int $randmax */
    protected $randmax = 9;

    /**
     * @param $object
     */
    public function add_data($object) {
        $this->data = $object;
    }

    /**
     * @param array $options
     */
    public function add_options(array $options) {
        if (isset($options['randmax'])) {
            $randmax = $options['randmax'];
            if ($randmax > static::MAXRANDMAX) {
                $randmax = static::MAXRANDMAX;
            }
            $this->randmax = $randmax;
        }
    }

    /**
     * @return string
     * @throws coding_exception
     */
    public function get_name() : string {
        return get_string('firstnamelastnamerandomnumber', 'enrol_arlo');
    }

    /**
     * @return string
     */
    public function get_shortname() : string {
        return 'firstnamelastnamerandomnumber';
    }

    /**
     * @return string
     * @throws coding_exception
     */
    public function get_description() : string {
        return get_string('firstnamelastnamerandomnumber_desc', 'enrol_arlo');
    }

    /**
     * @return array
     */
    public function get_required_fields() : array {
        return ['firstname', 'lastname'];
    }

    /**
     * @return string
     * @throws coding_exception
     */
    public function get_username() {
        if (!isset($this->data->firstname)) {
            throw new coding_exception("Required field firstname missing");
        }
        if (!isset($this->data->lastname)) {
            throw new coding_exception("Required field lastname missing");
        }
        $firstname = trim($this->data->firstname);
        $firstname = clean_param($firstname, PARAM_USERNAME);
        $lastname = trim($this->data->lastname);
        $lastname = clean_param($lastname, PARAM_USERNAME);
        $username = core_text::substr($firstname, 0 , $this->length) .
            core_text::substr($lastname, 0 , $this->length) .
            rand(0, $this->randmax);
        return core_text::strtolower($username);
    }
}