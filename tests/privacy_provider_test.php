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

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;
use enrol_arlo\privacy\provider;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy API unit tests.
 *
 * @package   enrol_arlo
 * @copyright 2019 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class enrol_arlo_privacy_provider_testcase extends \core_privacy\tests\provider_testcase {

    protected $plugingenerator;

    public function setUp() {
        global $CFG;
        require_once($CFG->dirroot . '/enrol/arlo/lib.php');

        if (!class_exists('core_privacy\manager')) {
            $this->markTestSkipped('Moodle version does not support privacy subsystem.');
        }
        /** @var enrol_arlo_generator $plugingenerator */
        $this->plugingenerator = $this->getDataGenerator()->get_plugin_generator('enrol_arlo');
        // Enable and setup plugin.
        $this->plugingenerator->enable_plugin();
        $this->plugingenerator->setup_plugin();
    }

    /**
     * Test getting the plugin contexts a user.
     */
    public function test_get_contexts_for_userid() {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user1->userid = $user1->id;
        $contact1 = $this->plugingenerator->create_contact($user1);

        $template1 = $this->plugingenerator->create_event_template();
        $event1 = $this->plugingenerator->create_event($template1);

        $category1 = $this->getDataGenerator()->create_category();
        $course1 = $this->getDataGenerator()->create_course(['category' => $category1->id]);

        $enrolinstance1 = $this->plugingenerator->create_event_enrolment_instance($course1, $event1);
        $this->plugingenerator->create_event_registration($contact1, $event1, $enrolinstance1);

        enrol_get_plugin('arlo')->enrol($enrolinstance1, $user1);

        // Check user and course contexts.
        $contextlist = provider::get_contexts_for_userid($user1->id);
        foreach ($contextlist->get_contexts() as $currentcontext) {
            if ($currentcontext instanceof context_user) {
                $usercontext = context_user::instance($user1->id);
                $this->assertEquals($usercontext->id, $currentcontext->id);
            }
            if ($currentcontext instanceof context_course) {
                $coursecontext = context_course::instance($course1->id);
                $this->assertEquals($coursecontext->id, $currentcontext->id);
            }
        }
    }

    /**
     * Test that user data is exported correctly.
     */
    public function test_export_user_data() {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user1->userid = $user1->id;
        $contact1 = $this->plugingenerator->create_contact($user1);

        $template1 = $this->plugingenerator->create_event_template();
        $event1 = $this->plugingenerator->create_event($template1);

        $category1 = $this->getDataGenerator()->create_category();
        $course1 = $this->getDataGenerator()->create_course(['category' => $category1->id]);

        $enrolinstance1 = $this->plugingenerator->create_event_enrolment_instance($course1, $event1);
        $this->plugingenerator->create_event_registration($contact1, $event1, $enrolinstance1);

        enrol_get_plugin('arlo')->enrol($enrolinstance1, $user1);

        $contextlist = provider::get_contexts_for_userid($user1->id);
        $approvedcontextlist = new approved_contextlist($user1, 'enrol_arlo', $contextlist->get_contextids());
        provider::export_user_data($approvedcontextlist);
        foreach ($contextlist as $context) {
            $writer = writer::with_context($context);
            $data = $writer->get_data([
                get_string('pluginname', 'enrol_arlo'),
                get_string('groups', 'core_group')
            ]);
            $this->assertTrue($writer->has_any_data());
        }

    }

    /**
     * Delete all data for all user in a particular course context.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_delete_data_for_all_users_in_context() {
        global $DB;
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user1->userid = $user1->id;
        $contact1 = $this->plugingenerator->create_contact($user1);

        $user2 = $this->getDataGenerator()->create_user();
        $user2->userid = $user2->id;
        $contact2 = $this->plugingenerator->create_contact($user2);

        $template1 = $this->plugingenerator->create_event_template();
        $event1 = $this->plugingenerator->create_event($template1);

        $category1 = $this->getDataGenerator()->create_category();
        $course1 = $this->getDataGenerator()->create_course(['category' => $category1->id]);
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course1->id]);

        $enrolinstance1 = $this->plugingenerator->create_event_enrolment_instance(
            $course1,
            $event1,
            ['customint2' => $group1->id]
        );
        $this->plugingenerator->create_event_registration($contact1, $event1, $enrolinstance1);
        $this->plugingenerator->create_event_registration($contact2, $event1, $enrolinstance1);

        enrol_get_plugin('arlo')->enrol($enrolinstance1, $user1);
        enrol_get_plugin('arlo')->enrol($enrolinstance1, $user2);

        $sql =  "SELECT COUNT(gm.id)
                   FROM {groups_members} gm
                   JOIN {groups} g ON gm.groupid = g.id
                  WHERE g.courseid = ? ";
        $this->assertEquals(2, $DB->count_records_sql($sql, [$course1->id]));
        $coursecontext1 = context_course::instance($course1->id);
        provider::delete_data_for_all_users_in_context($coursecontext1);
        $this->assertEquals(0, $DB->count_records_sql($sql, [$course1->id]));
    }

    /**
     * Delete data for a user.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_delete_data_for_user() {
        global $DB;
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user1->userid = $user1->id;
        $contact1 = $this->plugingenerator->create_contact($user1);

        $user2 = $this->getDataGenerator()->create_user();
        $user2->userid = $user2->id;
        $contact2 = $this->plugingenerator->create_contact($user2);

        $user3 = $this->getDataGenerator()->create_user();
        $user3->userid = $user3->id;
        $contact3 = $this->plugingenerator->create_contact($user3);

        $template1 = $this->plugingenerator->create_event_template();
        $event1 = $this->plugingenerator->create_event($template1);

        $category1 = $this->getDataGenerator()->create_category();
        $course1 = $this->getDataGenerator()->create_course(['category' => $category1->id]);
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course1->id]);

        $enrolinstance1 = $this->plugingenerator->create_event_enrolment_instance(
            $course1,
            $event1,
            ['customint2' => $group1->id]
        );
        $this->plugingenerator->create_event_registration($contact1, $event1, $enrolinstance1);
        $this->plugingenerator->create_event_registration($contact2, $event1, $enrolinstance1);
        $this->plugingenerator->create_event_registration($contact3, $event1, $enrolinstance1);

        enrol_get_plugin('arlo')->enrol($enrolinstance1, $user1);
        enrol_get_plugin('arlo')->enrol($enrolinstance1, $user2);
        enrol_get_plugin('arlo')->enrol($enrolinstance1, $user3);

        $this->setUser($user1);

        $sql =  "SELECT COUNT(gm.id)
                   FROM {groups_members} gm
                   JOIN {groups} g ON gm.groupid = g.id
                  WHERE g.courseid = ? ";

        $this->assertEquals(3, $DB->count_records_sql($sql, [$course1->id]));

        $coursecontext1 = context_course::instance($course1->id);
        $approvedcontextlist = new approved_contextlist($user1, 'enrol_arlo', [$coursecontext1->id]);
        provider::delete_data_for_user($approvedcontextlist);
        // Check for 2 users in groups because user1 was deleted.
        $this->assertEquals(2, $DB->count_records_sql($sql, [$course1->id]));

    }

    /**
     * Delete data for users in a course context.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_delete_data_for_users() {
        global $DB;

        if (!interface_exists('\core_privacy\local\request\core_userlist_provider')) {
            $this->markTestSkipped('Moodle version does not support privacy version.');
        }

        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user1->userid = $user1->id;
        $contact1 = $this->plugingenerator->create_contact($user1);

        $user2 = $this->getDataGenerator()->create_user();
        $user2->userid = $user2->id;
        $contact2 = $this->plugingenerator->create_contact($user2);

        $user3 = $this->getDataGenerator()->create_user();

        $template1 = $this->plugingenerator->create_event_template();
        $event1 = $this->plugingenerator->create_event($template1);

        $category1 = $this->getDataGenerator()->create_category();
        $course1 = $this->getDataGenerator()->create_course(['category' => $category1->id]);
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course1->id]);

        $enrolinstance1 = $this->plugingenerator->create_event_enrolment_instance(
            $course1,
            $event1,
            ['customint2' => $group1->id]
        );
        $this->plugingenerator->create_event_registration($contact1, $event1, $enrolinstance1);
        $this->plugingenerator->create_event_registration($contact2, $event1, $enrolinstance1);
        enrol_get_plugin('arlo')->enrol($enrolinstance1, $user1);
        enrol_get_plugin('arlo')->enrol($enrolinstance1, $user2);

        $manualinstance1 = $DB->get_record('enrol', ['courseid' => $course1->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->enrol_user($manualinstance1, $user3->id);
        groups_add_member($group1, $user3);

        $this->setUser($user1);

        $sql =  "SELECT COUNT(gm.id)
                   FROM {groups_members} gm
                   JOIN {groups} g ON gm.groupid = g.id
                  WHERE g.courseid = ? ";
        $this->assertEquals(3, $DB->count_records_sql($sql, [$course1->id]));
        $coursecontext1 = context_course::instance($course1->id);
        $approveduserlist = new approved_userlist(
            $coursecontext1,
            'enrol_arlo',
            [$user1->id, $user2->id]
        );
        provider::delete_data_for_users($approveduserlist);

        // Check for 1 user user3 in groups because user1 and user2 where deleted.
        $this->assertEquals(1, $DB->count_records_sql($sql, [$course1->id]));
    }

    /**
     * Retreive users in a course context.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_get_users_in_context() {
        global $DB;

        if (!interface_exists('\core_privacy\local\request\core_userlist_provider')) {
            $this->markTestSkipped('Moodle version does not support privacy version.');
        }

        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user1->userid = $user1->id;
        $contact1 = $this->plugingenerator->create_contact($user1);

        $user2 = $this->getDataGenerator()->create_user();
        $user2->userid = $user2->id;
        $contact2 = $this->plugingenerator->create_contact($user2);

        $user3 = $this->getDataGenerator()->create_user();

        $template1 = $this->plugingenerator->create_event_template();
        $event1 = $this->plugingenerator->create_event($template1);

        $category1 = $this->getDataGenerator()->create_category();
        $course1 = $this->getDataGenerator()->create_course(['category' => $category1->id]);
        $group1 = $this->getDataGenerator()->create_group(['courseid' => $course1->id]);

        $enrolinstance1 = $this->plugingenerator->create_event_enrolment_instance(
            $course1,
            $event1,
            ['customint2' => $group1->id]
        );
        $this->plugingenerator->create_event_registration($contact1, $event1, $enrolinstance1);
        $this->plugingenerator->create_event_registration($contact2, $event1, $enrolinstance1);
        enrol_get_plugin('arlo')->enrol($enrolinstance1, $user1);
        enrol_get_plugin('arlo')->enrol($enrolinstance1, $user2);

        $manualinstance = $DB->get_record('enrol', ['courseid' => $course1->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        $manualplugin = enrol_get_plugin('manual');
        $manualplugin->enrol_user($manualinstance, $user3->id);

        $context1 = context_course::instance($course1->id);;
        $userlist = new core_privacy\local\request\userlist($context1, 'enrol_arlo');

        enrol_arlo\privacy\provider::get_users_in_context($userlist);

        $userids = $userlist->get_userids();
        asort($userids);
        $this->assertEquals([$user1->id, $user2->id], array_values($userids));
    }

}
