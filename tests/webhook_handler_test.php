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
defined('MOODLE_INTERNAL') || die();

use enrol_arlo\input\webhook_handler;

/**
 * Arlo enrolment webhook handler tests.
 *
 * This file contains unit tests for the webhook handler of the Arlo enrolment plugin.
 * The tests cover the following functionality:
 * - Checking if the webhook is enabled
 * - Validating the signature of a webhook request
 * - Validating a webhook ID
 * - Processing events received from a webhook
 * - Getting the class and callback for a webhook event
 *
 * @package   enrol_arlo
 * @author    Oscar Nadjar <oscar.nadjar@moodle.com>
 * @copyright Moodle US
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webhook_handler_test extends advanced_testcase {

    /**
     * @var webhook_handler
     */
    private $webhookhandler;

    /**
     * Set up the test case.
     *
     * @throws coding_exception
     */
    protected function setUp(): void
    {
        $this->resetAfterTest();
        $this->webhookhandler = new webhook_handler();
    }

    /**
     * Test if the webhook is enabled.
     *
     * @throws coding_exception
     */
    public function test_webhook_enable()
    {
        $this->assertFalse($this->webhookhandler->webhook_is_enable());

        // Enable webhook.
        set_config('enablewebhook', 1, 'enrol_arlo');
        $this->webhookhandler = new webhook_handler();
        $this->assertEquals($this->webhookhandler->webhook_is_enable(), 1);
    }

    /**
     * Test if the signature of a webhook request is valid.
     *
     * @throws coding_exception
     */
    public function test_validate_signature() {

        // Test invalid signature.
        $signature = 'notvalidsignature';
        $body = '{events: {}}';
        $webhooksecretkey = 'webhooksecretkey';
        $this->assertFalse($this->webhookhandler->validatesignature($signature, $body, $webhooksecretkey));

        // Test invalid webhook secret key.
        $signature = 'ssDoykuhYoBGW2ABhRhldOMgSEQT5bhH2IH7zHmZ+UT9CimRC7elmvqOE0H+htYiVTjCIM/KIKoLy/2dZij3pQ==';
        $body = '{events: {}}';
        $webhooksecretkey = 'notvalidwebhooksecretkey';
        $this->assertFalse($this->webhookhandler->validatesignature($signature, $body, $webhooksecretkey));

        // Test invalid body.
        $signature = 'ssDoykuhYoBGW2ABhRhldOMgSEQT5bhH2IH7zHmZ+UT9CimRC7elmvqOE0H+htYiVTjCIM/KIKoLy/2dZij3pQ==';
        $body = 'notvalidbody';
        $webhooksecretkey = 'webhooksecretkey';
        $this->assertFalse($this->webhookhandler->validatesignature($signature, $body, $webhooksecretkey));

        // Test valid signature.
        $signature = 'ssDoykuhYoBGW2ABhRhldOMgSEQT5bhH2IH7zHmZ+UT9CimRC7elmvqOE0H+htYiVTjCIM/KIKoLy/2dZij3pQ==';
        $body = '{events: {}}';
        $webhooksecretkey = 'webhooksecretkey';
        $this->assertTrue($this->webhookhandler->validatesignature($signature, $body, $webhooksecretkey));
    }

    /**
     * Test if a webhook ID is valid.
     *
     * @throws coding_exception
     */
    public function test_validate_webhookid() {
        $this->assertFalse($this->webhookhandler->validatedwebhookid());
    }

    /**
     * Test processing events received from a webhook.
     *
     * @throws coding_exception
     */
    public function test_process_events() {
        global $DB;
        set_config('enablewebhook', 1, 'enrol_arlo');
        set_config('useadhoctask', 1, 'enrol_arlo');
        $this->webhookhandler = new webhook_handler();
        $event = new stdClass();
        $event->resourceType = 'Registration';
        $event->resourceId = 'resourceId';
        $events = [$event];
        $this->webhookhandler->process_events($events);

        $adhoctask = $DB->get_record('task_adhoc',['component' => 'enrol_arlo']);
        $event = json_decode($adhoctask->customdata);
        $this->assertEquals('Registration', $event->resourceType);
        $this->assertEquals('resourceId', $event->resourceId);
    }

    /**
     * Test case for get_class method.
     */
    public function test_get_class() {
        $event = new stdClass();
        $event->resourceType = 'Registration';

        $this->assertEquals('enrol_arlo\local\job\memberships_job', webhook_handler::get_class($event));
    }

    /**
     * Test case for get_callback method.
     */
    public function test_get_callback() {
        $event = new stdClass();
        $event->resourceType = 'Registration';

        $this->assertEquals('::process_registration_event', webhook_handler::get_callback($event));
    }    
}
