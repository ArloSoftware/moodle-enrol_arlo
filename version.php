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
 * Version information.
 *
 * @author    Troy Williams
 * @author    Corey Davis
 * @package   enrol_arlo
 * @copyright 2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin            = new stdClass();
$plugin->version   = 2020073112;
$plugin->requires  = 2017111300;        // See http://docs.moodle.org/dev/Moodle_Version
$plugin->component = 'enrol_arlo';      // Full name of the plugin (used for diagnostics).
$plugin->release   = '3.9.2';           // Human-friendly version name.
$plugin->maturity  = MATURITY_STABLE;   // This version's maturity level.
$plugin->dependencies = [];
