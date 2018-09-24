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

use table_sql;

class communications extends table_sql {
    const PAGINATION_MAX_LIMIT = 50;
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $columns = array();
        $headers = array();
        $columns[] = 'userid';
        $headers[] = get_string('user');
        $columns[] = 'type';
        $headers[] = get_string('type', 'enrol_arlo');
        $columns[] = 'status';
        $headers[] = get_string('status');
        $columns[] = 'timemodified';
        $headers[] = get_string('modified');

        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->useridfield = 'userid';
        $this->define_baseurl("/enrol/arlo/admin/communications.php");
        $this->is_collapsible = false;
        $this->sort_default_column = 'timemodified';
        $this->sort_default_order  = SORT_DESC;
        $this->set_count_sql('SELECT COUNT(*) FROM {enrol_arlo_emailqueue}', array());
        $fields = 'eq.id,';
        $fields .= get_all_user_name_fields(true, 'u');
        $fields .= ',eq.userid,eq.type,eq.status,eq.timemodified';
        $from = "{enrol_arlo_emailqueue} eq JOIN {user} u ON u.id = eq.userid";
        $this->set_sql($fields, $from, 'eq.id <> 0');
        $this->no_sorting('enrolid');
        $this->no_sorting('userid');
        $this->pageable(true);

    }
    public function col_timemodified($values) {
        return userdate($values->timemodified);
    }
    public function col_status($values) {
        switch ($values->status) {
            case \enrol_arlo\manager::EMAIL_STATUS_QUEUED :
                return get_string('queued', 'enrol_arlo');
            case \enrol_arlo\manager::EMAIL_STATUS_DELIVERED:
                return get_string('delivered', 'enrol_arlo');
            case \enrol_arlo\manager::EMAIL_STATUS_FAILED:
                return get_string('failed', 'enrol_arlo');
            default:
                return get_string('unknown', 'enrol_arlo');
        }
    }
    public function col_type($values) {
        switch ($values->type) {
            case \enrol_arlo\manager::EMAIL_TYPE_NEW_ACCOUNT:
                return get_string('newaccountdetails', 'enrol_arlo');
            case \enrol_arlo\manager::EMAIL_TYPE_COURSE_WELCOME:
                return get_string('coursewelcome', 'enrol_arlo');
            case \enrol_arlo\manager::EMAIL_TYPE_NOTIFY_EXPIRY:
                return get_string('notifyexpiry', 'enrol_arlo');
            default:
                return get_string('unknown', 'enrol_arlo');
        }
    }
    public function col_userid($values) {
        return fullname($values);
    }
}