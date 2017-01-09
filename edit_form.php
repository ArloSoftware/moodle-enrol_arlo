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
 * Adds new instance of enrol_arlo to specified course
 * or edits current instance.
 *
 * @author    Troy Williams
 * @author    Corey Davis
 * @package   local_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir  . '/formslib.php');
require_once($CFG->dirroot . '/enrol/arlo/lib.php');

class enrol_arlo_edit_form extends moodleform {
    public function definition() {
        global $DB;
        $mform = $this->_form;

        list($instance, $plugin, $course) = $this->_customdata;
        $context = context_course::instance($course->id);

        $enrol = enrol_get_plugin('arlo');

        $events = array();
        $onlineactivities = array();

        $groups = array(0 => get_string('none'));
        if (has_capability('moodle/course:managegroups', $context)) {
            $groups[ARLO_CREATE_GROUP] = get_string('creategroup', 'enrol_arlo');
        }

        foreach (groups_get_all_groups($course->id) as $group) {
            $groups[$group->id] = format_string($group->name, true, array('context' => $context));
        }

        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
            ENROL_INSTANCE_DISABLED => get_string('no'));
        $mform->addElement('select', 'status', get_string('status', 'enrol_arlo'), $options);

        $mform->addElement('duration',
            'enrolperiod',
            get_string('enrolperiod', 'enrol_self'),
            array('optional' => true, 'defaultunit' => 86400));
        $mform->addHelpButton('enrolperiod', 'enrolperiod', 'enrol_self');

        $options = array(0 => get_string('no'), 1 => get_string('yes'));
        $mform->addElement('select', 'expirynotify', get_string('expirynotify', 'enrol_arlo'), $options);
        $mform->addHelpButton('expirynotify', 'expirynotify', 'enrol_arlo');

        // Build array of keys of current Arlo instances to hide later.
        $currentinstancekeys = array();
        $params = array('courseid' => $course->id, 'enrol' => 'arlo');
        $arloenrolinstances = $DB->get_records('enrol', $params, '', 'id, customint3, customchar3');
        foreach ($arloenrolinstances as $arloenrolinstance) {
            $key = enrol_arlo_make_select_key($arloenrolinstance->customint3, $arloenrolinstance->customchar3);
            $currentinstancekeys[] = $key;
        }
        // Build event options group.
        foreach (\local_arlo\arlo::get_active_events() as $event) {
            $key = enrol_arlo_make_select_key(ARLO_TYPE_EVENT, $event->eventguid);
            if (! $instance->id) {
                if (!in_array($key, $currentinstancekeys)) {
                    $events[$key] = $event->code . ' ' . $event->name;
                }
            } else {
                $events[$key] = $event->code . ' ' . $event->name;
            }
        }
        // Build online activity options group.
        foreach (\local_arlo\arlo::get_active_online_activities() as $onlineactivity) {
            $key = enrol_arlo_make_select_key(ARLO_TYPE_ONLINEACTIVITY, $onlineactivity->onlineactivityguid);
            if (! $instance->id) {
                if (!in_array($key, $currentinstancekeys)) {
                    $onlineactivities[$key] = $onlineactivity->code . ' ' . $onlineactivity->name;
                }
            } else {
                $onlineactivities[$key] = $onlineactivity->code . ' ' . $onlineactivity->name;
            }
        }

        // ... @TODO build better selector = ajax for bigger installs.
        if ($instance->id) {
            // Platform name.
            $arloinstance = \local_arlo\arlo::get_platform_name();

            // Get Resource type and id.
            $type = $instance->customint3;
            $identifier = $instance->customchar3;
            if ($type == ARLO_TYPE_EVENT) {
                $table = 'local_arlo_events';
                $field = 'eventguid';
            } else if ($type == ARLO_TYPE_ONLINEACTIVITY) {
                $table = 'local_arlo_onlineactivities';
                $field = 'onlineactivityguid';
            }

            if (empty($onlineactivities)) {
                $onlineactivities = ['' => ''];
            }
            if (empty($events)) {
                $events = ['' => ''];
            }

            // Get Resource record either Event or Online Activity.
            $params = array($field => $identifier, 'arloinstance' => $arloinstance);
            $resource = $DB->get_record($table, $params, '*', MUST_EXIST);
            if (! $resource) {
                $options = array(get_string('error') => array());
            } else {
                $options = array(get_string('events', 'enrol_arlo') => $events,
                    get_string('onlineactivities', 'enrol_arlo') => $onlineactivities);

            }

            $key = enrol_arlo_make_select_key($type, $identifier);
            $mform->addElement('selectgroups', 'event', get_string('event', 'enrol_arlo'), $options);
            $mform->setConstant('event', $key);
            $mform->hardFreeze('event', $key);

            $mform->addElement('select', 'customint2', get_string('assignedgroup', 'enrol_arlo'), $groups);
            if ($instance->customint2 != '-1' or $instance->customint2 == '0') {
                $mform->setConstant('customint2', $instance->customint2);
                $mform->hardFreeze('customint2', $instance->customint2);
            }

        } else {
            // Remove active instances from options.
            $activeinstances = $DB->get_records('enrol', array('enrol' => 'arlo'));
            foreach ($activeinstances as $activeinstance) {
                // Unset any active events being used as enrolment instance.
                if ($activeinstance->customint3 == ARLO_TYPE_EVENT) {
                    $key = enrol_arlo_make_select_key(ARLO_TYPE_EVENT, $activeinstance->customchar3);
                    unset($events[$key]);
                }
                // Unset any active online activities being used as enrolment instance.
                if ($activeinstance->customint3 == ARLO_TYPE_ONLINEACTIVITY) {
                    $key = enrol_arlo_make_select_key(ARLO_TYPE_ONLINEACTIVITY, $activeinstance->customchar3);
                    unset($onlineactivities[$key]);
                }
            }

            $options = array(get_string('events', 'enrol_arlo') => $events,
                             get_string('onlineactivities', 'enrol_arlo') => $onlineactivities);

            $mform->addElement('selectgroups', 'event', get_string('event', 'enrol_arlo'), $options);
            $mform->addElement('hidden', 'customint2');
            $mform->setType('customint2', PARAM_INT);

        }

        $mform->addElement('advcheckbox', 'customint8', get_string('sendcoursewelcomemessage', 'enrol_arlo'));
        $mform->addHelpButton('customint8', 'sendcoursewelcomemessage', 'enrol_arlo');
        $mform->setDefault('customint8', 1);

        $mform->addElement('textarea', 'customtext1',
            get_string('customwelcomemessage', 'enrol_arlo'),
            array('cols' => '60', 'rows' => '8'));
        $mform->addHelpButton('customtext1', 'customwelcomemessage', 'enrol_arlo');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons(true, ($instance->id ? null : get_string('addinstance', 'enrol')));

        $this->set_data($instance);
    }
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
        return $errors;
    }
}
