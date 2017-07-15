<?php

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
    if (isset($validateddata->submitbuttonremove)) {
        enrol_arlo_unassociate_all($course, $validateddata->sourcetemplateguid);
    } else {
        enrol_arlo_associate_all($course, $validateddata->sourcetemplateguid);
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
        $message = get_string('warningnotice', 'enrol_arlo', $a);
        echo $OUTPUT->box($message, 'generalbox');
    }
} else {
    echo $OUTPUT->box(get_string('linktemplatenotice', 'enrol_arlo'), 'generalbox');
}
$form->display();
echo $OUTPUT->footer();
