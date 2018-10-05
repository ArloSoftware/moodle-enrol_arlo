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
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\tablesql;

defined('MOODLE_INTERNAL') || die();

use moodle_url;
use table_sql;

class unsuccessful_enrolments_table_sql extends table_sql {

    const PAGINATION_MAX_LIMIT = 20;

    public function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $columns = [
            'enrolmentinstancename',
            'firstname',
            'lastname',
            'email',
            'codeprimary',
            'timemodified',
            'report'
        ];
        $headers = [
            get_string('enrolment', 'enrol_arlo'),
            get_string('firstname'),
            get_string('lastname'),
            get_string('email'),
            get_string('codeprimary', 'enrol_arlo'),
            get_string('timemodified', 'enrol_arlo'),
            ''
        ];
        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->is_collapsible = false;
        $this->define_baseurl("/enrol/arlo/admin/unsuccessfulenrolments.php");
        $this->sortable(true, 'timemodified', SORT_ASC);
    }

    /**
     * @param bool $count
     * @return array
     */
    public function get_sql_and_params($count = false) {
        if ($count) {
            $select = "COUNT(1)";
        } else {
            $fields = [
                'ear.id',
                'ear.timemodified',
                'e.name as enrolmentinstancename',
                'eac.firstname',
                'eac.lastname',
                'eac.email',
                'eac.codeprimary'
            ];
            $select = implode(',', $fields);
        }
        $sql = "SELECT $select
                  FROM {enrol_arlo_contact} eac
                  JOIN {enrol_arlo_registration} ear
                    ON ear.sourcecontactguid = eac.sourceguid
                  JOIN {enrol} e ON e.id = ear.enrolid
                 WHERE ear.enrolmentfailure = :enrolmentfailure";
        $params = [
            'enrolmentfailure' => 1
        ];
        // Add order by if needed.
        if (!$count && $sqlsort = $this->get_sql_sort()) {
            $sql .= " ORDER BY " . $sqlsort;
        }
        return [$sql, $params];
    }


    /**
     * Query the DB.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar.
     * @throws \dml_exception
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;

        list($countsql, $countparams) = $this->get_sql_and_params(true);
        list($sql, $params) = $this->get_sql_and_params();
        $total = $DB->count_records_sql($countsql, $countparams);
        $this->pagesize($pagesize, $total);
        $this->rawdata = $DB->get_records_sql($sql, $params, $this->get_page_start(), $this->get_page_size());

        // Set initial bars.
        if ($useinitialsbar) {
            $this->initialbars($total > $pagesize);
        }
    }

    /**
     * @param $values
     * @return string
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function col_report($values) {
        global $OUTPUT;
        $url = new moodle_url('/enrol/arlo/admin/unsuccessfulenrolment.php');
        $url->param('id', $values->id);
        $text = get_string('viewreport', 'enrol_arlo');
        $actions[] = $OUTPUT->action_link($url, $text, null, null);
        return implode('', $actions);
    }

    /**
     * @param $values
     * @return string
     */
    public function col_timemodified($values) {
        return userdate($values->timemodified);
    }
}