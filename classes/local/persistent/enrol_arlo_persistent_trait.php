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
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\persistent;

defined('MOODLE_INTERNAL') || die();

trait enrol_arlo_persistent_trait {
    /**
     * Load based on a defined record property field.
     *
     * @param $name
     * @param $value
     * @return $this
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function from_record_property($name, $value) {
        global $DB;
        if (!static::has_property($name)) {
            throw new coding_exception("Property {$name} does not exist.");
        }
        $record = $DB->get_record(static::TABLE, [$name => $value]);
        if (!$record) {
            $this->set($name, $value);
        } else {
            $this->from_record($record);
        }
        return $this;
    }

    /**
     * Load a record based in key/value property array.
     *
     * @param array $properties
     * @return $this
     * @throws \coding_exception
     */
    public function from_record_properties(array $properties) {
        global $DB;
        foreach ($properties as $name => $value) {
            if (!static::has_property($name)) {
                throw new coding_exception("Property {$name} does not exist.");
            }
        }
        $record = $DB->get_record(static::TABLE, $properties);
        if (!$record) {
            foreach ($properties as $name => $value) {
                $this->set($name, $value);
            }
        } else {
            $this->from_record($record);
        }
        return $this;
    }

}
