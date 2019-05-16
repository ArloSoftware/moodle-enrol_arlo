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

class email implements username_format_interface {

    /** @var stdClass $data Data used by format, could be persons first name, last name, email etc. */
    protected $data;

    /**
     * @param $object
     */
    public function add_data($object) {
        $this->data = $object;
    }

    public function add_options(array $options) {
    }

    /**
     * @return string
     * @throws coding_exception
     */
    public function get_name() : string {
        return get_string('email', 'enrol_arlo');
    }

    /**
     * @return string
     */
    public function get_shortname() : string {
        return 'email';
    }

    /**
     * @return string
     * @throws coding_exception
     */
    public function get_description() : string {
        return get_string('email_desc', 'enrol_arlo');
    }

    /**
     * @return array
     */
    public function get_required_fields() : array {
        return ['email'];
    }

    /**
     * @return string
     * @throws coding_exception
     */
    public function get_username() {
        if (!isset($this->data->email)) {
            throw new coding_exception("Required field email missing");
        }
        $email = trim($this->data->email);
        $username = clean_param($email, PARAM_USERNAME);
        return core_text::strtolower($username);
    }
}