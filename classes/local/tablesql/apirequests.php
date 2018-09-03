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

class apirequests extends table_sql {
    const PAGINATION_MAX_LIMIT = 50;
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $columns = array();
        $headers = array();
        $columns[] = 'timelogged';
        $headers[] = get_string('timelogged', 'enrol_arlo');
        $columns[] = 'platform';
        $headers[] = get_string('platform', 'enrol_arlo');
        $columns[] = 'uri';
        $headers[] = get_string('uri', 'enrol_arlo');
        $columns[] = 'status';
        $headers[] = get_string('status');
        $columns[] = 'extra';
        $headers[] = get_string('extra', 'enrol_arlo');
        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->define_baseurl("/enrol/arlo/admin/apirequests.php");
        $this->is_collapsible = false;
        $this->sort_default_column = 'timelogged';
        $this->sort_default_order  = SORT_DESC;
        $this->set_count_sql('SELECT COUNT(*) FROM {enrol_arlo_requestlog}', array());
        $this->set_sql('*', "{enrol_arlo_requestlog}", 'timelogged <> 0');
        $this->no_sorting('platform');
        $this->no_sorting('uri');
        $this->no_sorting('extra');
        $this->pageable(true);
    }
    public function col_timelogged($values) {
        return userdate($values->timelogged);
    }
}