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

defined('MOODLE_INTERNAL') || die();

/**
 * Make new Arlo enrolment tables.
 *
 * @return bool
 */
function enrol_arlo_upgrade_prepare_new_tables() {
    global $DB;

    $dbman = $DB->get_manager();
    // Conditionally add Contact table.
    if (!$dbman->table_exists('enrol_arlo_contact')) {
        $table = new xmldb_table('enrol_arlo_contact');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('platform', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourceguid', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('sourcecreated', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('sourcemodified', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('lastpulltime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lasterror', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('errorcount', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('modified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('contactsourceguid', XMLDB_INDEX_UNIQUE, array('sourceguid'));
        $dbman->create_table($table);
    }
    // Conditionally add email table.
    if (!$dbman->table_exists('enrol_arlo_emailqueue')) {
        $table = new xmldb_table('enrol_arlo_emailqueue');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('enrolid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('type', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('extra', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('modified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $dbman->create_table($table);
    }
    // Conditionally add Event table.
    if (!$dbman->table_exists('enrol_arlo_event')) {
        $table = new xmldb_table('enrol_arlo_event');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('platform', XMLDB_TYPE_CHAR, '128', null, null, null, null);
        $table->add_field('sourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourceguid', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('code', XMLDB_TYPE_CHAR, '32', null, null, null, null);
        $table->add_field('startdatetime', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('finishdatetime', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('sourcestatus', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('sourcecreated', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('sourcemodified', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('sourcetemplateid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('sourcetemplateguid', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('modified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('eventsourceguid', XMLDB_INDEX_UNIQUE, array('sourceguid'));
        $dbman->create_table($table);
    }
    // Conditionally add Arlo instance table.
    if (!$dbman->table_exists('enrol_arlo_instance')) {
        $table = new xmldb_table('enrol_arlo_instance');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('enrolid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('platform', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '14', null, null, null, null);
        $table->add_field('sourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourceguid', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('modified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('instancesourceguid', XMLDB_INDEX_UNIQUE, array('sourceguid'));
        $dbman->create_table($table);
    }
    // Conditionally add Online Activity table.
    if (!$dbman->table_exists('enrol_arlo_onlineactivity')) {
        $table = new xmldb_table('enrol_arlo_onlineactivity');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('platform', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourceguid', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('code', XMLDB_TYPE_CHAR, '32', null, null, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '128', null, null, null, null);
        $table->add_field('contenturi', XMLDB_TYPE_CHAR, '256', null, null, null, null);
        $table->add_field('sourcestatus', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('sourcecreated', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('sourcemodified', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('sourcetemplateid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('sourcetemplateguid', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('modified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('onlineactivitysourceguid', XMLDB_INDEX_UNIQUE, array('sourceguid'));
        $dbman->create_table($table);
    }
    // Conditionally add Registrations table.
    if (!$dbman->table_exists('enrol_arlo_registration')) {
        $table = new xmldb_table('enrol_arlo_registration');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('enrolid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('platform', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourceguid', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('attendance', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('grade', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('outcome', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('lastactivity', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('progressstatus', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('progresspercent', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('sourcestatus', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('sourcecreated', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('sourcemodified', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('sourcecontactid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('sourcecontactguid', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('sourceeventid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('sourceeventguid', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('sourceonlineactivityid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('sourceonlineactivityguid', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('updatesource', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastpulltime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastpushtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lasterror', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('errorcount', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('modified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('registrationsourceguid', XMLDB_INDEX_UNIQUE, array('sourceguid'));
        $dbman->create_table($table);
    }
    // Conditionally add Schedule table.
    if (!$dbman->table_exists('enrol_arlo_schedule')) {
        $table = new xmldb_table('enrol_arlo_schedule');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('enrolid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('platform', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $table->add_field('resourcetype', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('latestsourcemodified', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('lastsourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('nextpulltime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastpulltime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('endpulltime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('nextpushtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastpushtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('endpushtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lasterror', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('errorcount', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('modified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $dbman->create_table($table);
    }
    // Conditionally add Event Template table.
    if (!$dbman->table_exists('enrol_arlo_template')) {
        $table = new xmldb_table('enrol_arlo_template');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('platform', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sourceid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourceguid', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '128', null, null, null, null);
        $table->add_field('code', XMLDB_TYPE_CHAR, '32', null, null, null, null);
        $table->add_field('sourcestatus', XMLDB_TYPE_CHAR, '10', null, null, null, null);
        $table->add_field('sourcecreated', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('sourcemodified', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('modified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('templatesourceguid', XMLDB_INDEX_UNIQUE, array('sourceguid'));
        $dbman->create_table($table);
    }
    // Conditionally add Template Associate table.
    if (!$dbman->table_exists('enrol_arlo_templateassociate')) {
        $table = new xmldb_table('enrol_arlo_templateassociate');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('platform', XMLDB_TYPE_CHAR, '128', null, null, null, null);
        $table->add_field('sourcetemplateid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sourcetemplateguid', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('modified', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('uniquecourseid', XMLDB_INDEX_UNIQUE, array('courseid'));
        $dbman->create_table($table);
    }
    // Conditionally add request log table.
    if (!$dbman->table_exists('enrol_arlo_requestlog')) {
        $table = new xmldb_table('enrol_arlo_requestlog');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timelogged', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('platform', XMLDB_TYPE_CHAR, '128', null, null, null, null);
        $table->add_field('uri', XMLDB_TYPE_CHAR, '1024', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('extra', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $dbman->create_table($table);
    }
    return true;
}

/**
 * Migrate config.
 *
 * @return bool
 */
function enrol_arlo_upgrade_migrate_config() {
    $config = get_config('local_arlo');

    set_config('apistatus', -1, 'enrol_arlo');
    set_config('apilastrequested', 0, 'enrol_arlo');
    set_config('apierrorcount', 0, 'enrol_arlo');

    $platform = '';
    if (!empty($config->platformname)) {
        $platform = $config->platformname;
    } else if (!empty($config->setting_arlo_orgname)) {
        $platform = $config->setting_arlo_orgname;
    }
    if (!strstr($platform, '.', true)) {
        $platform .= '.arlo.co';
    }
    set_config('platform', $platform, 'enrol_arlo');

    $apiusername = '';
    if (!empty($config->apiusername)) {
        $apiusername = $config->apiusername;
    } else if (!empty($config->setting_arlo_username)) {
        $apiusername = $config->setting_arlo_username;
    }
    set_config('apiusername', $apiusername, 'enrol_arlo');

    $apipassword = '';
    if (!empty($config->apipassword)) {
        $apipassword = $config->apipassword;
    } else if (!empty($config->setting_arlo_password)) {
        $apipassword = $config->setting_arlo_password;
    }
    set_config('apipassword', $apipassword, 'enrol_arlo');

    if (isset($config->authplugin)) {
        set_config('authplugin', $config->authplugin, 'enrol_arlo');
    } else {
        set_config('authplugin', 'manual', 'enrol_arlo');
    }
    if (isset($config->matchuseraccountsby)) {
        set_config('matchuseraccountsby', $config->matchuseraccountsby, 'enrol_arlo');
    } else {
        set_config('matchuseraccountsby', enrol_arlo\user::MATCH_BY_DEFAULT, 'enrol_arlo');
    }
    if (isset($config->eventresults)) {
        set_config('pusheventresults', $config->eventresults, 'enrol_arlo');
    } else {
        set_config('pusheventresults', 0, 'enrol_arlo');
    }
    if (isset($config->onlineactivityresults)) {
        set_config('pushonlineactivityresults', $config->onlineactivityresults, 'enrol_arlo');
    } else {
        set_config('pushonlineactivityresults', 0, 'enrol_arlo');
    }
    set_config('alertsiteadmins', 1, 'enrol_arlo');
    return true;
}

/**
 * Disable old login plugin scheduled tasks.
 *
 * @return bool
 */
function enrol_arlo_upgrade_disable_local_tasks() {
    // Check for legacy local plugin.
    $manager = core_plugin_manager::instance();
    $plugin = $manager->get_plugin_info('local_arlo');
    if (!is_null($plugin) && $plugin->is_enabled()) {
        $disable = array('local_arlo\task\full_sync', 'local_arlo\task\pointintime_sync');
        foreach ($disable as $taskname) {
            $task = \core\task\manager::get_scheduled_task($taskname);
            $task->set_disabled(true);
            $task->set_customised(true);
            \core\task\manager::configure_scheduled_task($task);
        }
    }
    return true;
}

/**
 * Get array of Moodle users with associated Arlo contact link.
 *
 * @return array
 */
function enrol_arlo_upgrade_get_usercontacts() {
    global $DB;

    $sql = "SELECT u.id AS userid, lac.contactid AS sourceid, lac.contactguid AS sourceguid, lac.created, lac.modified
              FROM {user} u
              JOIN {user_info_data} uid
                ON uid.userid = u.id
              JOIN {user_info_field} uif
                ON (uid.fieldid = uif.id AND uif.shortname = 'arloguid')
              JOIN {local_arlo_contacts} lac
                ON lac.contactguid = uid.data
              WHERE uid.data <> ''
           ORDER BY lac.modified";
    return $DB->get_records_sql($sql);
}

/**
 * Get array of user enrolments.
 *
 * @return array
 */
function enrol_arlo_upgrade_get_user_enrolments() {
    global $DB;
    $sql = "SELECT ue.id, ue.enrolid, ue.userid, e.customint3 AS resourcetype,
                   e.customchar3 AS resourcesourceguid, eac.sourceid AS contactid,
                   eac.sourceguid AS contactguid
              FROM {enrol} e
              JOIN {user_enrolments} ue ON ue.enrolid = e.id
              JOIN {enrol_arlo_contact} eac ON eac.userid = ue.userid
             WHERE enrol = 'arlo'
          ORDER BY ue.enrolid, ue.userid";
    return $DB->get_records_sql($sql);
}