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

use core_privacy\local\metadata\collection;
use core_privacy\local\request\writer;
use enrol_arlo\privacy\provider;

defined('MOODLE_INTERNAL') || die();


/**
 *
 * @package   enrol_arlo
 * @copyright 2019 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class enrol_arlo_privacy_provider_testcase extends \core_privacy\tests\provider_testcase {
    
    public function setUp() {
        global $CFG, $DB;
        
        require_once($CFG->dirroot . '/enrol/arlo/lib.php');
        /** @var enrol_arlo_generator $plugingenerator */
        $plugingenerator = $this->getDataGenerator()->get_plugin_generator('enrol_arlo');
        $this->resetAfterTest();
    
        $user1 = $this->getDataGenerator()->create_user();
        $contact1 = $plugingenerator->create_contact($user1);
        $contact1->set('userid', $user1->id);
        $contact1->save();
        
        $template1 = $this->getDataGenerator()->create_event_template();
        $event1 = $this->getDataGenerator()->create_event($template1);
    
        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $manualinstance1 = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $manualplugin = enrol_get_plugin('manual');
        $manualplugin->enrol_user($manualinstance1, $user1->id, $studentrole->id);
        $registration1 = $this->getDataGenerator()->create_registration($event1, $contact1, $manualinstance1);
    }
    
    
    public function test_get_contexts_for_userid() {
    
    }
}