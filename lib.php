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
 * Arlo enrolment plugin.
 *
 * @author      Troy Williams
 * @author      Corey Davis
 * @package     local_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright   2015 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * ARLO_CREATE_GROUP constant for automatically creating a group matched to enrolment instance.
 */
define('ARLO_CREATE_GROUP', -1);

define('ARLO_TYPE_EVENT', 0);

define('ARLO_TYPE_ONLINEACTIVITY', 1);


class enrol_arlo_plugin extends enrol_plugin {
    /**
     * Does this plugin allow manual changes in user_enrolments table?
     *
     * All plugins allowing this must implement 'enrol/xxx:manage' capability
     *
     * @param stdClass $instance course enrol instance
     * @return bool - true means it is possible to change enrol period and status in user_enrolments table
     */
    public function allow_manage(stdClass $instance) {
        return true;
    }
    /**
     * Does this plugin allow manual unenrolment of a specific user?
     * Yes, but only if user suspended...
     *
     * @param stdClass $instance course enrol instance
     * @param stdClass $ue record from user_enrolments table
     *
     * @return bool - true means user with 'enrol/xxx:unenrol' may unenrol this user,
     * false means nobody may touch this user enrolment
     */
    public function allow_unenrol_user(stdClass $instance, stdClass $ue) {
        if ($ue->status == ENROL_USER_SUSPENDED) {
            return true;
        }
        return false;
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param object $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/arlo:config', $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/arlo:config', $context);
    }

    /**
     * Send welcome email to specified user.
     *
     * @param stdClass $instance
     * @param stdClass $user user record
     * @return void
     */
    protected function email_welcome_message($instance, $user) {
        global $CFG, $DB;

        $course = $DB->get_record('course', array('id' => $instance->courseid), '*', MUST_EXIST);
        $context = context_course::instance($course->id);

        $a = new stdClass();
        $a->coursename = format_string($course->fullname, true, array('context' => $context));
        $a->courseurl = "$CFG->wwwroot/course/view.php?id=$course->id";

        if (trim($instance->customtext1) !== '') {
            $message = $instance->customtext1;
            $key = array('{$a->coursename}', '{$a->courseurl}', '{$a->fullname}', '{$a->email}');
            $value = array($a->coursename, $a->courseurl, fullname($user), $user->email);
            $message = str_replace($key, $value, $message);
            if (strpos($message, '<') === false) {
                // Plain text only.
                $messagetext = $message;
                $messagehtml = text_to_html($messagetext, null, false, true);
            } else {
                // This is most probably the tag/newline soup known as FORMAT_MOODLE.
                $messagehtml = format_text($message, FORMAT_MOODLE,
                    array('context' => $context, 'para' => false, 'newlines' => true, 'filter' => true));
                $messagetext = html_to_text($messagehtml);
            }
        } else {
            $messagetext = get_string('welcometocoursetext', 'enrol_arlo', $a);
            $messagehtml = text_to_html($messagetext, null, false, true);
        }

        $subject = get_string('welcometocourse', 'enrol_arlo',
            format_string($course->fullname, true, array('context' => $context)));

        $rusers = array();
        if (!empty($CFG->coursecontact)) {
            $croles = explode(',', $CFG->coursecontact);
            list($sort, $sortparams) = users_order_by_sql('u');
            // We only use the first user.
            $i = 0;
            do {
                $rusers = get_role_users($croles[$i], $context, true, '',
                    'r.sortorder ASC, ' . $sort, null, '', '', '', '', $sortparams);
                $i++;
            } while (empty($rusers) && !empty($croles[$i]));
        }
        if ($rusers) {
            $contact = reset($rusers);
        } else {
            $contact = core_user::get_support_user();
        }

        // Directly emailing welcome message rather than using messaging.
        email_to_user($user, $contact, $subject, $messagetext, $messagehtml);
    }

    /**
     * Returns edit icons for the page with list of instances.
     *
     * @param stdClass $instance
     * @return array
     * @throws coding_exception
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'arlo') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context =  context_course::instance($instance->courseid);

        $icons = array();

        if (has_capability('enrol/arlo:config', $context)) {
            $editlink = new moodle_url("/enrol/arlo/edit.php",
                array('courseid' => $instance->courseid, 'id' => $instance->id));
            $icons[] = $OUTPUT->action_icon($editlink,
                new pix_icon('t/edit', get_string('edit'), 'core', array('class' => 'iconsmall')));
        }
        
        return $icons;
    }
    /**
     * Returns Arlo Code for Event or Online Activity instance.
     *
     * @param object $instance
     * @return string
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function get_instance_name($instance) {
        global $DB;

        $enrol = $this->get_name();

        if (empty($instance->name)) {
            throw new moodle_exception('Arlo code does not exist.');
        }

        if (!empty($instance->roleid) and $role = $DB->get_record('role', array('id' => $instance->roleid))) {
            $role = ' (' . role_get_name($role, context_course::instance($instance->courseid, IGNORE_MISSING)) . ')';
        } else {
            $role = '';
        }

        return get_string('pluginname', 'enrol_' . $enrol) .  ' : ' . format_string($instance->name) . $role;
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     *
     * @param int $courseid
     * @return moodle_url page url
     */
    public function get_newinstance_link($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);
        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/arlo:config', $context)) {
            return null;
        }
        // Multiple instances supported.
        return new moodle_url('/enrol/arlo/edit.php', array('courseid' => $courseid));
    }

    public function check_enrolment($instance, $user){
        global $DB;
        if ($enrolments = $DB->get_record("user_enrolments", array('userid' => $user->id, 'enrolid' => $instance->id))){
            return true;
        }else{
            return false;
        }
    } 
    private function get_template($templateCode){
    	global $DB;
    	return $DB->get_record("local_arlo_templates", array('code' => $templateCode));
    }
    // This function gets the list of code's for the enrolment page
    public function getTemplateCodes($instance){
		global $DB;
        $params = array('status' => 'Active');
		$templates = $DB->get_records('local_arlo_templates', $params);
		$templateCodes = array();
		foreach ($templates as $key => $value) {
            // Scan Events/OnlineActivities to make sure this has an active item
            $events = $DB->get_records("local_arlo_events", array('arlotemplateid' => $value->arlotemplateid ));
            $OAevents = $DB->get_records("local_arlo_onlineactivities", array('arlotemplateid' => $value->arlotemplateid ));
			// Only allow templates not already assigned to courses to be added
			if (!$this->get_enrol_instance($value->code)){
				$templateCodes[$value->code] = $value->code;
			}
		}
		if (count($templateCodes) == 0){
			return null;
		}
        ksort($templateCodes);
		return $templateCodes;
	}
    // Function to loop though all events to ensure a matching group exists
    public function update_groups($instance, $oldInstance = null){
    	global $DB, $CFG;
         // Is this a coaching course?
        if (strpos($instance->customchar1,'_coach') !== false) {

        }else{
            $template = $this->get_template($instance->customchar1); 
            if ($oldInstance){
                // Remove group's assoiated with old method
                $oldTemplate = $this->get_template($oldInstance->customchar1); 
                $oldEvents = $DB->get_records("local_arlo_events", array('arlotemplateid' => $oldTemplate->arlotemplateid));
                require_once($CFG->dirroot.'/group/lib.php');
                foreach ($oldEvents as $key => $event) {
                    $oldGroup = $this->get_group($instance, $event->code);
                    groups_delete_group($oldGroup->id);
                }
                $groups = $DB->get_records('groups', array('courseid' => $oldInstance->courseid));
            }
           
            $events = $DB->get_records("local_arlo_events", array('arlotemplateid' => $template->arlotemplateid));
            foreach ($events as $key => $event) {
                // Creates group if it doesnt exist
                $group = $this->get_group($instance, $event->code);
                // // We need to blank all registration's timeSynced inorder to get the cron to update these
                if ($group->new){
                    $registrations = $DB->get_records("local_arlo_registrations", array('arloeventid' =>  $event->arloeventid));
                    foreach ($registrations as $key => $value) {
                        $value->lastsynced = 0;
                        $DB->update_record("local_arlo_registrations", $value);
                    }
                }
            }
            $OnlineActivities = $DB->get_records("local_arlo_onlineactivities", array('arlotemplateid' => $template->arlotemplateid));
            foreach ($OnlineActivities as $key => $OnlineActivity) {
                // Creates group if it doesnt exist
                $group = $this->get_group($instance, $OnlineActivity->code);
                if ($group->new){
                    $registrations = $DB->get_records("local_arlo_registrations", array('onlineactivityid' =>  $OnlineActivity->onlineactivityid));
                    foreach ($registrations as $key => $value) {
                        $value->lastsynced = 0;
                        $DB->update_record("local_arlo_registrations", $value);
                    }
                }
            }
        }
    }
    // Function to get the group from eventcode, group will be created if it doesnt exist
    private function get_group($instance, $eventcode){
    	global $DB, $CFG;
        $groupname = $eventcode;
    	// Create Group if group doesnt exist within current course
        if (!$group = $DB->get_record('groups', array('name' => $groupname, 'courseid' => $instance->courseid))){
        	require_once($CFG->dirroot.'/group/lib.php');
            
            $group = new stdClass();
            $group->name = $groupname;
            $group->idnumber = $groupname;
            $group->courseid = $instance->courseid;
            $group->id = groups_create_group($group);
            $group = $DB->get_record('groups', array('name' => $groupname, 'courseid' => $instance->courseid));
            $group->new = true;
            // Create and assign the grouping
            if (!$grouping = $DB->get_record('groupings', array('name' => $eventcode, 'courseid' => $instance->courseid))){
                require_once($CFG->dirroot.'/group/lib.php');
                $grouping = new stdClass();
                $grouping->name = $eventcode;
                $grouping->idnumber = $eventcode;
                $grouping->courseid = $instance->courseid;
                $grouping->id = groups_create_grouping($grouping);
                $grouping = $DB->get_record('groupings', array('name' => $eventcode, 'courseid' => $instance->courseid));
            }
            groups_assign_grouping($grouping->id, $group->id);
            return $group; 
        }
        $group->new = false;
        return $group;
    }

    public function create_Enrolment($registration, $instance, $event, $user){
    	global $DB, $CFG;
    	require_once($CFG->dirroot.'/group/lib.php');
		$group = $this->get_group($instance, $event->code);
        if ($registration->status != "Cancelled"){
			$this->enrol_user($instance, $user->id, $instance->roleid, 0, 0, 0);
		}else{
			//$this->enrol_user($instance, $user->id, $instance->roleid, $event->starttime, 0, 1);
		}
		groups_add_member($group, $user->id);
		$this->email_welcome_message($instance, $user);

    }
    public function update_Enrolment($registration, $instance, $event, $user){
    	global $DB;
		if ($registration->status != "Cancelled"){
			$this->update_user_enrol($instance, $user->id, 0, 0, 0);
		}else{
			$this->unenrol_user($instance, $user->id);
		}
    }
    public function get_enrol_instance($templateID){
        global $DB;
        return $DB->get_record('enrol',array('enrol'=>"arlo",'customchar1'=>$templateID));
    }
}

/**
 * Prevent removal of enrol roles.
 *
 * @param int $itemid
 * @param int $groupid
 * @param int $userid
 * @return bool
*/
function enrol_arlo_allow_group_member_remove($itemid, $groupid, $userid) {
    return false;
}

/**
 * Create new group or returns the existing group id based on matching
 * code to group idnumber.
 *
 * @param $courseid - course where to create group.
 * @param $table - resource table local_arlo_events or local_arlo_onlineactivities.
 * @param $field - guid field eventguid or onlineactivityguid.
 * @param $identifier - resource guid.
 * @return id
 * @throws coding_exception
 * @throws moodle_exception
 */
function enrol_arlo_create_new_group($courseid, $table, $field, $identifier) {
    global $DB;

    $code = $DB->get_field($table, 'code', array($field => $identifier), MUST_EXIST);

    // Already have group, return id.
    $exists = $DB->get_record('groups', array('idnumber' => $code, 'courseid' => $courseid));
    if ($exists) {
        return $exists->id;
    }

    $a = new stdClass();
    $a->name = $code;
    $a->increment = '';
    $groupname = trim(get_string('defaultgroupnametext', 'enrol_arlo', $a));

    // Create a new group for the for event or online activity.
    $groupdata = new stdClass();
    $groupdata->courseid = $courseid;
    $groupdata->name = $groupname;
    $groupdata->idnumber = $code;

    $groupid = groups_create_group($groupdata);

    return $groupid;
}

/**
 * Break key string which is combination of Arlo resource type and
 * guid identifier into their separate parts.
 *
 * @param $key
 * @return array
 */
function enrol_arlo_break_apart_key($key) {
    $type = '';
    $guid = '';
    $pattern = '/(\d.*):(.*)/'; // Break apart (type) : (guid).
    preg_match($pattern, $key, $matches);
    if (!empty($matches)) {
        $type = $matches[1];
        $guid = $matches[2];
    }
    return array($type, $guid);
}

/**
 * Make key based on Arlo resource type ans guid identifier.
 *
 * @param $type
 * @param $guid
 * @return string
 */
function enrol_arlo_make_select_key($type, $guid) {
    return $type . ':' . $guid;
}
