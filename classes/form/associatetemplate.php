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

class associatetemplate extends \moodleform {
    public function definition() {
        global $DB, $COURSE;
        $form = $this->_form;
        $plugin = new \enrol_arlo_plugin();
        $templates = $plugin->get_template_options($COURSE->id);
        array_unshift($templates, get_string('choose') . '...');
        $form->addElement('select', 'sourcetemplateguid', get_string('template', 'enrol_arlo'), $templates);
        $form->addRule('sourcetemplateguid', get_string('required'), 'required', null, 'client');
        $coursetemplate = $DB->get_record('enrol_arlo_templateassociate', array('courseid' => $COURSE->id));
        if (!empty($coursetemplate->sourcetemplateguid)) {
            $form->setConstant('sourcetemplateguid', $coursetemplate->sourcetemplateguid);
            $form->hardFreeze('sourcetemplateguid', $coursetemplate->sourcetemplateguid);
            $this->add_remove_button();
        } else {
            $this->add_action_buttons(true);
        }
        $form->addElement('hidden', 'id');
        $form->setType('id', PARAM_INT);
        $form->setDefault('id', $COURSE->id);
    }
    /**
     * Adds buttons on create new method form
     */
    protected function add_remove_button() {
        $form = $this->_form;
        $buttonarray = array();
        $buttonarray[0] = $form->createElement('submit', 'submitbuttonremove', get_string('remove', 'enrol_arlo'));
        $buttonarray[1] = $form->createElement('cancel');
        $buttonarray[2] = $form->createElement('submit', 'submitbuttonsynchronize', get_string('synchronize', 'enrol_arlo'), [], false);
        $form->addGroup($buttonarray, 'buttonarray', '', array(''), false);
        $form->closeHeaderBefore('buttonarray');
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        if (empty($data['sourcetemplateguid'])) {
            $errors['sourcetemplateguid'] = get_string('errorselecttemplate', 'enrol_arlo');
        }
        return $errors;
    }
}
