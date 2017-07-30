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
 * @package     Frankenstyle {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright   2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die('Direct access to this script is forbidden.');

function xmldb_enrol_arlo_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    // Moodle v2.7.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v2.8.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v2.9.0 release upgrade line.
    // Put any upgrade step following this.

    // Migration step 1 - Setup link table.
    if ($oldversion < 2015101502) {
        // Conditionally install.
        if (!$dbman->table_exists('enrol_arlo_templatelink')) {
            $installfile = $CFG->dirroot . '/enrol/arlo/db/install.xml';
            $dbman->install_from_xmldb_file($installfile);
        }
        upgrade_plugin_savepoint(true, 2015101502, 'enrol', 'arlo');
    }

    // Migration step 2 - Move old enrolments over to template links.
    if ($oldversion < 2015101503) {
        // Conditionally move old enrolment methods.
        $rs = $DB->get_records('enrol', array('enrol' => 'arlo'), '', 'id, enrol, courseid, customchar1');
        foreach ($rs as $record) {
            $params = array('code' => $record->customchar1);
            $templateguid = $DB->get_field('local_arlo_templates', 'templateguid', $params);
            if ($templateguid) {
                $link = new \stdClass();
                $link->courseid = $record->courseid;
                $link->templateguid = $templateguid;
                if (!$DB->record_exists('enrol_arlo_templatelink', (array) $link)) {
                    $link->modified = time();
                    $DB->insert_record('enrol_arlo_templatelink', $link);
                }
            }
            $DB->set_field('enrol', 'name', 'LEGACY', array('id' => $record->id));
        }
        upgrade_plugin_savepoint(true, 2015101503, 'enrol', 'arlo');
    }

    // Migration to single plugin
    if ($oldversion < 2016052309) {
        require_once($CFG->dirroot . '/enrol/arlo/upgrade/upgradelib.php');

        enrol_arlo_upgrade_disable_local_tasks();

        enrol_arlo_upgrade_prepare_new_tables();

        enrol_arlo_upgrade_migrate_config();

        \enrol_arlo\manager::schedule('eventtemplates');
        \enrol_arlo\manager::schedule('events');
        \enrol_arlo\manager::schedule('onlineactivities');

        $platform = get_config('enrol_arlo', 'platform');
        $servertimezone = \core_date::get_server_timezone();
        $tz = new \DateTimeZone($servertimezone);

        // Event Templates.
        $rs = $DB->get_records('local_arlo_templates', array(), 'modified');
        foreach ($rs as $record) {
            $template                 = new \stdClass();
            $template->platform       = $platform;
            $template->sourceid       = $record->templateid;
            $template->sourceguid     = $record->templateguid;
            $template->name           = $record->name;
            $template->code           = $record->code;
            $template->sourcestatus   = $record->status;
            $created                  = \DateTime::createFromFormat('U', $record->created, $tz);
            $template->sourcecreated  = $created->format(DATE_ISO8601);
            $modified                 = \DateTime::createFromFormat('U', $record->modified, $tz);
            $template->sourcemodified = $modified->format(DATE_ISO8601);
            $template->modified       = time();
            $DB->insert_record('enrol_arlo_template', $template);

        }
        // Events.
        $rs = $DB->get_records('local_arlo_events', array(), 'modified');
        foreach ($rs as $record) {
            $event                      = new \stdClass();
            $event->platform            = $platform;
            $event->sourceid            = $record->eventid;
            $event->sourceguid          = $record->eventguid;
            $event->code                = $record->code;
            $startdatetime              = \DateTime::createFromFormat('U', $record->starttime, $tz);
            $event->startdatetime       = $startdatetime->format(DATE_ISO8601);
            $finishdatetime             = \DateTime::createFromFormat('U', $record->finishtime, $tz);
            $event->finishdatetime      = $finishdatetime->format(DATE_ISO8601);
            $event->sourcestatus        = $record->status;
            $created                    = \DateTime::createFromFormat('U', $record->created, $tz);
            $event->sourcecreated       = $created->format(DATE_ISO8601);
            $modified                   = \DateTime::createFromFormat('U', $record->modified, $tz);
            $event->sourcemodified      = $modified->format(DATE_ISO8601);
            $event->sourcetemplateguid  = $record->templateguid;
            $event->modified            = time();
            $DB->insert_record('enrol_arlo_event', $event);
        }
        // Online Activities.
        $rs = $DB->get_records('local_arlo_onlineactivities', array(), 'modified');
        foreach ($rs as $record) {
            $onlineactivity                     = new \stdClass();
            $onlineactivity->platform           = $platform;
            $onlineactivity->sourceid           = $record->onlineactivityid;
            $onlineactivity->sourceguid         = $record->onlineactivityguid;
            $onlineactivity->name               = $record->name;
            $onlineactivity->code               = $record->code;
            $onlineactivity->contenturi         = $record->contenturi;
            $onlineactivity->sourcestatus       = $record->status;
            $created                            = \DateTime::createFromFormat('U', $record->created, $tz);
            $onlineactivity->sourcecreated      = $created->format(DATE_ISO8601);
            $modified                           = \DateTime::createFromFormat('U', $record->modified, $tz);
            $onlineactivity->sourcemodified     = $modified->format(DATE_ISO8601);
            $onlineactivity->modified           = time();
            $onlineactivity->sourcetemplateguid = $record->templateguid;
            $DB->insert_record('enrol_arlo_onlineactivity', $onlineactivity);
        }

        // Contacts.
        $records = enrol_arlo_upgrade_get_usercontacts();
        foreach ($records as $record) {
            $contact                    = new \stdClass();
            $contact->platform          = $platform;
            $contact->userid            = $record->userid;
            $contact->sourceid          = $record->sourceid;
            $contact->sourceguid        = $record->sourceguid;
            $created                    = \DateTime::createFromFormat('U', $record->created, $tz);
            $contact->sourcecreated     = $created->format(DATE_ISO8601);
            $modified                   = \DateTime::createFromFormat('U', $record->modified, $tz);
            $contact->sourcemodified    = $modified->format(DATE_ISO8601);
            $contact->modified          = time();
            $DB->insert_record('enrol_arlo_contact', $contact);
        }
        // Arlo instances.
        $records = $DB->get_records('enrol', array('enrol' => 'arlo'));
        foreach ($records as $record) {
            $arloinstance = new \stdClass();
            $arloinstance->enrolid = $record->id;
            $arloinstance->platform = $platform;
            // Event.
            if ($record->customint3 == 0) {
                $arloinstance->type = \enrol_arlo_plugin::ARLO_TYPE_EVENT;
                $resourceinfo = $DB->get_record('local_arlo_events', array('eventguid' => $record->customchar3));
                $arloinstance->sourceid = $resourceinfo->eventid;
                $arloinstance->sourceguid = $resourceinfo->eventguid;
            }
            // Online Activity.
            if ($record->customint3 == 1) {
                $arloinstance->type = \enrol_arlo_plugin::ARLO_TYPE_ONLINEACTIVITY;
                $resourceinfo = $DB->get_record('local_arlo_onlineactivities', array('onlineactivityguid' => $record->customchar3));
                $arloinstance->sourceid = $resourceinfo->onlineactivityid;
                $arloinstance->sourceguid = $resourceinfo->onlineactivityguid;
            }
            $arloinstance->modified = time();
            $DB->insert_record('enrol_arlo_instance', $arloinstance);
        }
        // Registration enrolments.
        $records = enrol_arlo_upgrade_get_user_enrolments();
        $events = array();
        $onlineactivities = array();
        foreach ($records as $record) {
            $registration = new \stdClass();
            $registration->platform = $platform;
            $registration->enrolid = $record->enrolid;
            $registration->userid = $record->userid;
            $registration->sourcecontactid = $record->contactid;
            $registration->sourcecontactguid = $record->contactguid;
            $resourcesourceguid = $record->resourcesourceguid;
            // Event.
            if ($record->resourcetype == 0) {
                if (!isset($events[$resourcesourceguid])) {
                    $event = $DB->get_record('local_arlo_events', array('eventguid' => $resourcesourceguid), 'id, eventid, eventguid');
                    if (!$event) {
                        continue;
                    }
                    $events[$resourcesourceguid] = $event;
                }
                $event = $events[$resourcesourceguid];
                $registration->sourceeventid = $event->eventid;
                $registration->sourceeventguid = $event->eventguid;
                $conditions = array('eventguid' => $event->eventguid, 'contactguid' => $record->contactguid);
                $registrationrecord = $DB->get_record('local_arlo_registrations', $conditions);
            }
            // Online Activity.
            if ($record->resourcetype == 1) {
                if (!isset($onlineactivities[$resourcesourceguid])) {
                    $onlineactivity = $DB->get_record('local_arlo_onlineactivities', array('onlineactivityguid' => $resourcesourceguid), 'id, onlineactivityid, onlineactivityguid');
                    if (!$onlineactivity) {
                        continue;
                    }
                    $onlineactivities[$resourcesourceguid] = $onlineactivity;
                }
                $onlineactivity = $onlineactivities[$resourcesourceguid];
                $registration->sourceonlineactivityid = $onlineactivity->onlineactivityid;
                $registration->sourceonlineactivityguid = $onlineactivity->onlineactivityguid;
                $conditions = array('onlineactivityguid' => $onlineactivity->onlineactivityguid, 'contactguid' => $record->contactguid);
                $registrationrecord = $DB->get_record('local_arlo_registrations', $conditions);
            }
            $registration->sourceid = $registrationrecord->registrationid;
            $registration->sourceguid = $registrationrecord->registrationguid;
            $registration->sourcestatus = $registrationrecord->status;

            if (!empty($registrationrecord->attendance)) {
                $registration->attendance = $registrationrecord->attendance;
            }

            if (!empty($registrationrecord->grade)) {
                $registration->grade = $registrationrecord->grade;
            }

            if (!empty($registrationrecord->outcome)) {
                $registration->outcome = $registrationrecord->outcome;
            }

            if (!empty($registrationrecord->lastactivity)) {
                $registration->lastactivity = date('Y-m-d\TH:i:s.000+00:00', $registrationrecord->lastactivity);
            }

            if (!empty($registrationrecord->progressstatus)) {
                $registration->progressstatus = $registrationrecord->progressstatus;
            }

            if (!empty($registrationrecord->progresspercent)) {
                $registration->progresspercent = $registrationrecord->progresspercent;
            }
            $created                      = \DateTime::createFromFormat('U', $registrationrecord->created, $tz);
            $registration->sourcecreated  = $created->format(DATE_ISO8601);
            $modified                     = \DateTime::createFromFormat('U', $registrationrecord->modified, $tz);
            $registration->sourcemodified = $modified->format(DATE_ISO8601);
            $registration->modified       = time();
            $DB->insert_record('enrol_arlo_registration', $registration);
        }

        // Associated Event Templates.
        $records = $DB->get_records('enrol_arlo_templatelink');
        foreach ($records as $record) {
            $associate = new \stdClass();
            $associate->courseid = $record->courseid;
            $associate->platform = $platform;
            $associate->sourcetemplateguid = $record->templateguid;
            $associate->modified = time();
            $DB->insert_record('enrol_arlo_templateassociate', $associate);
        }

        // Remove local Arlo plugin configuration.
        unset_all_config_for_plugin('local_arlo');

        upgrade_plugin_savepoint(true, 2016052309, 'enrol', 'arlo');
    }


    return true;
}
