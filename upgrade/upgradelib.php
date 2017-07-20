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
        $table->add_field('enrolid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0';
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
        $table->add_field('progresspercent', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('sourcestatus', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('sourcecreated', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('sourcemodified', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('sourcecontactid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('sourcecontactguid', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('sourceeventid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('sourceeventguid', XMLDB_TYPE_CHAR, '36', null, null, null, null);
        $table->add_field('sourceonlineactivityid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('sourceonlineactivtyguid', XMLDB_TYPE_CHAR, '36', null, null, null, null);
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
        $table->add_field('nextpulltime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastpulltime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('endpulltime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('nextpushtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastpushtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('endpushtime', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
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
    // Conditionally add email table.
    if (!$dbman->table_exists('enrol_arlo_emaillog')) {
        $table = new xmldb_table('enrol_arlo_emaillog');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timelogged', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('type', XMLDB_TYPE_CHAR, '128', null, null, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('delivered', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('extra', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $dbman->create_table($table);
    }
    // Conditionally add request log table.
    if (!$dbman->table_exists('enrol_arlo_requestlog')) {
        $table = new xmldb_table('enrol_arlo_requestlog');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('timelogged', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('platform', XMLDB_TYPE_CHAR, '128', null, null, null, null);
        $table->add_field('uri', XMLDB_TYPE_CHAR, '512', null, null, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('extra', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $dbman->create_table($table);
    }

}