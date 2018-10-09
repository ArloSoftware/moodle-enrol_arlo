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

class configuration extends \moodleform {

    public function definition() {

        $form = $this->_form;

        // User account matching.
        $options = array();
        $options[user_matching::MATCH_BY_USER_DETAILS] = get_string('matchbyarlouserdetails', 'enrol_arlo');
        $options[user_matching::MATCH_BY_CODE_PRIMARY] = get_string('matchbyarlocodeprimary', 'enrol_arlo');
        $options[user_matching::MATCH_BY_AUTO] = get_string('matchbyauto', 'enrol_arlo');

        $form->addElement('select', 'matchuseraccountsby', get_string('matchuseraccountsby', 'enrol_arlo'), $options);
        $default = user_matching::MATCH_BY_DEFAULT;
        $form->setDefault('matchuseraccountsby', $default);
        $form->addHelpButton('matchuseraccountsby', 'matchuseraccountsby', 'enrol_arlo');

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

        $form->addElement('advcheckbox', 'pushonlineactivityresults',
            get_string('pushonlineactivityresults', 'enrol_arlo'));
        $form->setDefault('pushonlineactivityresults', 1);
        $form->addHelpButton('pushonlineactivityresults', 'pushonlineactivityresults', 'enrol_arlo');

        $form->addElement('advcheckbox', 'pusheventresults',
            get_string('pusheventresults', 'enrol_arlo'));
        $form->setDefault('pusheventresults', 1);
        $form->addHelpButton('pusheventresults', 'pusheventresults', 'enrol_arlo');


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

        $form->addElement('advcheckbox', 'allowunenrolactiveenrolmentsui',
            get_string('allowunenrolactiveenrolmentsui', 'enrol_arlo'), get_string('allowunenrolactiveenrolmentsui_text', 'enrol_arlo'));
        $form->setDefault('allowunenrolactiveenrolmentsui', 0);
        $form->addHelpButton('allowunenrolactiveenrolmentsui', 'allowunenrolactiveenrolmentsui', 'enrol_arlo');

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

        // Hack - Quick load existing config if exists.
        $config = (array) get_config('enrol_arlo');
        if ($config) {
            $this->set_data($config);
        }

        $this->add_action_buttons(true, get_string('savechanges', 'enrol_arlo'));
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