<?php
/**
 * @package     Arlo Moodle Integration
 * @subpackage  enrol_arlo
 * @author      Corey Davis
 * @copyright   2015 LearningWorks Ltd <http://www.learningworks.co.nz>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class enrol_arlo_plugin extends enrol_plugin {
    public function __construct() {
        global $CFG;
        $this->load_config();
    }
    protected function email_welcome_message($instance, $user) {
        global $CFG, $DB;
        $course = $DB->get_record('course', array('id'=>$instance->courseid), '*', MUST_EXIST);
        $context = context_course::instance($course->id);

        $a = new stdClass();
        $a->coursename = format_string($course->fullname, true, array('context'=>$context));
        $a->courseurl = "$CFG->wwwroot/course/view.php?id=$course->id";

        $messagetext = get_string('welcometocoursetext', 'enrol_arlo', $a);
        $messagehtml = text_to_html($messagetext, null, false, true);

        $subject = get_string('welcometocourse', 'enrol_arlo', format_string($course->fullname, true, array('context'=>$context)));

        $contact = core_user::get_support_user();
        // Directly emailing welcome message rather than using messaging.
        email_to_user($user, $contact, $subject, $messagetext, $messagehtml);
    }
    public function allow_enrol(stdClass $instance) {
        // Users with enrol cap may unenrol other users manually manually.
        return true;
    }
    public function allow_unenrol(stdClass $instance) {
        // Users with unenrol cap may unenrol other users manually manually.
        return true;
    }
    public function allow_manage(stdClass $instance) {
        // Users with manage cap may tweak period and status.
        return true;
    }
    public function get_newinstance_link($courseid) {
        global $DB;

        if ($DB->record_exists('enrol', array('courseid'=>$courseid, 'enrol'=>'arlo'))) {
            // only one instance allowed, sorry
            return NULL;
        }

        return new moodle_url('/enrol/arlo/edit.php', array('courseid'=>$courseid));
    }
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'arlo') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context =  context_course::instance($instance->courseid);

        $icons = array();

        if (has_capability('enrol/manual:config', $context)) {
            $editlink = new moodle_url("/enrol/arlo/edit.php", array('courseid'=>$instance->courseid, 'id'=>$instance->id));
            $icons[] = $OUTPUT->action_icon($editlink, new pix_icon('t/edit', get_string('edit'), 'core', array('class' => 'iconsmall')));
        }
        
        return $icons;
    }
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/arlo:manage', $context);
    }
    public function get_instance_name($instance) {
        global $DB;

        if (empty($instance->name)) {
            if (!empty($instance->roleid) and $role = $DB->get_record('role', array('id'=>$instance->roleid))) {
                $role = ' (' . role_get_name($role, context_course::instance($instance->courseid, IGNORE_MISSING)) . ')';
            } else {
                $role = '';
            }
            $enrol = $this->get_name();
            return get_string('pluginname', 'enrol_'.$enrol) . " (" . $instance->customchar1 . ")";
        } else {
            return format_string($instance->name);
        }
    }
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/arlo:manage', $context);
    } 
    public function check_enrolment($instance, $user){
        global $DB;
        if ($enrolments = $DB->get_record("user_enrolments", array('userid' => $user->id, 'enrolid' => $instance->id))){
            return true;
        }else{
            return false;
        }
    } 
    private function get_group_instance($groupname, $courseid){
        global $DB;
        if ($group = $DB->get_record('groups', array('name' => $groupname, 'courseid' => $courseid))){
            return $group;
        }else{
            return false;
        }
    }
    private function get_template($templateCode){
    	global $DB;
    	return $DB->get_record("local_arlo_templates", array('code' => $templateCode));
    }
    public function getTemplateCodes($instance){
		global $DB;
		$templates = $DB->get_records('local_arlo_templates');
		$templateCodes = array();
		foreach ($templates as $key => $value) {
			// Only allow templates not already assigned to courses to be added
			if (!$this->get_enrol_instance($value->code)){
				$templateCodes[$value->code] = $value->code;
			}
		}
		if (count($templateCodes) == 0){
			return null;
		}
		return $templateCodes;
	}
    // Function to loop though all events to ensure a matching group exists
    public function update_groups($instance, $oldInstance = null){
    	global $DB, $CFG;
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
    		// We need to blank all registration's timeSynced inorder to get the cron to update these
    		$registrations = $DB->get_records("local_arlo_registrations", array('arloeventid' =>  $event->arloeventid));
    		foreach ($registrations as $key => $value) {
    			$value->lastsynced = 0;
    			$DB->update_record("local_arlo_registrations", $value);
    		}
    	}
    }
    // Function to get the group from eventcode, group will be created if it doesnt exist
    private function get_group($instance, $eventcode){
    	global $DB, $CFG;
    	// Create Group if group doesnt exist within current course
        if (!$group = $this->get_group_instance($eventcode, $instance->courseid)){
        	require_once($CFG->dirroot.'/group/lib.php');
            $group = new stdClass();
            $group->name = $eventcode;
            $group->idnumber = $eventcode;
            $group->courseid = $instance->courseid;
            $group->id = groups_create_group($group);
            $group = $this->get_group_instance($eventcode, $instance->courseid);
        }
        return $group;
    }

    public function create_Enrolment($registration, $instance, $event, $user){
    	global $DB, $CFG;
        //var_dump($instance->courseid);
    	require_once($CFG->dirroot.'/group/lib.php');
		$group = $this->get_group($instance, $event->code);
		if ($registration->status != "Approved"){
			$this->enrol_user($instance, $user->id, $instance->roleid, $event->starttime, $event->finishtime, 0);
		}else{
			$this->enrol_user($instance, $user->id, $instance->roleid, $event->starttime, $event->finishtime, 1);
		}
		groups_add_member($group, $user->id);
		$this->email_welcome_message($instance, $user);
    }
    public function update_Enrolment($registration, $instance, $event, $user){
    	global $DB;
        //var_dump($instance->courseid);
		if ($registration->status != "Approved"){
			$this->update_user_enrol($instance, $user->id, 0, $event->starttime, $event->finishtime);
		}else{
			$this->update_user_enrol($instance, $user->id, 1, $event->starttime, $event->finishtime);
		}
    }
    public function get_enrol_instance($templateID){
        global $DB;
        return $DB->get_record('enrol',array('enrol'=>"arlo",'customchar1'=>$templateID));
    }
}
