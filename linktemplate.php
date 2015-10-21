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

require('../../config.php');
require_once($CFG->dirroot . '/enrol/arlo/linktemplate_form.php');
require_once($CFG->dirroot . '/enrol/arlo/locallib.php');

$courseid   = required_param('courseid', PARAM_INT);
$linkid     = optional_param('id', 0, PARAM_INT);

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
require_capability('enrol/arlo:config', $context);

$returnurl = new moodle_url('/enrol/instances.php', array('id' => $course->id));
$PAGE->set_url('/enrol/arlo/linktemplate.php', array('courseid' => $course->id));

$link = $DB->get_record('enrol_arlo_templatelink', array('courseid' => $course->id));
if (! $link) {
    $link = new stdClass();
    $link->id         = 0;
    $link->courseid   = $course->id;
}

$mform = new enrol_arlo_linktemplate_form(null, array($link, $course));
if ($mform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $mform->get_data()) {
    $plugin = enrol_get_plugin('arlo');
    $trace = new \null_progress_trace();
    if ($data->id) {
        if (isset($data->submitbuttonremove)) {
            // Remove associated instances and mapping.
            enrol_arlo_course_remove_all_instances($trace, $data->courseid);
        }
    } else {
        $link = new \stdClass();
        $link->courseid = $data->courseid;
        $link->templateguid = $data->template;
        $link->modified = time();
        $link->id = $DB->insert_record('enrol_arlo_templatelink', $link);
    }
    // Can we sync now?
    $syncinstanceonadd = $plugin->get_config('syncinstanceonadd');
    if ($syncinstanceonadd) {
        enrol_arlo_sync($trace, $course->id);
    }
    $trace->finished();
    redirect($returnurl);
}
$PAGE->set_title(get_string('linktemplatetocourse', 'enrol_arlo'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('linktemplatetocourse', 'enrol_arlo'));
if ($link->id) {
    $a = '';
    $rs = $DB->get_records('enrol', array('enrol' => 'arlo', 'courseid' => $course->id), '', 'id, name');
    if ($rs) {
        foreach ($rs as $instance) {
            $a .= '<span>' . $instance->name . '</span><br>';
        }
        $message = get_string('warningnotice', 'enrol_arlo', $a);
        echo $OUTPUT->box($message, 'generalbox');
    }
} else {
    echo $OUTPUT->box(get_string('linktemplatenotice', 'enrol_arlo'), 'generalbox');
}
$mform->display();
echo $OUTPUT->footer();

