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

class learner_progress_test extends \advanced_testcase {

    /**
     * Test setup.
     */
    public function setUp(): void {
        global $CFG;
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_course.php');
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_activity.php');
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_duration.php');
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_grade.php');
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_date.php');

        $this->setAdminUser();
        $this->resetAfterTest();
    }

    public function test_completion_criteria_grade1() {
        global $DB;
        $timegraded = 1610000000;

        // Create a course and enrol a couple of users.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id], ['completion' => 1]);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, $studentrole->id);

        // Set completion criteria and mark the user to complete the criteria.
        $criteriadata = (object) [
            'id' => $course->id,
            'criteria_activity' => [$assign->cmid => 1],
        ];
        $criterion = new \completion_criteria_activity();
        $criterion->update_config($criteriadata);

        // Set completion criteria.
        $criteriadata = (object) [
            'id' => $course->id,
            'criteria_grade' => 1,
            'criteria_grade_value' => 66,
        ];
        $criterion = new \completion_criteria_grade();
        $criterion->update_config($criteriadata);


        // Complete the Assignment activity for the user and check the learner progress
        $cmassign = get_coursemodule_from_id('assign', $assign->cmid);
        $completion = new \completion_info($course);
        $completion->update_state($cmassign, COMPLETION_COMPLETE, $user1->id);


        $ccompletion = new \completion_completion(['userid' => $user1->id, 'course' => $course->id]);
        $this->assertFalse($ccompletion->is_complete());


        $learnerprogress = new \enrol_arlo\local\learner_progress($course, $user1,2);
        $data = $learnerprogress->get_keyed_data_for_arlo();
        $this->assertEquals('In progress', $data['ProgressStatus']);


        $coursegradeitem = \grade_item::fetch_course_item($course->id);

        // Grade User 1 with a passing grade.
        $grade1 = new \grade_grade();
        $grade1->itemid = $coursegradeitem->id;
        $grade1->timemodified = $timegraded;
        $grade1->userid = $user1->id;
        $grade1->finalgrade = 80;
        $grade1->insert();

        // Run completion scheduled task.
        $task = new \core\task\completion_regular_task();
        $this->expectOutputRegex("/Marking complete/");
        $task->execute();
        // Hopefully, some day MDL-33320 will be fixed and all these sleeps
        // and double cron calls in behat and unit tests will be removed.
        sleep(1);
        $task->execute();

        // The course for User 1 is supposed to be marked as completed when the user was graded.
        $ccompletion = new \completion_completion(['userid' => $user1->id, 'course' => $course->id]);
        $this->assertNotNull($ccompletion->timecompleted);
        $this->assertTrue($ccompletion->is_complete());
    }
}
