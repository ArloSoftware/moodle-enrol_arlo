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
 * Unsuccessful enrolments table SQL class.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\tablesql;

defined('MOODLE_INTERNAL') || die();

use html_writer;
use moodle_url;
use table_sql;
use enrol_arlo\local\persistent\contact_persistent;

/**
 * Unsuccessful enrolments table SQL class.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class unsuccessful_enrolments_table_sql extends table_sql {

    const PAGINATION_MAX_LIMIT = 20;

    public function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $columns = [
            'arlocoursecode',
            'course',
            'arlocontact',
            'associateduser',
            'timemodified',
            'actions'
        ];
        $headers = [
            get_string('arlocoursecode', 'enrol_arlo'),
            get_string('course'),
            get_string('arlocontact', 'enrol_arlo'),
            get_string('associateduser', 'enrol_arlo'),
            get_string('timemodified', 'enrol_arlo'),
            ''
        ];
        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->is_collapsible = false;
        $this->define_baseurl("/enrol/arlo/admin/unsuccessfulenrolments.php");
        $this->sortable(false, 'timemodified', SORT_ASC);
        $this->no_sorting('arlocoursecode');
        $this->no_sorting('course');
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
                'e.name as arlocoursecode',
                'eac.id AS contactid',
                'eac.firstname',
                'eac.lastname',
                'eac.email',
                'eac.codeprimary',
                'c.id AS courseid',
                'c.fullname AS coursefullname',
                'ear.timemodified'
            ];
            $select = implode(',', $fields);
        }
        $sql = "SELECT $select
                  FROM {enrol_arlo_contact} eac
                  JOIN {enrol_arlo_registration} ear
                    ON ear.sourcecontactguid = eac.sourceguid
                  JOIN {enrol} e ON e.id = ear.enrolid
                  JOIN {course} c ON c.id = e.courseid
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
     * Arlo contact details.
     *
     * @param $values
     * @return string
     */
    public function col_arlocontact($values) {
        $output = '';
        $output .= $values->firstname . html_writer::empty_tag('br');
        $output .= $values->lastname . html_writer::empty_tag('br');
        $output .= $values->email . html_writer::empty_tag('br');
        $output .= $values->codeprimary . html_writer::empty_tag('br');
        return $output;
    }

    /**
     * Arlo course code.
     *
     * @param $values
     * @return mixed
     * @throws \moodle_exception
     */
    public function col_arlocoursecode($values) {
        global $OUTPUT;
        $url = new moodle_url('/enrol/instances.php');
        $url->param('id', $values->courseid);
        return $OUTPUT->action_link($url, $values->arlocoursecode, null, null);
    }

    /**
     * Associated user with Arlo contact.
     *
     * @param $values
     * @return string
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function col_associateduser($values) {
        $output = '';
        $contact = new contact_persistent($values->contactid);
        if ($contact) {
            $user = $contact->get_associated_user();
            if ($user) {
                $output .= $user->get('firstname') . html_writer::empty_tag('br');
                $output .= $user->get('lastname') . html_writer::empty_tag('br');
                $output .= $user->get('email') . html_writer::empty_tag('br');
                $output .= $user->get('idnumber') . html_writer::empty_tag('br');
            }
        }
        return $output;
    }

    /**
     * Full course name.
     *
     * @param $values
     * @return mixed
     */
    public function col_course($values) {
        return $values->coursefullname;
    }

    /**
     * Actions that can be performed on line item.
     *
     * @param $values
     * @return string
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function col_actions($values) {
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