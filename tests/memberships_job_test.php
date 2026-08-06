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
 * Tests registration row reuse and ownership rules in memberships_job.
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

    /**
     * An orphan row belonging to a different user must never be deleted by another user's sync.
     */
    public function test_cross_user_orphan_row_is_not_deleted(): void {
        global $DB;
        $contact = $this->make_linked_contact('learner4@example.com');
        $primary = $this->generator->create_event_registration($contact, $this->event, $this->enrolinstance);

        $othercontact = $this->make_linked_contact('other@example.com');
        $orphan = $this->generator->create_event_registration($othercontact, $this->event, $this->enrolinstance);

        try {
            $this->save($this->make_registration_resource(
                $contact,
                lastmodified: $this->future(),
                sourceid: (int) $orphan->get('sourceid'),
                sourceguid: $orphan->get('sourceguid'),
            ));
            $this->fail('Expected a registrationownershipconflict exception.');
        } catch (moodle_exception $exception) {
            $this->assertSame('registrationownershipconflict', $exception->errorcode);
        }

        // Both rows survive untouched: the other user's row keeps its identity.
        $this->assertTrue($DB->record_exists('enrol_arlo_registration', ['id' => $orphan->get('id')]));
        $primary->read();
        $this->assertNotSame($orphan->get('sourceguid'), $primary->get('sourceguid'));
        $orphanuserid = $DB->get_field('enrol_arlo_registration', 'userid', ['id' => $orphan->get('id')]);
        $this->assertEquals($othercontact->get('userid'), $orphanuserid);
    }

    /**
     * A (user, course) row referencing a different Arlo contact must not have its identity overwritten.
     */
    public function test_row_of_other_contact_for_same_user_is_not_hijacked(): void {
        global $DB;
        $user = $this->getDataGenerator()->create_user(['email' => 'shared@example.com']);
        $info = (object) ['firstname' => 'Shared', 'lastname' => 'User', 'email' => 'shared@example.com'];
        $contacta = $this->generator->create_contact($info);
        $contacta->set('userid', $user->id);
        $contacta->update();
        $contactb = $this->generator->create_contact($info);
        $contactb->set('userid', $user->id);
        $contactb->update();
        $rowa = $this->generator->create_event_registration($contacta, $this->event, $this->enrolinstance);

        [$rowb] = $this->save($this->make_registration_resource($contactb, lastmodified: $this->future()));

        // Contact A's row keeps its identity; contact B's registration got its own row.
        $this->assertNotEquals($rowa->get('id'), $rowb->get('id'));
        $freshrowa = $DB->get_record('enrol_arlo_registration', ['id' => $rowa->get('id')]);
        $this->assertEquals($rowa->get('sourceguid'), $freshrowa->sourceguid);
        $this->assertEquals($contacta->get('sourceid'), $freshrowa->sourcecontactid);
        $this->assertEquals($contactb->get('sourceid'), $rowb->get('sourcecontactid'));
        $this->assertSame(2, $this->count_registrations());
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
