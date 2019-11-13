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
 *  Form for configuration.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2017 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\form\admin;

defined('MOODLE_INTERNAL') || die();

use enrol_arlo\local\enum\user_matching;
use enrol_arlo\local\config\arlo_plugin_config;
use enrol_arlo\local\generator\username_generator;
use html_table;
use html_writer;

class configuration extends \moodleform {

    public function definition() {
        global $CFG;

        $form = $this->_form;

        // User account matching.
        $options = array();
        $options[user_matching::MATCH_BY_USER_DETAILS] = get_string('matchbyarlouserdetails', 'enrol_arlo');
        $options[user_matching::MATCH_BY_CODE_PRIMARY] = get_string('matchbyarlocodeprimary', 'enrol_arlo');
        $options[user_matching::MATCH_BY_AUTO] = get_string('matchbyauto', 'enrol_arlo');

        $form->addElement('header', 'useraccountmatching', get_string('useraccountmatching', 'enrol_arlo'));
        $form->setExpanded('useraccountmatching', true);

        $form->addElement('select', 'matchuseraccountsby', get_string('matchuseraccountsby', 'enrol_arlo'), $options);
        $default = user_matching::MATCH_BY_DEFAULT;
        $form->setDefault('matchuseraccountsby', $default);
        $form->addHelpButton('matchuseraccountsby', 'matchuseraccountsby', 'enrol_arlo');

        $form->addElement('header', 'useraccountcreation', get_string('useraccountcreation', 'enrol_arlo'));
        $form->setExpanded('useraccountcreation', true);

        $form->addElement('html', static::get_username_generation_table_html());

        $form->addElement('header', 'courseenrolment', get_string('courseenrolment', 'enrol_arlo'));
        $form->setExpanded('courseenrolment', true);

        $student = get_archetype_roles('student');
        $student = reset($student);

        $options = get_default_enrol_roles(\context_system::instance());
        $form->addElement('select', 'roleid', get_string('defaultrole', 'role'), $options);
        $form->setDefault('roleid', $student->id);
        $form->addHelpButton('roleid', 'defaultrole', 'enrol_arlo');

        $options = array(
            ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
            ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'));

        $form->addElement('select', 'unenrolaction', get_string('extremovedaction', 'enrol'), $options);
        $form->setDefault('unenrolaction', ENROL_EXT_REMOVED_SUSPEND);
        $form->addHelpButton('unenrolaction', 'extremovedaction', 'enrol');

        $options = array(
            ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
            ENROL_EXT_REMOVED_SUSPEND        => get_string('extremovedsuspend', 'enrol'),
            ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        );

        $form->addElement('select', 'expiredaction', get_string('expiredaction', 'enrol_arlo'), $options);
        $form->setDefault('expiredaction', ENROL_EXT_REMOVED_SUSPEND);
        $form->addHelpButton('expiredaction', 'expiredaction', 'enrol_arlo');

        $form->addElement('header', 'resulting', get_string('resulting', 'enrol_arlo'));
        $form->setExpanded('resulting', true);

        $form->addElement('advcheckbox', 'pushonlineactivityresults',
            get_string('pushonlineactivityresults', 'enrol_arlo'));
        $form->setDefault('pushonlineactivityresults', 1);
        $form->addHelpButton('pushonlineactivityresults', 'pushonlineactivityresults', 'enrol_arlo');

        $form->addElement('advcheckbox', 'pusheventresults',
            get_string('pusheventresults', 'enrol_arlo'));
        $form->setDefault('pusheventresults', 1);
        $form->addHelpButton('pusheventresults', 'pusheventresults', 'enrol_arlo');

        $form->addElement('header', 'other', get_string('other'));
        $form->setExpanded('other', true);

        $form->addElement('advcheckbox', 'allowcompletedevents',
            get_string('allowcompletedevents', 'enrol_arlo'),
            get_string('allowcompletedevents_text', 'enrol_arlo'));
        $form->setDefault('allowcompletedevents', 1);
        $form->addHelpButton('allowcompletedevents', 'allowcompletedevents', 'enrol_arlo');

        $form->addElement('advcheckbox', 'allowcompletedonlineactivities',
            get_string('allowcompletedonlineactivities', 'enrol_arlo'),
            get_string('allowcompletedonlineactivities_text', 'enrol_arlo'));
        $form->setDefault('allowcompletedonlineactivities', 1);
        $form->addHelpButton('allowcompletedonlineactivities', 'allowcompletedonlineactivities', 'enrol_arlo');

        $form->addElement('advcheckbox', 'allowhiddencourses',
            get_string('allowhiddencourses', 'enrol_arlo'), get_string('allowhiddencourses_text', 'enrol_arlo'));
        $form->setDefault('allowhiddencourses', 0);
        $form->addHelpButton('allowhiddencourses', 'allowhiddencourses', 'enrol_arlo');

        $form->addElement('advcheckbox', 'allowunenrolaccessedui',
            get_string('allowunenrolaccessedui', 'enrol_arlo'), get_string('allowunenrolaccessedui_text', 'enrol_arlo'));
        $form->setDefault('allowunenrolaccessedui', 0);
        $form->addHelpButton('allowunenrolaccessedui', 'allowunenrolaccessedui', 'enrol_arlo');

        $form->addElement('header', 'cleanup', get_string('cleanup', 'enrol_arlo'));
        $form->setExpanded('cleanup', true);

        $options = array(
            0 => get_string('never'),
            7 => get_string('numdays', '', 7),
            30 => get_string('numdays', '', 30),
            60 => get_string('numdays', '', 60),
            90 => get_string('numdays', '', 90)
        );
        $form->addElement('select', 'requestlogcleanup',
            get_string('requestlogcleanup', 'enrol_arlo'), $options);
        $form->setDefault('requestlogcleanup', 0);
        $form->addHelpButton('requestlogcleanup', 'requestlogcleanup', 'enrol_arlo');

        // Developer information for outgoing email delivery.
        $emaildisabled = !empty($CFG->noemailever);
        $emaildiverted = !empty($CFG->divertallemailsto);
        $emailprocessing = '';
        if ($emaildiverted) {
            $emailprocessing = get_string('divertedto', 'enrol_arlo', $CFG->divertallemailsto);
        }
        if ($emaildisabled) {
            $emailprocessing = get_string('disabled', 'enrol_arlo');
        }
        if ($emailprocessing) {
            $form->addElement('static', 'outboundemaildelivery',
                get_string('outboundemaildelivery', 'enrol_arlo'),
                $emailprocessing
            );
        }

        // Hack - Quick load existing config if exists.
        $config = (array) get_config('enrol_arlo');
        if ($config) {
            $this->set_data($config);
        }
        $this->add_action_buttons(true, get_string('savechanges', 'enrol_arlo'));
    }

    /**
     * Table for username format order.
     *
     * @return string
     * @throws \coding_exception
     */
    public static function  get_username_generation_table_html() {
        global $PAGE, $OUTPUT;
        $url = clone($PAGE->url);
        $strmoveup = get_string('moveup');
        $strmovedown = get_string('movedown');
        $pluginconfig = new arlo_plugin_config();
        $table = new html_table();
        $table->colclasses = ['leftalign', 'leftalign', 'leftalign', 'leftleft'];
        $table->id = 'roles';
        $table->attributes['class'] = 'admintable generaltable';
        $table->head = [
            get_string('order'),
            get_string('name'),
            get_string('description'),
            get_string('move')
        ];
        $usernamegenerator = new username_generator();
        $usernamegenerator->set_order($pluginconfig->get('usernameformatorder'));
        $formats = $usernamegenerator->export_current_order_to_array();
        $first = (object) reset($formats);
        $last = (object) end($formats);
        foreach ($formats as $format) {
            $formatobject = (object) $format;
            $row = [
                $formatobject->order,
                $formatobject->name,
                $formatobject->description,
                ''
            ];
            $url->remove_all_params();
            // Move up.
            if ($first->shortname != $formatobject->shortname) {
                $url->params(['action' => 'moveup', 'usernameformat' => $formatobject->shortname, 'sesskey' => sesskey()]);
                $row[3] .= html_writer::link($url, $OUTPUT->pix_icon('t/up', $strmoveup));
            } else {
                $row[3] .= $OUTPUT->spacer();
            }
            // Move down.
            if ($last->shortname != $formatobject->shortname) {
                $url->params(['action' => 'movedown', 'usernameformat' => $formatobject->shortname, 'sesskey' => sesskey()]);
                $row[3] .= html_writer::link($url, $OUTPUT->pix_icon('t/down', $strmovedown));
            } else {
                $row[3] .= $OUTPUT->spacer();
            }
            $table->data[] = $row;
        }
        return get_string('usernamegeneration_desc', 'enrol_arlo') . html_writer::table($table);
    }

    /**
     * Returns the options array to use in text editor.
     *
     * @return array
     */
    public static function editor_options() {
        global $CFG, $PAGE;

        $maxbytes = get_user_max_upload_file_size($PAGE->context, $CFG->maxbytes);
        return array(
            'collapsed' => true,
            'maxfiles' => 0,
            'maxbytes' => $maxbytes,
            'trusttext' => true,
            'accepted_types' => 'web_image',
            'return_types' => FILE_INTERNAL | FILE_EXTERNAL
        );
    }
}