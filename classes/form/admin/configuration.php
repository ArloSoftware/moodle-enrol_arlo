<?php
// This program is free software; you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation; either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

/**
 *  Form for configuration.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2017 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\form\admin;

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

require_once($CFG->libdir . '/formslib.php');

class configuration extends \moodleform {

    public function definition() {

        $form = $this->_form;

        $form->addElement('header', 'user', get_string('user'));

        // User account matching.
        $options = array();
        $options[\enrol_arlo\user_match::BY_USER_DETAILS] = get_string('matchbyarlouserdetails', 'enrol_arlo');
        $options[\enrol_arlo\user_match::BY_CODE_PRIMARY] = get_string('matchbyarlocodeprimary', 'enrol_arlo');
        $options[\enrol_arlo\user_match::AUTO] = get_string('matchbyauto', 'enrol_arlo');


        $form->addElement('select', 'matchuseraccountsby', get_string('matchuseraccountsby', 'enrol_arlo'), $options);
        $default = \enrol_arlo\user_match::BY_DEFAULT;
        $form->setDefault('matchuseraccountsby', $default);
        $form->addHelpButton('matchuseraccountsby', 'matchuseraccountsby', 'enrol_arlo');


        $auths = \core_component::get_plugin_list('auth');
        $options = array();
        foreach ($auths as $auth => $unused) {
            $plugin = get_auth_plugin($auth);
            if (is_enabled_auth($auth)) {
                // Check plugin allows resetting of internal password.
                if ($plugin->can_reset_password()) {
                    $options[$auth] = get_string('pluginname', "auth_{$auth}");
                }
            }
        }

        $form->addElement('select', 'authplugin', get_string('chooseauthmethod', 'enrol_arlo'), $options);
        $form->setDefault('authplugin', 'manual');


        $form->addElement('editor', 'newuserdefaultemail',
            get_string('newuserdefaultemail', 'enrol_arlo'),
            null, self::editor_options());
        $form->setType('newuserdefaultemail', PARAM_RAW);

        $form->addElement('header', 'enrolment', get_string('enrolment', 'enrol_arlo'));

        $student = get_archetype_roles('student');
        $student = reset($student);

        $options = get_default_enrol_roles(\context_system::instance());
        $form->addElement('select', 'roleid', get_string('defaultrole', 'role'), $options);
        $form->setDefault('roleid', $student->id);

        $options = array(
            ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
            ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'));


        $form->addElement('select', 'unenrolaction', get_string('extremovedaction', 'enrol'), $options);
        $form->setDefault('unenrolaction', ENROL_EXT_REMOVED_UNENROL);
        $form->addHelpButton('unenrolaction', 'extremovedaction', 'enrol');

        // Note: let's reuse the ext sync constants and strings here, internally it is very similar,
        //       it describes what should happend when users are not supposed to be enerolled any more.
        $options = array(
            ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
            ENROL_EXT_REMOVED_SUSPEND        => get_string('extremovedsuspend', 'enrol'),
            ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        );

        $form->addElement('select', 'expiredaction', get_string('expiredaction', 'enrol_arlo'), $options);
        $form->setDefault('expiredaction', ENROL_EXT_REMOVED_SUSPEND);
        $form->addHelpButton('expiredaction', 'expiredaction', 'enrol_arlo');

        $form->addElement('header', 'resulting', get_string('resulting', 'enrol_arlo'));

        $form->addElement('advcheckbox', 'pushonlineactivityresults',
            get_string('pushonlineactivityresults', 'enrol_arlo'));
        $form->setDefault('pushonlineactivityresults', 1);
        $form->addHelpButton('pushonlineactivityresults', 'pushonlineactivityresults', 'enrol_arlo');

        $form->addElement('advcheckbox', 'pusheventresults',
            get_string('pusheventresults', 'enrol_arlo'));
        $form->setDefault('pusheventresults', 0);
        $form->addHelpButton('pusheventresults', 'pusheventresults', 'enrol_arlo');

        $form->addElement('header', 'alert', get_string('alert', 'enrol_arlo'));

        $form->addElement('advcheckbox', 'alertsiteadmins',
            get_string('siteadmins', 'enrol_arlo'));


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
            'trusttext'=> true,
            'accepted_types' => 'web_image',
            'return_types'=> FILE_INTERNAL | FILE_EXTERNAL
        );
    }
}