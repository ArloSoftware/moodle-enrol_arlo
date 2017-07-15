<?php

namespace enrol_arlo\form;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->libdir . '/formslib.php');

class associatetemplate extends \moodleform {
    public function definition() {
        global $DB, $COURSE;
        $form = $this->_form;
        $plugin = new \enrol_arlo_plugin();
        $templates = $plugin->get_template_options($COURSE->id);
        if (empty($templates)) {
        }
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
