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
 * Form to configure the Arlo webhook.
 * 
 * @package   enrol_arlo
 * @author    2023 Oscar Nadjar <oscar.nadjar@moodle.com>
 * @copyright Moodle US
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\form\admin;

require_once($CFG->dirroot. '/lib/formslib.php');
defined('MOODLE_INTERNAL') || die();

class webhook_form extends \moodleform {

    public function definition() {
        $form = $this->_form;

        $form->addElement('text', 'name', get_string('webhookname', 'enrol_arlo'));
        $form->setType('name', PARAM_TEXT);

        $form->addElement('text', 'contact', get_string('technicalcontact', 'enrol_arlo'));
        $form->setType('contact', PARAM_TEXT);

        $form->addElement('hidden', 'format', 'Json_2019_11_01');
        $form->setType('format', PARAM_TEXT);

        $this->add_action_buttons(false, get_string('createwebhook', 'enrol_arlo'));
    }
}
