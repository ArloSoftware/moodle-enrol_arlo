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
 * @author      Troy Williams
 * @package     enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright   2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace enrol_arlo\form\admin;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/enrol/arlo/lib.php');
class linktemplate_form extends \moodleform{
    public function definition() {
        global $DB;
        $mform = $this->_form;

        list($link, $course) = $this->_customdata;

        $templates = array();
        $templates[''] = get_string("select") . '...';
        // Build templates options group.
        //get_template_options
        foreach (\enrol_arlo_plugin::get_template_options() as $key => $template) {
            $templates[$template->templateguid] = $template->code . ' ' . $template->name;
        }
        if ($link->id) {
            $mform->addElement('select', 'template', get_string('template', 'enrol_arlo'), $templates);
            $mform->setConstant('template', $link->templateguid);
            $mform->hardFreeze('template', $link->templateguid);
        } else {
            $activetemplates = $DB->get_records('enrol_arlo_templatelink');
            foreach ($activetemplates as $activetemplate) {
                unset($templates[$activetemplate->templateguid]);
            }
            $mform->addElement('select', 'template', get_string('template', 'enrol_arlo'), $templates);
            $mform->addRule('template', get_string('required'), 'required', null, 'client');
        }
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'id', null);
        $mform->setType('id', PARAM_INT);
        if ($link->id) {
            $this->add_remove_button();
        } else {
            $this->add_action_buttons(true);
        }
        $this->set_data($link);
    }
    /**
     * Adds buttons on create new method form
     */
    protected function add_remove_button() {
        $mform = $this->_form;
        $buttonarray = array();
        $buttonarray[0] = $mform->createElement('submit', 'submitbuttonremove', get_string('remove', 'enrol_arlo'));
        $buttonarray[1] = $mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', array(' '), false);
        $mform->closeHeaderBefore('buttonar');
    }
}
