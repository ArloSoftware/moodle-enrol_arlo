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
 * Tests recovery of registrations that failed on a contact merge.
 *
 * @package   enrol_arlo
 * @copyright 2026 Moodle US
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_arlo\local\job\memberships_job;
use enrol_arlo\local\persistent\contact_persistent;
use enrol_arlo\local\persistent\registration_persistent;
use enrol_arlo\task\reattempt_failed_registrations;

/**
 * Tests recovery of registrations that failed on a contact merge.
 *
 * @package   enrol_arlo
 * @copyright 2026 Moodle US
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class merge_failure_recovery_test extends advanced_testcase {
    /**
     * @var enrol_arlo_generator
     */
    private enrol_arlo_generator $generator;

    protected function setUp(): void {
        global $CFG;
        require_once("{$CFG->dirroot}/enrol/arlo/lib.php");
        parent::setUp();
        $this->resetAfterTest();
        $this->redirectMessages();

        $this->generator = $this->getDataGenerator()->get_plugin_generator('enrol_arlo');
        $this->generator->enable_plugin();
        $this->generator->setup_plugin();
    }

    /**
     * Same-user detection on a merge request.
     *
     * @covers \enrol_arlo\local\persistent\contact_merge_request_persistent::resolves_to_same_user
     */
    public function test_resolves_to_same_user(): void {
        [$user, $source, $destination] = $this->make_same_user_contacts();
        $sameuser = $this->generator->create_contact_merge_request($source, $destination);

        $other = $this->getDataGenerator()->create_user();
        $othercontact = $this->generator->create_contact();
        $othercontact->set('userid', $other->id);
        $othercontact->update();
        $differentuser = $this->generator->create_contact_merge_request($othercontact, $destination);

        $this->assertTrue($sameuser->resolves_to_same_user());
        $this->assertFalse($differentuser->resolves_to_same_user());
    }

    /**
     * Every failed registration of a contact is enrolled once its merge is marked complete.
     *
     * @covers \enrol_arlo\local\job\memberships_job::reattempt_failed_registrations
     */
    public function test_reattempt_failed_registrations(): void {
        [$user, $source, $destination] = $this->make_same_user_contacts();
        $merge = $this->generator->create_contact_merge_request($source, $destination);
        $courses = $this->make_failed_registrations($destination, 3);

        $merge->set('active', 0);
        $merge->set('mergefailed', 0);
        $merge->update();

        $this->assertTrue(memberships_job::reattempt_failed_registrations($destination));
        foreach ($courses as $course) {
            $this->assertTrue(is_enrolled(context_course::instance($course->id), $user->id));
        }
        $this->assertSame(0, registration_persistent::count_records(['enrolmentfailure' => 1]));
    }

    /**
     * The clean-up task resolves stuck same-user merges and re-attempts orphaned failures.
     *
     * @covers \enrol_arlo\task\reattempt_failed_registrations::execute
     */
    public function test_task_recovers_stuck_and_orphaned_registrations(): void {
        [$user, $source, $destination] = $this->make_same_user_contacts();
        $merge = $this->generator->create_contact_merge_request($source, $destination);
        $stuckcourses = $this->make_failed_registrations($destination, 2);

        $orphanuser = $this->getDataGenerator()->create_user();
        $orphancontact = $this->generator->create_contact((object) [
            'firstname' => $orphanuser->firstname, 'lastname' => $orphanuser->lastname, 'email' => $orphanuser->email,
        ]);
        $orphancontact->set('userid', $orphanuser->id);
        $orphancontact->update();
        [$orphancourse] = $this->make_failed_registrations($orphancontact, 1, false);

        $this->expectOutputRegex('/enrolled/');
        (new reattempt_failed_registrations())->execute();

        $merge->read();
        $this->assertEquals(0, $merge->get('active'));
        $this->assertEquals(0, $merge->get('mergefailed'));
        foreach ($stuckcourses as $course) {
            $this->assertTrue(is_enrolled(context_course::instance($course->id), $user->id));
        }
        $this->assertTrue(is_enrolled(context_course::instance($orphancourse->id), $orphanuser->id));
        $this->assertSame(0, registration_persistent::count_records(['enrolmentfailure' => 1]));
    }

    /**
     * Create one Moodle user with an enrolment, linked to an old and a new Arlo contact.
     *
     * @return array [user, source contact, destination contact]
     */
    private function make_same_user_contacts(): array {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['email' => 'jane.new@example.com']);
        $course = $this->getDataGenerator()->create_course();
        $manual = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
        enrol_get_plugin('manual')->enrol_user($manual, $user->id);

        $contacts = [];
        foreach (['jane.old@example.com', 'jane.new@example.com'] as $email) {
            $contact = $this->generator->create_contact((object) ['firstname' => 'Jane', 'lastname' => 'Doe', 'email' => $email]);
            $contact->set('userid', $user->id);
            $contact->update();
            $contacts[] = $contact;
        }
        return [$user, $contacts[0], $contacts[1]];
    }

    /**
     * Create Arlo-linked courses with a registration for the contact that fails to enrol.
     *
     * @param contact_persistent $contact
     * @param int $count
     * @param bool $process Fail through enrolment processing instead of flagging directly.
     * @return array Created courses.
     */
    private function make_failed_registrations(contact_persistent $contact, int $count, bool $process = true): array {
        $courses = [];
        for ($i = 0; $i < $count; $i++) {
            $course = $this->getDataGenerator()->create_course();
            $event = $this->generator->create_event($this->generator->create_event_template());
            $instance = $this->generator->create_event_enrolment_instance($course, $event);
            $registration = $this->generator->create_event_registration($contact, $event, $instance);
            if ($process) {
                $this->assertFalse(memberships_job::process_enrolment_registration($instance, $registration));
            } else {
                $registration->set('enrolmentfailure', 1);
                $registration->update();
            }
            $courses[] = $course;
        }
        return $courses;
    }
}
