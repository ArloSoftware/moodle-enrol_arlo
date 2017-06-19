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

class plugin_config {
    private static $plugin = 'enrol_arlo';

    public static function get($name) {
        static $config;
        if (is_null($config)){
            $config = get_config(static::$plugin);
        }
        if (isset($config->{$name})) {
            return $config->{$name};
        }
        return static::get_default($name);
    }

    public static function get_default($name) {
        static $studentroleid;
        switch ($name) {
            case 'platformname':
                return '';
            case 'aspiusername':
                return '';
            case 'apipassword':
                return '';
            case 'apistatus':
                return 0;
            case 'authplugin':
                return 'manual';
            case 'matchuseraccountsby':
                return user_match::BY_DEFAULT;
            case 'roleid':
                if (is_null($roleid)) {
                    $student = get_archetype_roles('student');
                    $student = reset($student);
                    $studentroleid = $student->id;
                }
                return $studentroleid;
            case 'unenrolaction':
                return ENROL_EXT_REMOVED_UNENROL;
            case 'expiredaction':
                return ENROL_EXT_REMOVED_SUSPEND;
            case 'pushonlineactivityresults':
                return 1;
            case 'pusheventresults':
                return 0;
            case 'alertsiteadmins':
                return 1;
            default:
                return null;
        }
    }

    public static function set($name, $value) {
        static $config;
        if (is_null($config)){
            $config = get_config(static::$plugin);
        }
        if (is_array($value) || is_object($value)) {
            throw new \Exception('TODO need to handle these types');
        }

        //if (!isset($config->{$name}) || $config->{$name} != $value) {
            //mtrace('save ' . $name .);
            set_config($name, $value, static::$plugin);
        //}
    }
}