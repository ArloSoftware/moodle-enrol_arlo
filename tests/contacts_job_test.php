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
 * Tests for contacts_job pagination: an all-cancelled page must advance the paging
 * cursor instead of re-requesting the same page forever.
 *
 * @package   enrol_arlo
 * @category  phpunit
 * @copyright 2026 Arlo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use enrol_arlo\local\client;
use enrol_arlo\local\job\contacts_job;
use enrol_arlo\local\persistent\job_persistent;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

class contacts_job_test extends advanced_testcase {

    /** @var enrol_arlo_generator $plugingenerator handle to plugin generator. */
    protected $plugingenerator;

    /** @var array captured Guzzle request/response history for the current test. */
    protected $history = [];

    /**
     * Test setup.
     */
    public function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/enrol/arlo/lib.php');
        $this->resetAfterTest();
        $this->plugingenerator = $this->getDataGenerator()->get_plugin_generator('enrol_arlo');
        $this->plugingenerator->enable_plugin();
        $this->plugingenerator->setup_plugin();
    }

    /**
     * Reset the injected HTTP handler so static state does not leak between tests.
     */
    public function tearDown(): void {
        client::set_test_handler(null);
        $this->history = [];
        parent::tearDown();
    }

    /**
     * Build a course with an Arlo Event enrolment instance.
     *
     * @return stdClass the enrolment instance record.
     */
    protected function create_arlo_instance() {
        $course = $this->getDataGenerator()->create_course();
        $template = $this->plugingenerator->create_event_template();
        $event = $this->plugingenerator->create_event($template);
        return $this->plugingenerator->create_event_enrolment_instance($course, $event);
    }

    /**
     * Fetch the auto-registered contacts job for an enrolment instance.
     *
     * @param int $instanceid
     * @return job_persistent
     */
    protected function get_contacts_job($instanceid) {
        return job_persistent::get_record(
            ['area' => 'enrolment', 'type' => 'contacts', 'instanceid' => $instanceid]
        );
    }

    /**
     * Queue a sequence of fixture files as Arlo XML responses and route the client through them.
     *
     * @param array $fixtures fixture filenames under tests/fixtures/
     */
    protected function mock_arlo_responses(array $fixtures) {
        global $CFG;
        $responses = [];
        foreach ($fixtures as $fixture) {
            $xml = file_get_contents($CFG->dirroot . '/enrol/arlo/tests/fixtures/' . $fixture);
            $responses[] = new Response(200, ['Content-Type' => 'application/xml'], $xml);
        }
        $stack = HandlerStack::create(new MockHandler($responses));
        $this->history = [];
        $stack->push(Middleware::history($this->history));
        client::set_test_handler($stack);
    }

    /**
     * A full page of Cancelled registrations must still advance the cursor and stop paging,
     * rather than re-requesting the same page forever.
     */
    public function test_all_cancelled_page_advances_cursor_and_stops_paging(): void {
        $instance = $this->create_arlo_instance();
        $job = $this->get_contacts_job($instance->id);

        // Sanity: the cursor starts at the epoch default that the flood was stuck on.
        $this->assertSame('1970-01-01T00:00:00Z', $job->get('lastsourcetimemodified'));
        $this->assertEquals(0, $job->get('lastsourceid'));

        // Page 1: 3 Cancelled registrations + a "next" link. Page 2: empty (terminates).
        $this->mock_arlo_responses([
            'registrations_cancelled_page.xml',
            'registrations_empty_page.xml',
        ]);

        $result = (new contacts_job($job))->run();
        $this->assertTrue($result);

        // Exactly two requests: page 1, then page 2 - NOT an unbounded re-request loop.
        $this->assertCount(2, $this->history,
            'contacts_job should page once and stop; more requests means the cursor stalled.');

        // The cursor advanced past the cancelled page to the last contact on it.
        $job->read();
        $this->assertSame('2025-07-17T15:39:56.2075043Z', $job->get('lastsourcetimemodified'));
        $this->assertEquals(2003, $job->get('lastsourceid'));

        // The second request used the advanced keyset cursor (incl. the ContactID tiebreaker).
        $secondfilter = urldecode((string) $this->history[1]['request']->getUri());
        $this->assertStringContainsString("gt datetime('2025-07-17T15:39:56.2075043Z')", $secondfilter);
        $this->assertStringContainsString('Contact/ContactID gt 2003', $secondfilter);
    }

    /**
     * If the cursor genuinely cannot advance (e.g. contacts with no LastModifiedDateTime) while
     * the API still reports more pages, the safety net must stop paging instead of looping.
     */
    public function test_paging_halts_when_cursor_cannot_advance(): void {
        $instance = $this->create_arlo_instance();
        $job = $this->get_contacts_job($instance->id);

        // Queue the same non-advancing page twice: with the guard only ONE is ever requested.
        $this->mock_arlo_responses([
            'registrations_cancelled_no_modified_page.xml',
            'registrations_cancelled_no_modified_page.xml',
        ]);

        $contactsjob = new contacts_job($job);
        $contactsjob->run();

        // The guard stopped after a single request rather than re-fetching the identical page.
        $this->assertCount(1, $this->history,
            'A non-advancing page with a next link must not be requested more than once.');

        // Cursor untouched, and the no-progress reason was recorded.
        $job->read();
        $this->assertSame('1970-01-01T00:00:00Z', $job->get('lastsourcetimemodified'));
        $this->assertEquals(0, $job->get('lastsourceid'));
        $this->assertTrue($contactsjob->has_errors());
        $this->assertContains(get_string('pagingnoprogress', 'enrol_arlo'), $contactsjob->get_errors());
    }

    /**
     * A corrupted datetime cursor must be rejected before it reaches the OData filter,
     * and no request may be sent to the API.
     */
    public function test_malformed_datetime_cursor_is_rejected(): void {
        $instance = $this->create_arlo_instance();
        $job = $this->get_contacts_job($instance->id);
        $job->set('lastsourcetimemodified', "x') OR (1 eq 1");
        $job->save();

        $this->mock_arlo_responses(['registrations_empty_page.xml']);

        $contactsjob = new contacts_job($job);
        $this->assertFalse($contactsjob->run());
        $this->assertDebuggingCalled();
        $this->assertCount(0, $this->history, 'No request must be sent with an invalid cursor.');
        $this->assertTrue($contactsjob->has_errors());
    }
}
