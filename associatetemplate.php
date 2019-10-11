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

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/enrol/arlo/lib.php');
require_once($CFG->dirroot . '/enrol/arlo/locallib.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
require_capability('enrol/arlo:config', $context);

$pageurl = new moodle_url('/enrol/arlo/associatetemplate.php', array('id' => $course->id));
$returnurl = new \moodle_url('/enrol/instances.php', array('id' => $course->id));
$PAGE->set_url($pageurl);
$PAGE->set_heading($course->fullname);

$form = new enrol_arlo\form\associatetemplate();
if ($form->is_cancelled()) {
    redirect($returnurl);
}
if ($validateddata = $form->get_data()) {
    if (isset($validateddata->submitbutton) || isset($validateddata->submitbuttonsynchronize)) {
        enrol_arlo_associate_all($course, $validateddata->sourcetemplateguid);
    }
    if (isset($validateddata->submitbuttonremove)) {
        enrol_arlo_unassociate_all($course, $validateddata->sourcetemplateguid);
    }
    redirect($returnurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('associatetemplatewithcourse', 'enrol_arlo'));
$association = $DB->get_record('enrol_arlo_templateassociate', array('courseid' => $course->id));
if (!empty($association->sourcetemplateguid)) {
    $a = '';
    $instances = enrol_arlo_get_associated_instances($course, $association->sourcetemplateguid);
    if ($instances) {
        foreach ($instances as $instance) {
            $a .= '<span>' . $instance->name . '</span><br>';
        }
        echo $OUTPUT->box(get_string('removetemplatedanger', 'enrol_arlo', $a), 'generalbox');
    }
} else {
    echo $OUTPUT->box(get_string('associatetemplatedanger', 'enrol_arlo'), 'generalbox');
}
$form->display();
echo $OUTPUT->footer();
