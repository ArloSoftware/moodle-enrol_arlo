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
 * Memberships job responsible for handling enrolments, user creation and matching based on
 * registration.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\job;

defined('MOODLE_INTERNAL') || die();

use enrol_arlo\api;
use enrol_arlo\Arlo\AuthAPI\Enum\RegistrationStatus;
use enrol_arlo\local\administrator_notification;
use enrol_arlo\local\contact_merge_requests_coordinator;
use enrol_arlo\local\factory\job_factory;
use enrol_arlo\local\generator\username_generator;
use enrol_arlo\local\persistent\contact_merge_request_persistent;
use enrol_arlo\local\persistent\job_persistent;
use enrol_arlo\local\persistent\user_persistent;
use enrol_arlo\local\user_matcher;
use enrol_arlo\persistent;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\invalid_persistent_exception;
use enrol_arlo\local\client;
use enrol_arlo\local\persistent\contact_persistent;
use enrol_arlo\local\persistent\registration_persistent;
use enrol_arlo\local\response_processor;
use GuzzleHttp\Psr7\Request;
use moodle_exception;
use coding_exception;

/**
 * Memberships job responsible for handling enrolments, user creation and matching based on
 * registration.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class memberships_job extends job {

    /**
     * Run the Job.
     *
     * @return bool|mixed
     * @throws coding_exception
     * @throws invalid_persistent_exception
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
                    $jobpersistent->set('disabled', 1);
                    $jobpersistent->save();
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
                // We don't know how many records we will be retrieving it maybe 5 it maybe 5000,
                // and the page size limit is 250. So we have to keep calling the endpoint and
                // adjusting and the filter each call to so we get all records and don't end up
                // getting same 250 each call.
                $hasnext = true;
                while ($hasnext) {
                    $hasnext = false; // Break paging by default.
                    job_factory::get_job(['type' => 'contact_merge_requests'])->run(); // Update contact merge requests records every page.
                    $uri = new RequestUri();
                    $uri->setHost($pluginconfig->get('platform'));
                    $uri->setResourcePath($jobpersistent->get('endpoint'));
                    $uri->addExpand('Registration/Contact');
                    $resourcename = $jobpersistent->get_resource_name();
                    $resourceidname = $resourcename . 'ID';
                    $expand = 'Registration/' . $resourcename;
                    $uri->addExpand($expand);
                    $uri->setPagingTop(250);
                    $filter = "(LastModifiedDateTime gt datetime('" . $jobpersistent->get('lastsourcetimemodified') . "'))";
                    if ($jobpersistent->get('lastsourceid')) {
                        $filter .= " OR ";
                        $filter .= "(LastModifiedDateTime eq datetime('" . $jobpersistent->get('lastsourcetimemodified') . "')";
                        $filter .= " AND ";
                        $filter .= "RegistrationID gt " . $jobpersistent->get('lastsourceid') . ")";
                    }
                    $uri->setFilterBy($filter);
                    $uri->setOrderBy("LastModifiedDateTime ASC,RegistrationID ASC");
                    $request = new Request('GET', $uri->output(true));
                    $response = client::get_instance()->send_request($request);
                    $collection = response_processor::process($response);
                    if ($collection->count() > 0) {
                        foreach ($collection as $resource) {
                            try {
                                $sourceid = $resource->RegistrationID;
                                $sourceguid = $resource->UniqueIdentifier;
                                $sourcemodified = $resource->LastModifiedDateTime;
                                $contactresource = $resource->getContact();
                                if (empty($contactresource)) {
                                    throw new moodle_exception('Contact missing from Registration.');
                                }
                                $eventresource = $resource->getEvent();
                                $onlineactivityresource = $resource->getOnlineActivity();
                                if (empty($eventresource) && empty($onlineactivityresource)) {
                                    throw new moodle_exception('Event or Online Activity missing from Registration.');
                                }
                                // Check for existing registration record.
                                $registration = registration_persistent::get_record(
                                    ['sourceguid' => $sourceguid]
                                );
                                if (!$registration) {
                                    $registration = new registration_persistent();
                                    $registration->set('sourceid', $sourceid);
                                    $registration->set('sourceguid', $sourceguid);
                                }
                                $registration->set('enrolid', $enrolmentinstance->id);
                                $registration->set('attendance', $resource->Attendance);
                                $registration->set('outcome', $resource->Outcome);
                                $registration->set('grade', $resource->Grade);
                                $registration->set('progresspercent', $resource->ProgressPercent);
                                $registration->set('progressstatus', $resource->ProgressStatus);
                                $registration->set('lastactivity', $resource->LastActivityDateTime);
                                $registration->set('sourcestatus', $resource->Status);
                                $registration->set('sourcecreated', $resource->CreatedDateTime);
                                $registration->set('sourcemodified', $resource->LastModifiedDateTime);
                                $registration->set('sourcecontactid', $contactresource->ContactID);
                                $registration->set('sourcecontactguid', $contactresource->UniqueIdentifier);
                                if (!empty($eventresource)) {
                                    $registration->set('sourceeventid', $eventresource->EventID);
                                    $registration->set('sourceeventguid', $eventresource->UniqueIdentifier);
                                }
                                if (!empty($onlineactivityresource)) {
                                    $registration->set('sourceonlineactivityid', $onlineactivityresource->OnlineActivityID);
                                    $registration->set('sourceonlineactivityguid', $onlineactivityresource->UniqueIdentifier);
                                }
                                // Reset registration failure flag.
                                $registration->set('enrolmentfailure', 0);
                                $registration->save();

                                // Check for existing contact record.
                                $contact = $registration->get_contact();
                                if (!$contact) {
                                    $contact = new contact_persistent();
                                    $contact->set('sourceid', $contactresource->ContactID);
                                    $contact->set('sourceguid', $contactresource->UniqueIdentifier);
                                }
                                $contact->set('firstname', $contactresource->FirstName);
                                $contact->set('lastname', $contactresource->LastName);
                                $contact->set('email', $contactresource->Email);
                                $contact->set('codeprimary', $contactresource->CodePrimary);
                                $contact->set('phonework', $contactresource->PhoneWork);
                                $contact->set('phonemobile', $contactresource->PhoneMobile);
                                $contact->set('sourcestatus', $contactresource->Status);
                                $contact->set('sourcecreated', $contactresource->CreatedDateTime);
                                $contact->set('sourcemodified', $contactresource->LastModifiedDateTime);
                                // Reset contact failure flags.
                                $contact->set('usercreationfailure', 0);
                                $contact->set('userassociationfailure', 0);
                                $contact->save();
                                // Apply any contact merge requests.
                                $coordinator = new contact_merge_requests_coordinator($contact);
                                $status = $coordinator->apply_merge_requests();
                                if (!$status) {
                                    $registration->set('enrolmentfailure', 1);
                                    $registration->update();
                                    $contact->set('userassociationfailure', 1);
                                    $contact->update();
                                    administrator_notification::send_unsuccessful_enrolment_message();
                                    throw new moodle_exception('enrolmentfailure');
                                }
                                // Get associated user.
                                $user = user_persistent::get_record_and_unset(
                                    ['id' => $contact->get('userid'), 'deleted' => 0]
                                );
                                // Attempt to match or create.
                                if (!$user) {
                                    // Match.
                                    $matches = user_matcher::get_matches_based_on_preference($contact);
                                    $matchcount = count($matches);
                                    if ($matchcount > 1) {
                                        $registration->set('enrolmentfailure', 1);
                                        $registration->update();
                                        $contact->set('userassociationfailure', 1);
                                        $contact->save();
                                        administrator_notification::send_unsuccessful_enrolment_message();
                                        throw new moodle_exception('morethanoneusermatches');
                                    }
                                    // Associate to Moodle account.
                                    if ($matchcount == 1) {
                                        $match = reset($matches);
                                        $contact->set('userid', $match->id);
                                        $contact->save();
                                        $registration->set('userid', $match->id);
                                        $registration->save();
                                        $user = user_persistent::get_record_and_unset(
                                            ['id' => $match->id, 'deleted' => 0]
                                        );
                                    }
                                    // Create new user.
                                    if ($matchcount == 0) {
                                        $user = new user_persistent();
                                        $username = username_generator::generate(
                                            $contact->get('firstname'),
                                            $contact->get('lastname'),
                                            $contact->get('email')
                                        );
                                        $user->set('username', $username);
                                    }
                                }
                                $user->set('firstname', $contact->get('firstname'));
                                $user->set('lastname', $contact->get('lastname'));
                                $user->set('email', $contact->get('email'));
                                // Conditionally add codeprimary as idnumber.
                                if (empty($user->get('idnumber'))) {
                                    $user->set('idnumber', 'codeprimary');
                                }
                                $user->set('phone1', $contact->get('phonemobile'));
                                $user->set('phone2', $contact->get('phonework'));
                                // Save contact information onto user account.
                                $user->save();
                                // Associate user account with registration.
                                $registration->set('userid', $contact->get('userid'));
                                $registration->save();

                                // Process enrolment.
                                if (in_array($registration->get('sourcestatus'), [RegistrationStatus::APPROVED, RegistrationStatus::COMPLETED])) {
                                    $plugin->enrol($enrolmentinstance, $user->to_record());
                                }
                                // Process unenrolment.
                                if ($registration->get('sourcestatus') == RegistrationStatus::CANCELLED) {
                                    $plugin->unenrol($enrolmentinstance, $user->to_record());
                                    // Cleanup registration.
                                    $registration->delete();
                                }
                                // Update scheduling information on persistent after successfull save.
                                $jobpersistent->set('timelastrequest', time());
                                $jobpersistent->set('lastsourceid', $sourceid);
                                $jobpersistent->set('lastsourcetimemodified', $sourcemodified);
                                $jobpersistent->update();

                            } catch (moodle_exception $exception) {
                                // Can't really do anythink but break out on these types of exceptions.
                                if ($exception instanceof invalid_persistent_exception || $exception instanceof coding_exception) {
                                    throw $exception;
                                }
                                debugging($exception->getMessage(), DEBUG_DEVELOPER);
                                $this->add_error($exception->getMessage());
                            }
                        }
                        $hasnext = (bool) $collection->hasNext();
                    }
                }
                return true;
            } catch (moodle_exception $exception) {
                debugging($exception->getMessage(), DEBUG_DEVELOPER);
                $this->add_error($exception->getMessage());
                if ($exception instanceof invalid_persistent_exception || $exception instanceof coding_exception) {
                    throw $exception;
                }
                return false;
            } finally {
                $lock->release();
            }
        } else {
            throw new moodle_exception('locktimeout');
        }
    }

}
