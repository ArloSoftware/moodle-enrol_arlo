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
 * Tests the one-row-per-(userid, enrolid) invariant in memberships_job (ARLO-77).
 *
 * @package   enrol_arlo
 * @category  phpunit
 * @copyright 2026 Moodle US
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use enrol_arlo\Arlo\AuthAPI\Enum\RegistrationStatus;
use enrol_arlo\Arlo\AuthAPI\Resource\Contact as ContactResource;
use enrol_arlo\Arlo\AuthAPI\Resource\Event as EventResource;
use enrol_arlo\Arlo\AuthAPI\Resource\Registration as RegistrationResource;
use enrol_arlo\local\job\memberships_job;
use enrol_arlo\local\persistent\contact_persistent;
use PHPUnit\Framework\Attributes\Group;

#[Group('enrol_arlo')]
final class memberships_job_test extends advanced_testcase {

    private enrol_arlo_generator $generator;
    private stdClass $enrolinstance;
    private \enrol_arlo\local\persistent\event_persistent $event;

    protected function setUp(): void {
        global $CFG;
        require_once "{$CFG->dirroot}/enrol/arlo/lib.php";
        parent::setUp();
        $this->resetAfterTest();

        $this->generator = $this->getDataGenerator()->get_plugin_generator('enrol_arlo');
        $this->generator->enable_plugin();
        $this->generator->setup_plugin();

        $course = $this->getDataGenerator()->create_course();
        $this->event = $this->generator->create_event($this->generator->create_event_template());
        $this->enrolinstance = $this->generator->create_event_enrolment_instance($course, $this->event);
    }

    /** Re-syncing an unchanged registration must short-circuit, never duplicate the row. */
    public function test_repeat_sync_of_same_registration_is_noop(): void {
        $contact = $this->make_linked_contact();
        $resource = $this->make_registration_resource($contact);

        [$first, , $skip1] = $this->save($resource);
        [$second, , $skip2] = $this->save($resource);

        $this->assertFalse($skip1);
        $this->assertTrue($skip2);
        $this->assertEquals($first->get('id'), $second->get('id'));
        $this->assertSame(1, $this->count_registrations());
    }

    /** A new RegistrationID for the same (user, course) pair updates the existing row in place. */
    public function test_new_sourceguid_for_same_user_course_pair_updates_in_place(): void {
        global $DB;
        $contact = $this->make_linked_contact('learner2@example.com');

        $this->save($this->make_registration_resource($contact));
        $new = $this->make_registration_resource($contact, lastmodified: $this->future());
        $this->save($new);

        $rows = $DB->get_records('enrol_arlo_registration', ['enrolid' => $this->enrolinstance->id]);
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertSame($new->UniqueIdentifier, $row->sourceguid);
        $this->assertEquals($new->RegistrationID, $row->sourceid);
        $this->assertEquals($contact->get('userid'), $row->userid);
    }

    /** A stale row already holding the incoming sourceguid must be dropped before the in-place update. */
    public function test_orphan_row_holding_incoming_sourceguid_is_dropped(): void {
        global $DB;
        $contact = $this->make_linked_contact('learner3@example.com');
        $primary = $this->generator->create_event_registration($contact, $this->event, $this->enrolinstance);

        $sourceguid = 'orphan-' . uniqid();
        $sourceid = random_int(1, 999999);
        $orphan = $this->generator->create_event_registration($contact, $this->event);
        $orphan->set('sourceguid', $sourceguid);
        $orphan->set('sourceid', $sourceid);
        $orphan->save();

        $this->save($this->make_registration_resource(
            $contact,
            lastmodified: $this->future(),
            sourceid: $sourceid,
            sourceguid: $sourceguid,
        ));

        $this->assertFalse($DB->record_exists('enrol_arlo_registration', ['id' => $orphan->get('id')]));
        $rows = $DB->get_records('enrol_arlo_registration', ['userid' => $contact->get('userid')]);
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertSame($sourceguid, $row->sourceguid);
        $this->assertEquals($primary->get('id'), $row->id);
    }

    // ----- helpers -----

    private function save(RegistrationResource $resource): array {
        return memberships_job::save_resource_information_to_persistents($this->enrolinstance, $resource);
    }

    private function count_registrations(): int {
        global $DB;
        return $DB->count_records('enrol_arlo_registration', ['enrolid' => $this->enrolinstance->id]);
    }

    private function future(): string {
        return (new DateTimeImmutable('+1 hour'))->format('Y-m-d\TH:i:sP');
    }

    private function make_linked_contact(string $email = 'learner@example.com'): contact_persistent {
        $user = $this->getDataGenerator()->create_user(['email' => $email]);
        $info = (object) ['firstname' => 'Test', 'lastname' => 'Learner', 'email' => $email];
        $contact = $this->generator->create_contact($info);
        $contact->set('userid', $user->id);
        $contact->update();
        return $contact;
    }

    private function make_registration_resource(
        contact_persistent $contact,
        string $status = RegistrationStatus::APPROVED,
        ?string $lastmodified = null,
        ?int $sourceid = null,
        ?string $sourceguid = null,
    ): RegistrationResource {
        $now = (new DateTimeImmutable())->format('Y-m-d\TH:i:sP');

        $registration = new RegistrationResource();
        $registration->RegistrationID = $sourceid ?? random_int(1000, 999999);
        $registration->UniqueIdentifier = $sourceguid ?? 'reg-' . uniqid();
        $registration->Status = $status;
        $registration->CreatedDateTime = $now;
        $registration->LastModifiedDateTime = $lastmodified ?? $now;

        $contactresource = new ContactResource();
        $contactresource->ContactID = $contact->get('sourceid');
        $contactresource->UniqueIdentifier = $contact->get('sourceguid');
        $contactresource->FirstName = $contact->get('firstname');
        $contactresource->LastName = $contact->get('lastname');
        $contactresource->Email = $contact->get('email');
        $contactresource->Status = $contact->get('sourcestatus');
        $contactresource->CreatedDateTime = $contact->get('sourcecreated');
        $contactresource->LastModifiedDateTime = $contact->get('sourcemodified');
        $registration->setContact($contactresource);

        $eventresource = new EventResource();
        $eventresource->EventID = $this->event->get('sourceid');
        $eventresource->UniqueIdentifier = $this->event->get('sourceguid');
        $registration->setEvent($eventresource);

        return $registration;
    }
}
