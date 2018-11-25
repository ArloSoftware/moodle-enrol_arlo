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

use enrol_arlo\local\job\memberships_job;
use enrol_arlo\local\persistent\contact_persistent;
use enrol_arlo\local\persistent\contact_merge_request_persistent;
use html_writer;
use moodle_url;
use table_sql;

/**
 * Unsuccessful enrolments table SQL class.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class unsuccessful_enrolments_table_sql extends table_sql {

    /** @var int PAGINATION_MAX_LIMIT */
    const PAGINATION_MAX_LIMIT = 10;

    /** @var array $contacts Contacts cache. */
    protected static $contacts = [];

    /**
     * Constructor.
     *
     * @param $uniqueid
     * @throws \coding_exception
     */
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $columns = [
            'timemodified',
            'sourcestatus',
            'arlocoursecode',
            'course',
            'arlocontact',
            'associateduser',
            'type',
            'actions'
        ];
        $headers = [
            get_string('date'),
            get_string('status'),
            get_string('arlocoursecode', 'enrol_arlo'),
            get_string('course'),
            get_string('arlocontact', 'enrol_arlo'),
            get_string('associateduser', 'enrol_arlo'),
            get_string('report'),
            ''
        ];
        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->is_collapsible = false;
        $this->define_baseurl("/enrol/arlo/admin/unsuccessfulenrolments.php");
        $this->sortable(true, 'timemodified', SORT_ASC);
        $this->no_sorting('arlocoursecode');
        $this->no_sorting('course');
        $this->no_sorting('arlocontact');
        $this->no_sorting('associateduser');
        $this->no_sorting('type');
        $this->no_sorting('actions');
    }

    /**
     * Accessor contacts static cache.
     *
     * @param $id
     * @return mixed
     */
    public function get_contact($id) {
        if (!isset(static::$contacts[$id])) {
            static::$contacts[$id] = new contact_persistent($id);
        }
        return static::$contacts[$id];
    }

    /**
     * Helper method to make SQL and PARAMS.
     *
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
                'c.id AS courseid',
                'c.fullname AS coursefullname',
                'ear.sourcestatus',
                'eac.id AS contactid',
                'eac.firstname',
                'eac.lastname',
                'eac.email',
                'eac.codeprimary',
                'eac.usercreationfailure',
                'eac.userassociationfailure',
                'eacmr.mergefailed',
                'ear.timemodified'
            ];
            $select = implode(',', $fields);
        }
        $sql = "SELECT $select
                  FROM {enrol_arlo_registration} ear
                  JOIN {enrol_arlo_contact} eac
                    ON eac.sourceguid = ear.sourcecontactguid
             LEFT JOIN {enrol_arlo_contactmerge} eacmr
                    ON eacmr.destinationcontactguid = eac.sourceguid AND eacmr.mergefailed = :mergefailed
                  JOIN {enrol} e ON e.id = ear.enrolid
                  JOIN {course} c ON c.id = e.courseid
                 WHERE ear.enrolmentfailure = :enrolmentfailure";

        $params = [
            'enrolmentfailure' => 1,
            'mergefailed' => 1
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
     */
    public function col_associateduser($values) {
        $output = '';
        $contact = $this->get_contact($values->contactid);
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
        $actions['reattemptenrolment'] = '';
        if (!empty($values->userassociationfailure)) {
            $contact = $this->get_contact($values->contactid);
            if ($contact) {
                $duplicatescount = memberships_job::match_user_from_contact($contact);
                if (is_numeric($duplicatescount) && $duplicatescount <= 1) {
                    $url = new moodle_url('/enrol/arlo/admin/reattemptenrolment.php');
                    $url->param('id', $values->id);
                    $text = get_string('reattemptenrolment', 'enrol_arlo');
                    $actions['reattemptenrolment'] .= $OUTPUT->action_link(
                        $url, $text, null, ['class' => 'btn btn-outline-primary btn-sm']
                    );
                }
            }
        }
        if (!empty($values->mergefailed)) {
            $contact = $this->get_contact($values->contactid);
            if ($contact) {
                $contactmergerequests = contact_merge_request_persistent::get_records(
                    ['destinationcontactid' => $contact->get('sourceid'), 'mergefailed' => 1]
                );
                $contactmergerequest = reset($contactmergerequests);
                $sourcecontact = $contactmergerequest->get_source_contact();
                $sourceenrolments = $sourcecontact->get_associated_user()->has_course_enrolments();
                $destinationenrolments = $contact->get_associated_user()->has_course_enrolments();
                if (!($sourceenrolments && $destinationenrolments)) {
                    $url = new moodle_url('/enrol/arlo/admin/reattemptenrolment.php');
                    $url->param('id', $values->id);
                    $text = get_string('reattemptenrolment', 'enrol_arlo');
                    $actions['reattemptenrolment'] .= $OUTPUT->action_link(
                        $url, $text, null, ['class' => 'btn btn-outline-primary btn-sm']
                    );
                }
            }
        }
        return implode('', $actions);
    }

    /**
     * Registration status.
     *
     * @param $values
     * @return mixed
     */
    public function col_sourcestatus($values) {
        return $values->sourcestatus;
    }

    /**
     * Shorten date.
     *
     * @param $values
     * @return string
     */
    public function col_timemodified($values) {
        $content = userdate($values->timemodified, '%d %B %Y');
        $title = userdate($values->timemodified);
        return html_writer::span($content, '', ['title' => $title]);
    }

    /**
     * Build report access URL's based on type of failure.
     *
     * @param $values
     * @return string
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public function col_type($values) {
        global $OUTPUT;
        $output = '';
        if (!empty($values->userassociationfailure)) {
            $url = new moodle_url('/enrol/arlo/admin/userassociationfailure.php');
            $url->param('id', $values->id);
            $text = get_string('userassociationfailurereport', 'enrol_arlo');
            $output .= $OUTPUT->action_link($url, $text, null, null);
        }
        if (!empty($values->mergefailed)) {
            $url = new moodle_url('/enrol/arlo/admin/contactmergefailure.php');
            $url->param('id', $values->id);
            $text = get_string('contactmergefailurereport', 'enrol_arlo');
            $output .= $OUTPUT->action_link($url, $text, null, null);
        }
        return $output;
    }
}
