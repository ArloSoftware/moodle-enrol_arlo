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

/**
 *
 * @package   enrol_plugin
 * @copyright 2019 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class enrol_arlo_username_generator_testcase extends \core_privacy\tests\provider_testcase {

    /**
     *  @var enrol_arlo_generator $plugingenerator handle to plugin generator.
     */
    protected $plugingenerator;

    /**
     * @throws coding_exception
     */
    public function setUp() {
        global $CFG;

        require_once($CFG->dirroot . '/enrol/arlo/lib.php');
        /** @var enrol_arlo_generator $plugingenerator */
        $this->plugingenerator = $this->getDataGenerator()->get_plugin_generator('enrol_arlo');
        // Enable and setup plugin.
        $this->plugingenerator->enable_plugin();
        $this->plugingenerator->setup_plugin();
    }

    /**
     * Passed generating a username.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_pass_username_generate() {
        $this->resetAfterTest();

        $userdata = new stdClass();
        $userdata->firstname = 'Frank';
        $userdata->lastname = 'Rizzo';

        $usernamegenerator = new enrol_arlo\local\generator\username_generator();
        $usernamegenerator->set_order('firstnamelastnamerandomnumber');
        $usernamegenerator->add_data($userdata);

        $username = $usernamegenerator->generate();
        $matched = (bool) preg_match('/([a-zA-Z]{1,6}[\d]{1,3})/', $username);
        $this->assertTrue($matched);
    }

    /**
     * Failed to generate username.
     *
     * @throws coding_exception
     * @throws dml_exception
     */
    public function test_failed_username_generate() {
        $this->resetAfterTest();
        $user1 = $this->getDataGenerator()->create_user(
            ['username' => 'username@example.com', 'email' => 'username@example.com']
        );

        $usernamegenerator = new enrol_arlo\local\generator\username_generator();
        $usernamegenerator->set_order('email');
        $usernamegenerator->add_data($user1);

        $username = $usernamegenerator->generate();
        $this->assertFalse($username);
    }
}
