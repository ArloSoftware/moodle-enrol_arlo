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
 * Arlo enrolment plugin settings and presets.
 *
 * @package     enrol_arlo
 * @author      Troy Williams
 * @author      Corey Davis
 * @copyright   2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_heading('enrol_arlo_settings', '', get_string('pluginname_desc', 'enrol_arlo')));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_arlo/roleid',
            get_string('defaultrole', 'role'), '', $student->id, $options));

        $options = array(
            ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
            ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'));

        $settings->add(new admin_setting_configselect('enrol_arlo/unenrolaction',
            get_string('extremovedaction', 'enrol'),
            get_string('extremovedaction_help', 'enrol'), ENROL_EXT_REMOVED_UNENROL, $options));

        // Note: let's reuse the ext sync constants and strings here, internally it is very similar,
        //       it describes what should happend when users are not supposed to be enerolled any more.
        $options = array(
            ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
            ENROL_EXT_REMOVED_SUSPEND        => get_string('extremovedsuspend', 'enrol'),
            ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
            ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
        );
        $settings->add(new admin_setting_configselect('enrol_arlo/expiredaction', get_string('expiredaction', 'enrol_arlo'), get_string('expiredaction_help', 'enrol_arlo'), ENROL_EXT_REMOVED_KEEP, $options));

        $options = array();
        for ($i=0; $i<24; $i++) {
            $options[$i] = $i;
        }
        $settings->add(new admin_setting_configselect('enrol_arlo/expirynotifyhour', get_string('expirynotifyhour', 'core_enrol'), '', 6, $options));

        // Sync enrolment instance immediately on adding instance.
        $settings->add(new admin_setting_configcheckbox('enrol_arlo/syncinstanceonadd',
            get_string('syncinstanceonadd', 'enrol_arlo'),
            get_string('syncinstanceonadd_help', 'enrol_arlo'), 0));
    }
}
