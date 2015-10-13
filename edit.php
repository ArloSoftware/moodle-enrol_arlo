<?php
//
// This file is part of Moodle - http://moodle.org/
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
 * Adds new instance of enrol_arlo to specified course
 * or edits current instance.
 *
 * @author    Troy Williams
 * @author    Corey Davis
 * @package   local_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

require_once($CFG->dirroot . '/enrol/arlo/edit_form.php');
require_once($CFG->dirroot . '/enrol/arlo/locallib.php');
require_once($CFG->dirroot . '/group/lib.php');


$courseid = required_param('courseid', PARAM_INT);
$instanceid = optional_param('id', 0, PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
require_capability('enrol/arlo:config', $context);

$PAGE->set_url('/enrol/arlo/edit.php', array('courseid' => $course->id));
$PAGE->set_pagelayout('admin');

$returnurl = new moodle_url('/enrol/instances.php', array('id' => $course->id));
if (!enrol_is_enabled('arlo')) {
    redirect($return);
}

$plugin = enrol_get_plugin('arlo');
if ($instanceid) {
    $instance = $DB->get_record('enrol',
        array('courseid' => $course->id, 'enrol' => 'arlo', 'id' => $instanceid), '*', MUST_EXIST);
} else {
    // No instance yet, we have to add new instance.
    if (! $plugin->get_newinstance_link($course->id)) {
        redirect($returnurl);
    }
    navigation_node::override_active_url(new moodle_url('/enrol/instances.php', array('id'=>$course->id)));
    $instance = new stdClass();
    $instance->id         = null;
    $instance->courseid   = $course->id;
    $instance->enrol      = 'enrol';
    $instance->customint1 = ''; // Template id.
    $instance->customint2 = '-1'; // Optional group id.
}

// Try and make the manage instances node on the navigation active.
$courseadmin = $PAGE->settingsnav->get('courseadmin');
if ($courseadmin && $courseadmin->get('users') && $courseadmin->get('users')->get('manageinstances')) {
    $courseadmin->get('users')->get('manageinstances')->make_active();
}

// Handle form.
$mform = new enrol_arlo_edit_form(null, array($instance, $plugin, $course));
if ($mform->is_cancelled()) {
    redirect($return);
} else if ($data = $mform->get_data()) {
    // Get default roleid.
    $defaultroleid = $plugin->get_config('roleid');
    // Platform name.
    $arloinstance = get_config('local_arlo', 'setting_arlo_orgname');

    // Split the event string to get Resource type and the identifier, used later on as well.
    list($type, $identifier) = enrol_arlo_break_apart_key($data->event);

    if ($type == ARLO_TYPE_EVENT) {
        $table = 'local_arlo_events';
        $field = 'eventguid';
    } else if ($type == ARLO_TYPE_ONLINEACTIVITY) {
        $table = 'local_arlo_onlineactivities';
        $field = 'onlineactivityguid';
    } else {
        print_error('Arlo resource type not supported!');
    }

    // Get Resource record either Event or Online Activity.
    $params = array($field => $identifier, 'arloinstance' => $arloinstance);
    $resource = $DB->get_record($table, $params, '*', MUST_EXIST);

    // Get associated Template.
    $params = array('templateguid' => $resource->templateguid, 'arloinstance' => $arloinstance);
    $template = $DB->get_record('local_arlo_templates', $params);

    // Setup name.
    $data->name = $resource->code . ' ' . $template->name;

    if ($data->id) {
        $instance->name         = $data->name;
        $instance->status       = $data->status;
        $instance->roleid       = $defaultroleid;
        $instance->customint2   = $data->customint2;
        $instance->customint3   = $type; // Resource type.
        $instance->customchar1  = $template->templateguid; // Template unique identifier.
        $instance->customchar2  = $arloinstance; // Platform name.
        $instance->customchar3  = $identifier; // Resource unique identifier.
        $instance->customtext1  = $data->customtext1;
        $instance->timemodified = time();
        // Create a new group for the arlo if requested.
        if ($data->customint2 == ARLO_CREATE_GROUP) {
            require_capability('moodle/course:managegroups', $context);
            $groupid = enrol_arlo_create_new_group($course->id, $table, $field, $identifier);
            $instance->customint2 = $groupid;
        }
        $DB->update_record('enrol', $instance);
    }  else {
        $newinstance = array();
        $newinstance['name']        = $data->name;
        $newinstance['status']      = $data->status;
        $newinstance['roleid']      = $defaultroleid;
        $newinstance['customint3']  = $type; // Resource type.
        $newinstance['customchar1'] = $template->templateguid; // Template unique identifier.
        $newinstance['customchar2'] = $arloinstance; // Platform name.
        $newinstance['customchar3'] = $identifier; // Resource unique identifier.
        $newinstance['customtext1'] = $data->customtext1;

        // Create a new group for the arlo if requested.
        if ($data->customint2 == ARLO_CREATE_GROUP) {
            require_capability('moodle/course:managegroups', $context);
            $groupid = enrol_arlo_create_new_group($course->id, $table, $field, $identifier);
            $newinstance['customint2'] = $groupid;
            $plugin->add_instance($course, $newinstance);
        } else {
            $plugin->add_instance($course, $newinstance);
        }
        if (!empty($data->submitbuttonnext)) {
            $returnurl = new moodle_url($PAGE->url);
            $returnurl->param('message', 'added');
        }
    }
    $trace = new null_progress_trace();
    enrol_arlo_sync($trace, $course->id);
    $trace->finished();
    redirect($returnurl);
}

$PAGE->set_title(get_string('pluginname', 'enrol_arlo'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'enrol_arlo'));
$mform->display();
echo $OUTPUT->footer();
