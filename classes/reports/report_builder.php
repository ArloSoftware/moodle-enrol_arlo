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

namespace enrol_arlo\reports;
use enrol_arlo\user;

/**
 * Test table class to be put in test_table.php of root of Moodle installation.
 *  for defining some custom column names and proccessing
 * Username and Password feilds using custom and other column methods.
 */
class report_builder extends \table_sql {

    /**
     * Constructor
     * @param int $uniqueid all tables have to have a unique id, this is used
     *      as a key when storing table properties like sort order in the session.
     */
    function __construct($uniqueid, $columns) {
        parent::__construct($uniqueid);
        // Define the list of columns to show.
        $this->define_columns($columns);

        // Define the titles of columns to show in header.
        $this->define_headers($columns);
    }

    /**
     * This function is called for each data row to allow processing of
     * columns which do not have a *_cols function.
     * @return string return processed value. Return NULL if no change has
     *     been made.
     */
    function other_cols($colname, $value) {
        // For security reasons we don't want to show the password hash.
        if ($colname == 'password') {
            return "****";
        }
    }

    function col_timelogged($values) {
        // If the data is being downloaded than we don't want to show HTML.
        if ($this->is_downloading()) {
            return $values->timelogged;
        } else {
            $dt = new \DateTime("@$values->timelogged");
            return $dt->format('Y-m-d H:i:s');
        }
    }

    function col_userid($values) {
        global $DB;
        // If the data is being downloaded than we don't want to show HTML.
        if ($this->is_downloading()) {
            return $values->userid;
        } else {
            $user = $DB->get_record('user', array('id' => $values->userid), 'firstname, lastname');
            return '<a href="/user/profile.php?id='.$values->userid.'">'. $user->firstname . ' '. $user->lastname .'</a>';
        }
    }
}
