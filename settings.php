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
 * Things that are accessable:
 *  - $ADMIN = $adminroot;
 *  - $plugininfo = The Arlo enrolment plugin class;
 *  - $enrol = The Arlo enrolment plugin class;
 *
 * @package     enrol_arlo
 * @author      Troy Williams
 * @author      Corey Davis
 * @copyright   2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/adminlib.php');

if ($hassiteconfig) {
    $name = get_string('arloconnection', 'enrol_arlo');
    $settings = new admin_settingpage('enrolsettingsarlo', $name, 'moodle/site:config', $enrol->is_enabled() === false);

    $settings->add(new enrol_arlo_admin_setting_configarlostatus('apistatus', get_string('pluginstatus', 'enrol_arlo')));

    $name = get_string('platform', 'enrol_arlo');

    $url = new moodle_url('https://www.arlo.co/register#stage1');
    $url->param('utm_source', 'Moodle product');
    $url->param('utm_medium', 'referral inproduct');
    $url->param('utm_campaign', 'Moodle inproduct trial signup link');
    $title = get_string('opennewtabtitle', 'enrol_arlo');
    $description = get_string('platform_desc', 'enrol_arlo', array('url' => $url->out(), 'title' => $title));
    $settings->add(new enrol_arlo_admin_setting_configlockedtext('enrol_arlo/platform', $name, $description, ''));

    $name = get_string('apiusername', 'enrol_arlo');
    $url = new moodle_url('https://support.arlo.co/hc/en-gb/articles/115003692863');
    $url->param('utm_source', 'Moodle product');
    $url->param('utm_medium', 'referral inproduct');
    $url->param('utm_campaign', 'Moodle inproduct config support link');
    $title = get_string('opennewtabtitle', 'enrol_arlo');
    $description = get_string('apiusername_desc', 'enrol_arlo', array('url' => $url->out(), 'title' => $title));
    $settings->add(new enrol_arlo_admin_setting_configemail('enrol_arlo/apiusername', $name, $description, null));

    $url = new moodle_url('https://support.arlo.co/hc/en-gb/articles/211902623');
    $url->param('utm_source', 'Moodle product');
    $url->param('utm_medium', 'referral inproduct');
    $url->param('utm_campaign', 'Moodle inproduct forgot password support link');
    $title = get_string('opennewtabtitle', 'enrol_arlo');
    $description = get_string('apipassword_desc', 'enrol_arlo', array('url' => $url->out(), 'title' => $title));

    $name = get_string('apipassword', 'enrol_arlo');
    $settings->add(new admin_setting_configpasswordunmask('enrol_arlo/apipassword', $name, $description, ''));

    // Only display management category if plugin enabled.
    if ($enrol->is_enabled()) {
        $name = get_string('managearlo', 'enrol_arlo');
        $category = new admin_category('enrolsettingsarlomanage', $name);
        $ADMIN->add('enrolments', $category);

        $ADMIN->add('enrolsettingsarlomanage', new admin_externalpage('enrolsettingsarloconfiguration',
            $name = get_string('configuration', 'enrol_arlo'),
            new moodle_url('/enrol/arlo/admin/configuration.php')));
        $ADMIN->add('enrolsettingsarlomanage', new admin_externalpage('enrolsettingsarloapirequests',
            $name = get_string('apirequests', 'enrol_arlo'),
            new moodle_url('/enrol/arlo/admin/apirequests.php')));

        $ADMIN->add('enrolsettingsarlomanage', new admin_externalpage('enrolsettingsarlocommunications',
            $name = get_string('communications', 'enrol_arlo'),
            new moodle_url('/enrol/arlo/admin/communications.php')));

        $ADMIN->add(
            'enrolsettingsarlomanage',
            new admin_externalpage(
                'enrolsettingsarloenrolmentinstances',
                get_string('enrolmentinstances', 'enrol_arlo'),
                new moodle_url('/enrol/arlo/admin/enrolmentinstances.php')
            )
        );

        $ADMIN->add('enrolsettingsarlomanage', new admin_externalpage('enrolsettingsarlounsuccessfulenrolments',
            get_string('unsuccessfulenrolments', 'enrol_arlo'),
            new moodle_url('/enrol/arlo/admin/unsuccessfulenrolments.php'))
        );

        $ADMIN->add('enrolsettingsarlomanage', new admin_externalpage('enrolsettingsarlocontactmergefailure',
                get_string('unsuccessfulenrolments', 'enrol_arlo'),
                new moodle_url('/enrol/arlo/admin/unsuccessfulenrolments.php'),
                'moodle/site:config', true)
        );

        $ADMIN->add('enrolsettingsarlomanage', new admin_externalpage('enrolsettingsarlouserassociationfailure',
                get_string('unsuccessfulenrolments', 'enrol_arlo'),
                new moodle_url('/enrol/arlo/admin/unsuccessfulenrolments.php'),
                'moodle/site:config', true)
        );

        $ADMIN->add('enrolsettingsarlomanage', new admin_externalpage('enrolsettingsarlounenrolcontact',
                get_string('unenrolcontact', 'enrol_arlo'),
                new moodle_url('/enrol/arlo/admin/unenrolcontact.php'),
                'moodle/site:config', true)
        );

        $ADMIN->add('enrolsettingsarlomanage', new admin_externalpage('enrolsettingsarloreattemptenrolment',
                get_string('unsuccessfulenrolments', 'enrol_arlo'),
                new moodle_url('/enrol/arlo/admin/unsuccessfulenrolments.php'),
                'moodle/site:config', true)
        );
    }

}
