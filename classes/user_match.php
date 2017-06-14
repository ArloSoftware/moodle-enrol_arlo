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
 * @copyright 2017 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo;

class user_match {
    /**
     * @var BY_CODE_PRIMARY match by idnumber
     */
     const BY_CODE_PRIMARY = 1;
    /**
     * @var BY_USER_DETAILS match by firstname, lastname and email
     */
    const BY_USER_DETAILS = 2;
    /**
     * @var int AUTO match using BY_USER_DETAILS then BY_CODE_PRIMARY
     * */
    const AUTO = 3;
    /**
     * @var int DEFAULT default user match method to use.
     */
    const BY_DEFAULT = 2;
}