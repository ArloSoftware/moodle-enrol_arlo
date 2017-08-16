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

namespace enrol_arlo\report;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/tablelib.php");

class communications extends \table_sql {
    public $perpage = 20;
    public function __construct($uniqueid) {
        parent::__construct($uniqueid);
        $columns = array();
        $headers = array();
        $columns[] = 'enrolid';
        $columns[] = 'userid';
        $columns[] = 'type';
        $columns[] = 'status';
        $columns[] = 'modified';

        $this->define_columns($columns);
        $this->define_headers($columns);
        $this->useridfield = 'userid';
        $this->define_baseurl("/enrol/arlo/admin/communications.php");
        $this->is_collapsible = false;
        $this->sort_default_column = 'modified';
        $this->sort_default_order  = SORT_DESC;
        $this->set_count_sql('SELECT COUNT(*) FROM {enrol_arlo_emailqueue}', array());
        $this->set_sql('*', "{enrol_arlo_emailqueue}", true);
        $this->pageable(true);

    }
    public function col_modified($values) {
        return userdate($values->modified);
    }
}