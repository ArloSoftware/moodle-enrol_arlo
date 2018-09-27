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
 * Contact merge tests
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @category  phpunit
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use enrol_arlo\local\persistent\contact_persistent;
use enrol_arlo\local\persistent\user_persistent;
use enrol_arlo\local\handler\contact_merge_requests_handler;

class enrol_arlo_contact_merge_requests_testcase extends advanced_testcase {

    public function test_no_merge_requests() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/enrol/arlo/lib.php');
        /** @var enrol_arlo_generator $generator */
        $plugingenerator = $this->getDataGenerator()->get_plugin_generator('enrol_arlo');
        $this->resetAfterTest();

        $destinationinfo = new stdClass();
        $destinationinfo->firstname = 'Destination';
        $destinationinfo->lastname = 'Contact';
        $destinationinfo->email = 'destination@example.com';

        $destinationcontact = $plugingenerator->create_contact($destinationinfo);

        $handler = new contact_merge_requests_handler($destinationcontact);
        $result = $handler->apply_all_merge_requests();

        $this->assertEquals(true,  $result);
    }

    public function test_both_source_and_destination_have_enrolments() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/enrol/arlo/lib.php');
        /** @var enrol_arlo_generator $plugingenerator */
        $plugingenerator = $this->getDataGenerator()->get_plugin_generator('enrol_arlo');
        $this->resetAfterTest();

        $sourceinfo = new stdClass();
        $sourceinfo->firstname = 'Source';
        $sourceinfo->lastname = 'Contact';
        $sourceinfo->email = 'source@example.com';

        $sourcecontact = $plugingenerator->create_contact($sourceinfo);

        $sourceinfo->lastname = 'User';
        $sourceuser = $this->getDataGenerator()->create_user($sourceinfo);

        // Associate contact and user.
        $sourcecontact->set('userid', $sourceuser->id);
        $sourcecontact->update();

        $destinationinfo = new stdClass();
        $destinationinfo->firstname = 'Destination';
        $destinationinfo->lastname = 'Contact';
        $destinationinfo->email = 'destination@example.com';

        $destinationcontact = $plugingenerator->create_contact($destinationinfo);

        $destinationinfo->lastname = 'User';
        $destinationuser = $this->getDataGenerator()->create_user($destinationinfo);

        // Associate contact and user.
        $destinationcontact->set('userid', $destinationuser->id);
        $destinationcontact->update();

        // Create a contact merge request.
        $contactmergerequest = $plugingenerator->create_contact_merge_request($sourcecontact, $destinationcontact);

        // Set up course enrolments.
        $manualplugin = enrol_get_plugin('manual');

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->assertNotEmpty($studentrole);

        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $manualinstance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);

        $manualplugin->enrol_user($manualinstance, $sourceuser->id, $studentrole->id);
        $manualplugin->enrol_user($manualinstance, $destinationuser->id, $studentrole->id);

        $handler = new contact_merge_requests_handler($destinationcontact);
        $result = $handler->apply_all_merge_requests();

        $this->assertEquals(false,  $result);
    }

    public function test_source_has_user_and_destination_has_enrolments() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/enrol/arlo/lib.php');
        /** @var enrol_arlo_generator $plugingenerator */
        $plugingenerator = $this->getDataGenerator()->get_plugin_generator('enrol_arlo');
        $this->resetAfterTest();

        $sourceinfo = new stdClass();
        $sourceinfo->firstname = 'Source';
        $sourceinfo->lastname = 'Contact';
        $sourceinfo->email = 'source@example.com';

        $sourcecontact = $plugingenerator->create_contact($sourceinfo);

        $sourceinfo->lastname = 'User';
        $sourceuser = $this->getDataGenerator()->create_user($sourceinfo);

        // Associate contact and user.
        $sourcecontact->set('userid', $sourceuser->id);
        $sourcecontact->update();

        $destinationinfo = new stdClass();
        $destinationinfo->firstname = 'Destination';
        $destinationinfo->lastname = 'Contact';
        $destinationinfo->email = 'destination@example.com';

        $destinationcontact = $plugingenerator->create_contact($destinationinfo);

        $destinationinfo->lastname = 'User';
        $destinationuser = $this->getDataGenerator()->create_user($destinationinfo);

        // Associate contact and user.
        $destinationcontact->set('userid', $destinationuser->id);
        $destinationcontact->update();

        // Create a contact merge request.
        $contactmergerequest = $plugingenerator->create_contact_merge_request($sourcecontact, $destinationcontact);

        // Set up course enrolments.
        $manualplugin = enrol_get_plugin('manual');

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->assertNotEmpty($studentrole);

        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $manualinstance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);

        $manualplugin->enrol_user($manualinstance, $destinationuser->id, $studentrole->id);

        $handler = new contact_merge_requests_handler($destinationcontact);
        $result = $handler->apply_all_merge_requests();

        $sourceuser = new user_persistent($sourceuser->id);
        $sourcecontact = contact_persistent::get_record(['userid' => $sourceuser->get('id')]);
        $contactmergerequest->read();

        $this->assertEquals(1, $sourceuser->get('suspended'));
        $this->assertEquals(false, $sourcecontact);
        $this->assertEquals(0, $contactmergerequest->get('active'));
        $this->assertEquals(true,  $result);
    }

    public function test_source_has_enrolments_and_destination_has_user() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/enrol/arlo/lib.php');
        /** @var enrol_arlo_generator $plugingenerator */
        $plugingenerator = $this->getDataGenerator()->get_plugin_generator('enrol_arlo');
        $this->resetAfterTest();

        $sourceinfo = new stdClass();
        $sourceinfo->firstname = 'Source';
        $sourceinfo->lastname = 'Contact';
        $sourceinfo->email = 'source@example.com';

        $sourcecontact = $plugingenerator->create_contact($sourceinfo);

        $sourceinfo->lastname = 'User';
        $sourceuser = $this->getDataGenerator()->create_user($sourceinfo);

        // Associate contact and user.
        $sourcecontact->set('userid', $sourceuser->id);
        $sourcecontact->update();

        $destinationinfo = new stdClass();
        $destinationinfo->firstname = 'Destination';
        $destinationinfo->lastname = 'Contact';
        $destinationinfo->email = 'destination@example.com';

        $destinationcontact = $plugingenerator->create_contact($destinationinfo);

        $destinationinfo->lastname = 'User';
        $destinationuser = $this->getDataGenerator()->create_user($destinationinfo);

        // Associate contact and user.
        $destinationcontact->set('userid', $destinationuser->id);
        $destinationcontact->update();

        // Create a contact merge request.
        $contactmergerequest = $plugingenerator->create_contact_merge_request($sourcecontact, $destinationcontact);

        // Set up course enrolments.
        $manualplugin = enrol_get_plugin('manual');

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->assertNotEmpty($studentrole);

        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $manualinstance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);

        $manualplugin->enrol_user($manualinstance, $sourceuser->id, $studentrole->id);

        $handler = new contact_merge_requests_handler($destinationcontact);
        $result = $handler->apply_all_merge_requests();

        $sourcecontact = contact_persistent::get_record(['id' => $sourcecontact->get('id')]);
        $destinationuser = new user_persistent($destinationuser->id);
        $destinationcontact->read();
        $contactmergerequest->read();

        $this->assertEquals($sourceuser->id, $destinationcontact->get('userid'));
        $this->assertEquals(1, $destinationuser->get('suspended'));
        $this->assertEquals(false, $sourcecontact);
        $this->assertEquals(0, $contactmergerequest->get('active'));
        $this->assertEquals(true,  $result);
    }

    public function test_multple_matching_requests() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/enrol/arlo/lib.php');
        /** @var enrol_arlo_generator $plugingenerator */
        $plugingenerator = $this->getDataGenerator()->get_plugin_generator('enrol_arlo');
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $contact1 = $plugingenerator->create_contact($user1);
        // Associate contact and user.
        //$contact1->set('userid', $user1->id);
        //$contact1->update();

        $user2 = $this->getDataGenerator()->create_user();
        $contact2 = $plugingenerator->create_contact($user2);
        // Associate contact and user.
        //$contact2->set('userid', $user2->id);
        //$contact2->update();

        $user3 = $this->getDataGenerator()->create_user();
        $contact3 = $plugingenerator->create_contact($user3);
        // Associate contact and user.
        $contact3->set('userid', $user3->id);
        $contact3->update();

        $user4 = $this->getDataGenerator()->create_user();
        $contact4 = $plugingenerator->create_contact($user4);
        // Associate contact and user.
        $contact4->set('userid', $user4->id);
        $contact4->update();

        // Create a contact merge request.
        $contactmergerequest1 = $plugingenerator->create_contact_merge_request($contact3, $contact4);
        $contactmergerequest2 = $plugingenerator->create_contact_merge_request($contact2, $contact3);
        //$contactmergerequest2 = $plugingenerator->create_contact_merge_request($contact1, $contact2);

        // Set up course enrolments.
        $manualplugin = enrol_get_plugin('manual');

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->assertNotEmpty($studentrole);

        $category = $this->getDataGenerator()->create_category();
        $course1 = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $course2 = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $manualinstance1 = $DB->get_record('enrol', ['courseid' => $course1->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $manualinstance2 = $DB->get_record('enrol', ['courseid' => $course2->id, 'enrol' => 'manual'], '*', MUST_EXIST);

        $manualplugin->enrol_user($manualinstance1, $user3->id, $studentrole->id);


        $handler = new contact_merge_requests_handler($contact4);
        $result = $handler->apply_all_merge_requests();
        $this->assertEquals(true,  $result);

    }

}
