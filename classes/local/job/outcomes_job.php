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
 * Job class for sending outcome data to individual registrations.
 *
 * @author    Troy Williams
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\job;

defined('MOODLE_INTERNAL') || die();

use core_user;
use enrol_arlo\api;
use enrol_arlo\local\external;
use enrol_arlo\local\learner_progress;
use enrol_arlo\persistent;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\local\client;
use enrol_arlo\local\enum\arlo_type;
use enrol_arlo\local\persistent\registration_persistent;
use enrol_arlo\result;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use coding_exception;
use moodle_exception;

/**
 * Job class for sending outcome data to individual registrations.
 *
 * @author    Troy Williams
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class outcomes_job extends job {

    /** @var string area */
    const AREA = 'enrolment';

    /** @var string type */
    const TYPE = 'outcomes';

    /** @var mixed $enrolmentinstance */
    protected $enrolmentinstance;

    /**
     * Override to load enrolment instance.
     *
     * @param persistent $jobpersistent
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function __construct(persistent $jobpersistent) {
        parent::__construct($jobpersistent);
        $plugin = api::get_enrolment_plugin();
        $this->enrolmentinstance = $plugin::get_instance_record($jobpersistent->get('instanceid'));
    }

    /**
     * Check if config allows this job to be processed.
     *
     * @return bool
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function can_run() {
        $plugin = api::get_enrolment_plugin();
        $pluginconfig = $plugin->get_plugin_config();
        $jobpersistent = $this->get_job_persistent();
        $enrolmentinstance = $this->enrolmentinstance;
        if (!$enrolmentinstance) {
            $jobpersistent->set('disabled', 1);
            $jobpersistent->save();
            $this->add_reasons(get_string('nomatchingenrolmentinstance', 'enrol_arlo'));
            return false;
        }
        if ($enrolmentinstance->status == ENROL_INSTANCE_DISABLED) {
            $this->add_reasons(get_string('enrolmentinstancedisabled', 'enrol_arlo'));
            return false;
        }
        if (!$pluginconfig->get('allowhiddencourses')) {
            $course = get_course($enrolmentinstance->courseid);
            if (!$course->visible) {
                $this->add_reasons(get_string('allowhiddencoursesdiabled', 'enrol_arlo'));
                return false;
            }
        }
        if (!$pluginconfig->get('allowoutcomespushing')) {
            $this->add_reasons(get_string('outcomespushingdisabled', 'enrol_arlo'));
            return false;
        }
        if ($enrolmentinstance->customchar2 == arlo_type::EVENT && !$pluginconfig->get('pusheventresults')) {
            $this->add_reasons(get_string('eventresultpushingdisabled', 'enrol_arlo'));
            return false;
        }
        if ($enrolmentinstance->customchar2 == arlo_type::ONLINEACTIVITY && !$pluginconfig->get('pushonlineactivityresults')) {
            $this->add_reasons(get_string('onlineactivityresultpushingdisabled', 'enrol_arlo'));
            return false;
        }
        return true;
    }

    /**
     * Run the Job.
     *
     * @return bool|mixed
     * @throws \coding_exception
     * @throws moodle_exception
     */
    public function run() {
        if (!$this->can_run()) {
            return false;
        }
        $jobpersistent = $this->get_job_persistent();
        $enrolmentinstance = $this->enrolmentinstance;
        $plugin = api::get_enrolment_plugin();
        $pluginconfig = $plugin->get_plugin_config();
        $lockfactory = static::get_lock_factory();
        $lock = $lockfactory->get_lock($this->get_lock_resource(), self::TIME_LOCK_TIMEOUT);
        if ($lock) {
            $limit = $pluginconfig->get('outcomejobdefaultlimit');
            $registrations = registration_persistent::get_records(
                ['enrolid' => $enrolmentinstance->id, 'updatesource' => 1],
                'timelastrequest', 'ASC', 0, $limit
            );
            $course = get_course($enrolmentinstance->courseid);
            if (!$registrations) {
                // Update scheduling information on persistent after successfull save.
                $jobpersistent->set('timelastrequest', time());
                $jobpersistent->save();
            } else {
                foreach ($registrations as $registrationpersistent) {
                    try {
                        $user = core_user::get_user($registrationpersistent->get('userid'));
                        if (!$user) {
                            throw new moodle_exception('moodleaccountdoesnotexist');
                        }
                        $registrationid = $registrationpersistent->get('sourceid');
                        $sourceregistration = external::get_registration_resource($registrationid);
                        $learnerprogress = new learner_progress($course, $user);
                        $data = $learnerprogress->get_keyed_data_for_arlo();
                        if (!empty($data)) {
                            $this->trace->output(implode(',', $data));
                            external::patch_registration_resource($sourceregistration, $data);
                        }
                    } catch (Exception $exception) {
                        debugging($exception->getMessage(), DEBUG_DEVELOPER);
                        $this->add_error($exception->getMessage());
                        $registrationpersistent->set('errormessage', $exception->getMessage());
                    } finally {
                        $registrationpersistent->set('timelastrequest', time());
                        // Reset update flag.
                        $registrationpersistent->set('updatesource', 0);
                        $registrationpersistent->save();
                        // Update scheduling information on persistent after successfull save.
                        $jobpersistent->set('timelastrequest', time());
                        $jobpersistent->save();
                        $lock->release();
                    }
                }
            }
            $lock->release();
            return true;
        } else {
            throw new moodle_exception('locktimeout');
        }
    }
}
