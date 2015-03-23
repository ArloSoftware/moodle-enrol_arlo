<?php
/**
 * @package     Arlo Moodle Integration
 * @subpackage  enrol_arlo
 * @author      Corey Davis
 * @copyright   2015 LearningWorks Ltd <http://www.learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once('edit_form.php');

$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
require_capability('enrol/arlo:config', $context);

$PAGE->set_url('/enrol/arlo/edit.php', array('courseid'=>$course->id));
$PAGE->set_pagelayout('admin');

$return = new moodle_url('/enrol/instances.php', array('id'=>$course->id));
if (!enrol_is_enabled('arlo')) {
    redirect($return);
}

$plugin = enrol_get_plugin('arlo');

if ($instances = $DB->get_records('enrol', array('courseid'=>$course->id, 'enrol'=>'arlo'), 'id ASC')) {
    $instance = array_shift($instances);
    if ($instances) {
        // Oh - we allow only one instance per course!!
        foreach ($instances as $del) {
            $plugin->delete_instance($del);
        }
    }
    // Merge these two settings to one value for the single selection element.
    if ($instance->notifyall and $instance->expirynotify) {
        $instance->expirynotify = 2;
    }
    unset($instance->notifyall);

} else {
    require_capability('moodle/course:enrolconfig', $context);
    // No instance yet, we have to add new instance.
    navigation_node::override_active_url(new moodle_url('/enrol/instances.php', array('id'=>$course->id)));
    $instance = new stdClass();
    $instance->id              = null;
    $instance->courseid        = $course->id;
}

$mform = new enrol_arlo_edit_form(null, array($instance, $plugin, $context));

if ($mform->is_cancelled()) {
    redirect($return);

} else if ($data = $mform->get_data()) {

    if ($instance->id) {
        // Update record
        $oldInstance = $instance;
        $oldCode = $instance->customchar1;	

        $instance->customchar1            = $data->customchar1;
        $instance->timemodified    = time();
        $DB->update_record('enrol', $instance);
        if ($oldCode != $data->customchar1){
        	$oldInstance->customchar1 = $oldCode;
        	$plugin->update_groups($data, $oldInstance);
        }
        
    } else {
        // Create new record
        $fields = array('customchar1'          => $data->customchar1);
        $plugin->add_instance($course, $fields);
        $plugin->update_groups($data);
    }
    redirect($return);
}

$PAGE->set_title(get_string('pluginname', 'enrol_arlo'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'enrol_arlo'));
$mform->display();
echo $OUTPUT->footer();
