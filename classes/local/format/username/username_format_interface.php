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
 * Interface that must be used by username formats.
 *
 * @package   enrol_arlo
 * @copyright 2019 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\format\username;

defined('MOODLE_INTERNAL') || die();

/**
 * Interface that must be used by username formats.
 *
 * @package   enrol_arlo
 * @copyright 2019 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface username_format_interface {

    /**
     * Add data to be used by format.
     *
     * @param $object
     * @return mixed
     */
    public function add_data($object);

    /**
     * Add any options that a format may support.
     *
     * @param array $options
     * @return mixed
     */
    public function add_options(array $options);

    /**
     * Get readable name of format.
     *
     * @return string
     */
    public function get_name() : string;

    /**
     * Get alpha shortname of format.
     *
     * @return string
     */
    public function get_shortname() : string;

    /**
     * Get description of what format does.
     *
     * @return string
     */
    public function get_description() : string;

    /**
     * Get fields that are required in data the format will use.
     *
     * @return array
     */
    public function get_required_fields() : array;

    /**
     * Get generated username of format.
     *
     * @return mixed
     */
    public function get_username();
}