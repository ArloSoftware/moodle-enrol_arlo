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
 *
 * @author      Troy Williams
 * @package     enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright   2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/enrol/arlo/lib.php');

use enrol_arlo\Arlo\AuthAPI\Enum\EventStatus;
use enrol_arlo\Arlo\AuthAPI\Enum\OnlineActivityStatus;

/**
 * Deletes all occurences of the old instance
 * troughout the arlo tables if FQDN setting is changed.
 *
 * @param String $oldinstance
 * @param String $newinstance
 */
function enrol_arlo_change_platform($oldinstance, $newinstance) {
    global $DB, $CFG;
    require_once($CFG->dirroot.'/enrol/arlo/lib.php');
    // Do nothing on empties.
    if (empty($oldinstance) || empty($newinstance)){
        return;
    }
    // Nothing changed.
    if ($oldinstance  === $newinstance) {
        return;
    }
    $rs = $DB->get_recordset('enrol', array('enrol' => 'arlo'));
    $plugin = new enrol_arlo_plugin();
    foreach ($rs as $instance) {
        $plugin->delete_instance($instance);
    }
    $rs->close();
    role_unassign_all(array('component' => 'enrol_arlo'));
    // Clear any create password flags.
    $DB->delete_records('user_preferences', array('name' => 'enrol_arlo_createpassword'));
    // Clear out tables.
    $DB->delete_records('enrol_arlo_applicationlog');
    $DB->delete_records('enrol_arlo_contact', array('platform' => $oldinstance));
    $DB->delete_records('enrol_arlo_emaillog');
    $DB->delete_records('enrol_arlo_event', array('platform' => $oldinstance));
    $DB->delete_records('enrol_arlo_instance', array('platform' => $oldinstance));
    $DB->delete_records('enrol_arlo_onlineactivity', array('platform' => $oldinstance));
    $DB->delete_records('enrol_arlo_registration', array('platform' => $oldinstance));
    $DB->delete_records('enrol_arlo_requestlog', array('platform' => $oldinstance));
    $DB->delete_records('enrol_arlo_schedule', array('platform' => $oldinstance));
    $DB->delete_records('enrol_arlo_template', array('platform' => $oldinstance));
    $DB->delete_records('enrol_arlo_templateassociate', array('platform' => $oldinstance));
    // Finally purge all caches.
    purge_all_caches();
}

function enrol_arlo_associate_all($course, $sourcetemplateguid) {
    global $DB;
    $conditions = array('sourceguid' => $sourcetemplateguid);
    $template = $DB->get_record('enrol_arlo_template', $conditions, '*', MUST_EXIST);
    $record = new stdClass();
    $record->courseid = $course->id;
    $record->platform = $template->platform;
    $record->sourcetemplateid = $template->sourceid;
    $record->sourcetemplateguid = $template->sourceguid;
    $record->modified = time();
    $DB->insert_record('enrol_arlo_templateassociate', $record);
    // Container for the items to add.
    $adds = array();
    // Events.
    $sql = "SELECT e.sourceguid 
              FROM {enrol_arlo_event} e
             WHERE e.sourcetemplateguid = :sourcetemplateguid 
               AND e.sourcestatus = :sourcestatus
               AND e.sourceguid NOT IN (SELECT i.sourceguid FROM {enrol_arlo_instance} i)";
    $conditions = array(
        'sourcetemplateguid' => $sourcetemplateguid,
        'sourcestatus' => EventStatus::ACTIVE
    );
    $events = $DB->get_records_sql($sql, $conditions);
    foreach ($events as $event) {
        $item = new stdClass();
        $item->arlotype = \enrol_arlo_plugin::ARLO_TYPE_EVENT;
        $item->arloevent = $event->sourceguid;
        $adds[] = $item;
    }
    // Online Activities.
    $sql = "SELECT e.sourceguid 
              FROM {enrol_arlo_onlineactivity} e
             WHERE e.sourcetemplateguid = :sourcetemplateguid 
               AND e.sourcestatus = :sourcestatus
               AND e.sourceguid NOT IN (SELECT i.sourceguid FROM {enrol_arlo_instance} i)";
    $conditions = array(
        'sourcetemplateguid' => $sourcetemplateguid,
        'sourcestatus' => OnlineActivityStatus::ACTIVE
    );
    $onlineactivites = $DB->get_records_sql($sql, $conditions);
    foreach ($onlineactivites as $onlineactivity) {
        $item = new stdClass();
        $item->arlotype = \enrol_arlo_plugin::ARLO_TYPE_ONLINEACTIVITY;
        $item->arloonlineactivity = $onlineactivity->sourceguid;
        $adds[] = $item;
    }
    // Get enrol plugin instance.
    $plugin = new \enrol_arlo_plugin();
    foreach ($adds as $add) {
        $newinstance = $plugin->get_instance_defaults();
        $newinstance['arlotype'] = $add->arlotype;
        if (isset($add->arloevent)) {
            unset($newinstance['arloonlineactivity']); // Stop enrol complaining.
            $newinstance['arloevent'] = $add->arloevent;
        }
        if (isset($add->arloonlineactivity)) {
            unset($newinstance['arloevent']); // Stop enrol complaining.
            $newinstance['arloonlineactivity'] = $add->arloonlineactivity;
        }
        $plugin->add_instance($course, $newinstance);
    }
}

function enrol_arlo_unassociate_all($course, $sourcetemplateguid) {
    global $DB;
    // Get enrol plugin instance.
    $plugin = new \enrol_arlo_plugin();
    $instances = enrol_arlo_get_associated_instances($course, $sourcetemplateguid);
    foreach ($instances as $instance) {
        if ($plugin->can_delete_instance($instance)) {
            $plugin->delete_instance($instance);
        }
    }
    // Delete association.
    $conditions = array('courseid' => $course->id, 'sourcetemplateguid' => $sourcetemplateguid);
    $DB->delete_records('enrol_arlo_templateassociate', $conditions);
}

function enrol_arlo_get_associated_instances($course, $sourcetemplateguid) {
    global $DB;
    $conditions = array(
        'courseid' => $course->id,
        'sourcetemplateguid' => $sourcetemplateguid

    );
    $sql = "SELECT e.*
              FROM {enrol} e
              JOIN {enrol_arlo_instance} ai ON ai.enrolid = e.id
              JOIN {enrol_arlo_event} ae ON ae.sourceguid = ai.sourceguid 
             WHERE e.enrol = 'arlo'
               AND e.courseid = :courseid
               AND ae.sourcetemplateguid = :sourcetemplateguid";
    $events = $DB->get_records_sql($sql, $conditions);
    $sql = "SELECT e.*
              FROM {enrol} e
              JOIN {enrol_arlo_instance} ai ON ai.enrolid = e.id
              JOIN {enrol_arlo_onlineactivity} aoa ON aoa.sourceguid = ai.sourceguid 
             WHERE e.enrol = 'arlo'
               AND e.courseid = :courseid
               AND aoa.sourcetemplateguid = :sourcetemplateguid";
    $onlineactivities = $DB->get_records_sql($sql, $conditions);
    // Merge Events and Online Activities.
    return array_merge($events, $onlineactivities);
}

function enrol_arlo_add_associated($arlotype, $eventdata) {
    global $DB;
    $plugin = new enrol_arlo_plugin();
    $platform = $plugin->get_config('platform', false);
    if (!$platform) {
        return;
    }
    $sourceguid = $eventdata['sourceguid'];
    $sourcestatus = $eventdata['sourcestatus'];
    $sourcetemplateguid = $eventdata['sourcetemplateguid'];
    // Can associate.
    if ($arlotype == \enrol_arlo_plugin::ARLO_TYPE_EVENT) {
        if (! ($sourcestatus == EventStatus::ACTIVE) || ($sourcestatus == EventStatus::COMPLETED)) {
            return;
        }
    }
    if ($arlotype == \enrol_arlo_plugin::ARLO_TYPE_ONLINEACTIVITY) {
        if (! ($sourcestatus == OnlineActivityStatus::ACTIVE) || ($sourcestatus == OnlineActivityStatus::COMPLETED)) {
            return;
        }
    }
    // Associated Template.
    $sql = "SELECT c.* 
              FROM {course} c
              JOIN {enrol_arlo_templateassociate} ta
                ON ta.courseid = c.id
             WHERE ta.sourcetemplateguid = :sourcetemplateguid";
    $course = $DB->get_record_sql($sql, array('sourcetemplateguid' => $sourcetemplateguid));
    if (empty($course)) {
        return;
    }
    $fields = $plugin->get_instance_defaults();
    if ($arlotype == \enrol_arlo_plugin::ARLO_TYPE_EVENT) {
        unset($fields['arloonlineactivity']);
        $fields['arlotype'] = $arlotype;
        $fields['arloevent'] = $sourceguid;
    }
    if ($arlotype == \enrol_arlo_plugin::ARLO_TYPE_ONLINEACTIVITY) {
        unset($fields['arloevent']);
        $fields['arlotype'] = $arlotype;
        $fields['arloonlineactivity'] = $sourceguid;
    }
    $plugin->add_instance($course, $fields);
}

function enrol_arlo_handle_update($arlotype, $eventdata) {
    global $DB;
    $plugin = new enrol_arlo_plugin();
    $platform = $plugin->get_config('platform', false);
    if (!$platform) {
        return;
    }
    $sql  = "SELECT e.*
               FROM {enrol} e 
               JOIN {enrol_arlo_instance} ai ON ai.enrolid = e.id
              WHERE ai.platform = :platform AND ai.sourceguid = :sourceguid";
    $conditions = array(
        'platform' => $platform,
        'type' => $arlotype,
        'sourceguid' => $eventdata['sourceguid']
    );
    $instance = $DB->get_record_sql($sql, $conditions);
    if (!$instance) {
        return;
    }
    $fields = $plugin->get_instance_defaults();
    if ($arlotype == \enrol_arlo_plugin::ARLO_TYPE_EVENT) {
        unset($fields['arloonlineactivity']);
        $fields['arlotype'] = $arlotype;
        $fields['arloevent'] = $eventdata['sourceguid'];
    }
    if ($arlotype == \enrol_arlo_plugin::ARLO_TYPE_ONLINEACTIVITY) {
        unset($fields['arloevent']);
        $fields['arlotype'] = $arlotype;
        $fields['arloonlineactivity'] = $eventdata['sourceguid'];
    }
    $data = (object) $fields;
    $plugin->update_instance($instance, $data);
}
