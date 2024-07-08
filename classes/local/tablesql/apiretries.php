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
 * API retries error report table
 *
 * @package     enrol_arlo
 * @copyright   2023 Moodle US
 * @author      Nathan Hunt {nathan.hunt@moodle.com}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\tablesql;

defined('MOODLE_INTERNAL') || die();

use table_sql;

class apiretries extends table_sql {
    const PAGINATION_MAX_LIMIT = 50;
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $columns = array();
        $headers = array();
        $columns[] = 'timelogged';
        $headers[] = get_string('timelogged', 'enrol_arlo');
        $columns[] = 'userid';
        $headers[] = get_string('userid', 'enrol_arlo');
        $columns[] = 'participantname';
        $headers[] = get_string('fullname', 'enrol_arlo');
        $columns[] = 'courseid';
        $headers[] = get_string('courseid', 'enrol_arlo');
        $columns[] = 'coursename';
        $headers[] = get_string('coursename', 'enrol_arlo');
        $columns[] = 'action';
        $headers[] = get_string('action');

        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->define_baseurl("/enrol/arlo/admin/apiretries.php");
        $this->is_collapsible = false;
        $this->sort_default_column = 'timelogged';
        $this->sort_default_order  = SORT_DESC;
        $this->set_count_sql('SELECT COUNT(*) FROM {enrol_arlo_retrylog}', array());
        $this->set_sql('*', "{enrol_arlo_retrylog}", 'timelogged <> 0');
        $this->pageable(true);
    }
    public function col_timelogged($values) {
        return userdate($values->timelogged);
    }

    public function col_action($values) {
        global $DB, $OUTPUT;
        $sql = "SELECT r.id, r.redirectcounter 
                  FROM {enrol_arlo_registration} r 
                  JOIN {enrol} e ON e.id = r.enrolid 
                 WHERE r.userid = :userid 
                       AND e.courseid = :courseid";
        $params = ['userid' => $values->userid, 'courseid' => $values->courseid];
        $record = $DB->get_record_sql($sql, $params);
        $retry = get_string('retry_sync', 'enrol_arlo');
        $moodeurl = new \moodle_url('/enrol/arlo/admin/apiretries.php', ['action' => 'resetredirects', 'regid' => $record->id]);
        $maxretries = get_config('enrol_arlo', 'retriesperrecord');
        $renderlink = !empty($record->redirectcounter) && ($record->redirectcounter >= $maxretries);
        $output = $renderlink ? '<a href="' . $moodeurl . '">' . $retry . '</a>' : '';
        return $output;
    }
}