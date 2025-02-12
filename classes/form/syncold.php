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

namespace enrol_arlo\form;


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
use core_form\dynamic_form;
use enrol_arlo\task\enrolments_adhoc;
use moodle_url;
use stdClass;

class syncold extends dynamic_form {

     /**
     * Get context.
     *
     * @return \context
     * @throws \dml_exception
     */
    protected function get_context_for_dynamic_submission(): \context {
        return \context_system::instance();
    }

    /**
     * Check access.
     *
     * @return void
     * @throws \dml_exception
     * @throws \required_capability_exception
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('moodle/course:create', \context_system::instance());
    }

    /**
     * Process submission.
     *
     * @return array
     */
    public function process_dynamic_submission() {
        $data = $this->get_data();
        $task = new enrolments_adhoc();
        $task->set_custom_data($data->startdate);
        \core\task\manager::queue_adhoc_task($task);
        return ['message' => 'taskqueued'];
    }

     /**
     * Set form data.
     *
     * @return void
     * @throws Exception No record in persistent table
     */
    public function set_data_for_dynamic_submission(): void {
        $this->set_data(new stdClass());
    }

    /**
     * Get page url.
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/enrol/arlo/admin/apiretries.php');
    }

    public function definition() {
        global $DB, $COURSE;
        $form = $this->_form;
        $form->addElement('date_selector', 'startdate', get_string('syncsince', 'enrol_arlo'));
        $form->addHelpButton('startdate', 'syncsince', 'enrol_arlo');
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $time = time();
        if ($data['startdate'] > $time) {
            $errors['startdate'] = get_string('invalidstartdate', 'enrol_arlo');
        }
        return $errors;
    }
}
