<?php
/**
 * @package     Arlo Moodle Integration
 * @subpackage  enrol_arlo
 * @author      Corey Davis
 * @copyright   2015 LearningWorks Ltd <http://www.learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

//$arloCore = new local_arlo_core();
class enrol_arlo_edit_form extends moodleform {

    function definition() {
        $mform = $this->_form;

        list($instance, $plugin, $context) = $this->_customdata;
        //$plugin = enrol_get_plugin('arlo');

        $mform->addElement('header', 'header', get_string('pluginname', 'enrol_arlo'));
        $templates = $plugin->getTemplateCodes($instance);
        if ($templates != null){
            $mform->addElement('select', 'customchar1', get_string('customchar1', 'enrol_arlo'), $templates);
            $mform->addHelpButton('customchar1', 'customchar1', 'enrol_arlo');
            $mform->setType('customchar1', PARAM_TEXT);
            $mform->setDefault('customchar1', $plugin->get_config('customchar1'));

            $mform->addElement('hidden', 'courseid');
            $mform->setType('courseid', PARAM_INT);

            if (enrol_accessing_via_instance($instance)) {
                $mform->addElement('static', 'selfwarn', get_string('instanceeditselfwarning', 'core_enrol'), get_string('instanceeditselfwarningtext', 'core_enrol'));
            }

            $this->add_action_buttons(true, ($instance->id ? null : get_string('addinstance', 'enrol')));
        }else{
            $mform->addElement('static', 'selfwarn', get_string('instanceeditselfwarning', 'core_enrol'), get_string('notemplatesavali', 'enrol_arlo'));
        }
        

        $this->set_data($instance);
    }

    function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);

        return $errors;
    }
}
