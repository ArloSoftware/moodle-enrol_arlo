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
 * DML Utilities.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2017 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\utility;

class dml {
    /**
     * Alias array of fields.
     *
     * e.g. c.id AS course_id
     *
     * @param array $fields
     * @param $tableprefix
     * @param $aliasprefix
     * @return string
     */
    public static function alias(array $fields, $tableprefix, $aliasprefix) {
        $aliasedfields = array();
        foreach ($fields as $field) {
            $aliasedfields[$field] = "$tableprefix.$field AS $aliasprefix$field";
        }
        return implode(',', $aliasedfields);
    }
    /**
     * Takes a record and builds hierarchical structure based on
     * occurrences of delimiter in key.
     *
     * message_author_email
     * becomes:
     * array['message']['author']['email'] = 'nobody@nowhere'
     *
     * @param \stdClass $record
     * @param string $delimiter
     * @return array
     */
    public static function unalias(\stdClass $record, $delimiter = "_") {
        $collect = array();
        foreach (get_object_vars($record) as $key => $value) {
            $tip = &$collect;
            $branches = explode($delimiter, $key);
            while ($branches) {
                $field = array_shift($branches);
                if (count($branches) === 0) {
                    break;
                }
                if (isset($tip[$field])) {
                    $tip = &$tip[$field];
                } else {
                    $tip[$field] = null;
                    $tip = &$tip[$field];
                }
            }
            $tip[$field] = $value;
        }
        return $collect;
    }
}
