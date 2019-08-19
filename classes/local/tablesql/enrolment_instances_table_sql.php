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

namespace enrol_arlo\local\tablesql;

defined('MOODLE_INTERNAL') || die();

use html_writer;
use moodle_url;
use table_sql;

/**
 * SQL table report for enrolment instances.
 *
 * @package   enrol_arlo
 * @copyright 2019 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrolment_instances_table_sql extends table_sql {

    const PAGINATION_MAX_LIMIT = 10;

    public function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $columns = [];
        $headers = [];
        $columns[] = 'name';
        $headers[] = get_string('name');
        $columns[] = 'coursefullname';
        $headers[] = get_string('course');
        $columns[] = 'timemodified';
        $headers[] = get_string('modified');

        $this->define_columns($columns);
        $this->define_headers($headers);

        $this->define_baseurl("/enrol/arlo/admin/enrolmentinstances.php");
        $this->is_collapsible = false;

        $countsql = "SELECT COUNT(1)
                       FROM {enrol} e
                       JOIN {course} c 
                         ON c.id = e.courseid AND e.enrol = 'arlo'";

        $this->set_count_sql($countsql, []);
        $fields = 'e.id AS enrolid, c.id AS courseid, e.name, c.fullname AS coursefullname, e.timemodified';
        $from = "{enrol} e
            JOIN {course} c 
              ON c.id = e.courseid AND e.enrol = 'arlo'";
        $this->set_sql($fields, $from, 'e.timemodified <> 0');

        $this->sortable(false);
        $this->pageable(true);

    }

    public function col_name($values) {
        $url = new moodle_url('/enrol/instances.php', ['id' => $values->courseid]);
        return html_writer::link($url, $values->name);
    }

    public function col_timemodified($values) {
        return userdate($values->timemodified);
    }

    public function get_sql_sort() {
        return static::construct_order_by(['c.fullname' => SORT_ASC, 'e.name' => SORT_ASC]);
    }

    public function get_total() {
        global $DB;
        return $DB->count_records_sql($this->countsql, $this->countparams);
    }

}
