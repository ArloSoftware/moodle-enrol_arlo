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
 * Job using to update contact information. Updates contacts in an active enrolment instance.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\job;

defined('MOODLE_INTERNAL') || die();

use enrol_arlo\api;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\invalid_persistent_exception;
use enrol_arlo\local\client;
use enrol_arlo\local\persistent\contact_persistent;
use enrol_arlo\local\persistent\user_persistent;
use enrol_arlo\local\response_processor;
use GuzzleHttp\Psr7\Request;
use coding_exception;
use moodle_exception;

/**
 * Job using to update contact information. Updates contacts in an active enrolment instance.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contacts_job extends job {

    /** @const TIME_PERIOD_DELAY time in seconds to delay next request. */
    const TIME_PERIOD_DELAY = 86400; // 24 Hours.

    /**
     * Run the Job.
     *
     * @return bool|mixed
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function run() {
        $trace = self::get_trace();
        $plugin = api::get_enrolment_plugin();
        $pluginconfig = $plugin->get_plugin_config();
        $lockfactory = static::get_lock_factory();
        $lock = $lockfactory->get_lock($this->get_lock_resource(), self::TIME_LOCK_TIMEOUT);
        if ($lock) {
            $jobpersistent = $this->get_job_persistent();
            try {
                $enrolmentinstance = $plugin::get_instance_record($jobpersistent->get('instanceid'));
                if (!$enrolmentinstance) {
                    $this->disable();
                    throw new moodle_exception(get_string('nomatchingenrolmentinstance', 'enrol_arlo'));
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
                $hasnext = true;
                while ($hasnext) {
                    $hasnext = false; // Break paging by default.
                    $uri = new RequestUri();
                    $uri->setHost($pluginconfig->get('platform'));
                    $uri->setPagingTop(250);
                    $uri->setResourcePath($jobpersistent->get('endpoint'));
                    $uri->addExpand('Registration/Contact');
                    $filter = "Contact/LastModifiedDateTime gt datetime('". $jobpersistent->get('lastsourcetimemodified') ."')";
                    $uri->setFilterBy($filter);
                    $uri->setOrderBy('Contact/LastModifiedDateTime ASC');
                    $request = new Request('GET', $uri->output(true));
                    $response = client::get_instance()->send_request($request);
                    $collection = response_processor::process($response);
                    if ($collection->count() > 0) {
                        foreach ($collection as $resource) {
                            try {
                                $contactresource = $resource->getContact();
                                if (empty($contactresource)) {
                                    throw new coding_exception(get_string('contactresourcemissing', 'enrol_arlo'));
                                }
                                $sourceguid = $contactresource->UniqueIdentifier;
                                $contact = contact_persistent::get_record(['sourceguid' => $sourceguid]);
                                if (!$contact) {
                                    throw new coding_exception(get_string('contactrecordmissing', 'enrol_arlo'));
                                }
                                if ($contact->get('userid') <= 0) {
                                    $contact->set('userassociationfailure', 1);
                                    $contact->update();
                                    throw new moodle_exception(get_string('noassociateduser', 'enrol_arlo'));
                                }
                                // Update contact record.
                                $contact->set('firstname', $contactresource->FirstName);
                                $contact->set('lastname', $contactresource->LastName);
                                $contact->set('email', $contactresource->Email);
                                $contact->set('codeprimary', $contactresource->CodePrimary);
                                $contact->set('phonework', $contactresource->PhoneWork);
                                $contact->set('phonemobile', $contactresource->PhoneMobile);
                                $contact->set('sourcestatus', $contactresource->Status);
                                $contact->set('sourcecreated', $contactresource->CreatedDateTime);
                                $contact->set('sourcemodified', $contactresource->LastModifiedDateTime);
                                // Update user record.
                                $user = user_persistent::get_record_and_unset(['id' => $contact->get('userid')]);
                                if ($user->get('id') <= 0) {
                                    $contact->set('userassociationfailure', 1);
                                    $contact->update();
                                    throw new moodle_exception(get_string('noassociateduser', 'enrol_arlo'));
                                }
                                $user->set('firstname', $contact->get('firstname'));
                                $user->set('lastname', $contact->get('lastname'));
                                $user->set('email', $contact->get('email'));
                                $user->set('phone1', $contact->get('phonemobile'));
                                $user->set('phone2', $contact->get('phonework'));
                                $user->update();
                                // Clear errors on contact and update.
                                $contact->set('errormessage', '');
                                $contact->set('errorcounter', 0);
                                $contact->update();
                                // Update scheduling information on persistent after successfull save.
                                $jobpersistent->set('timelastrequest', time());
                                $jobpersistent->set('lastsourceid', $contact->get('sourceid'));
                                $jobpersistent->set('timenextrequestdelay', self::TIME_PERIOD_DELAY);
                                $jobpersistent->set('lastsourcetimemodified', $contact->get('sourcemodified'));
                                $jobpersistent->update();
                            } catch (moodle_exception $exception) {
                                debugging($exception->getMessage(), DEBUG_DEVELOPER);
                                $this->add_error($exception->getMessage());
                                if ($exception instanceof coding_exception || $exception instanceof invalid_persistent_exception) {
                                   throw $exception;
                                }
                            }
                        }
                        // See if need to get another page of records.
                        $hasnext = (bool) $collection->hasNext();
                    }
                }
                return true;
            } catch (moodle_exception $exception) {
                debugging($exception->getMessage(), DEBUG_DEVELOPER);
                $this->add_error($exception->getMessage());
                return false;
            } finally {
                $lock->release();
            }
        } else {
            throw new moodle_exception('locktimeout');
        }
    }
}
