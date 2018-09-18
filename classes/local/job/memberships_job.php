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
use enrol_arlo\local\generator\username_generator;
use enrol_arlo\local\persistent\contact_merge_request_persistent;
use enrol_arlo\local\persistent\user_persistent;
use enrol_arlo\local\user_matcher;
use enrol_arlo\persistent;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\Arlo\AuthAPI\Resource\AbstractCollection;
use enrol_arlo\invalid_persistent_exception;
use enrol_arlo\local\client;
use enrol_arlo\local\persistent\contact_persistent;
use enrol_arlo\local\persistent\registration_persistent;
use enrol_arlo\local\response_processor;
use GuzzleHttp\Psr7\Request;
use moodle_exception;
use coding_exception;

class memberships_job extends job {

    public function run() {
        $plugin = api::get_enrolment_plugin();
        $pluginconfig = $plugin->get_plugin_config();
        $lockfactory = static::get_lock_factory();
        $lock = $lockfactory->get_lock($this->get_lock_resource(), self::TIME_LOCK_TIMEOUT);
        $trace = self::get_trace();
        try {
            $jobpersistent = $this->get_job_persistent();
            $enrolmentinstance = $plugin::get_instance_record($jobpersistent->get('instanceid'), MUST_EXIST);
            if ($enrolmentinstance->status == ENROL_INSTANCE_DISABLED) {
                $this->add_reasons('Enrolment instance disabled.');
                return false;
            }
            if (!$pluginconfig->get('allowhiddencourses')) {
                $course = get_course($enrolmentinstance->courseid);
                if (!$course->visible) {
                    $this->add_reasons('Course is hidden. Allow hidden courses is not set.');
                    return false;
                }
            }
            if ($lock) {
                // We don't know how many records we will be retrieving it maybe 5 it maybe 5000,
                // and the page size limit is 250. So we have to keep calling the endpoint and
                // adjusting and the filter each call to so we get all records and don't end up
                // getting same 250 each call.
                $hasnext = true;
                while ($hasnext) {
                    $hasnext = false; // Break paging by default.
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
                                /** @var $resource enrol_arlo\Arlo\AuthAPI\Resource\Registration */
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
                                $contact->save();

                                // Time to process registration.
                                $status = static::process_registration($registration);

                                // Update scheduling information on persistent after successfull save.
                                $jobpersistent->set('timelastrequest', time());
                                $jobpersistent->set('lastsourceid', $sourceid);
                                $jobpersistent->set('lastsourcetimemodified', $sourcemodified);
                                $jobpersistent->update();

                            } catch (moodle_exception $exception) {
                                debugging($exception->getMessage(), DEBUG_DEVELOPER);
                                // Can't really do anythink but break out on these types of exceptions.
                                if ($exception instanceof invalid_persistent_exception || $exception instanceof coding_exception) {
                                   throw $exception;
                                }
                                $this->add_error($exception->getMessage());
                            }
                        }
                        $hasnext = (bool) $collection->hasNext();
                    }
                }
            } else {
                throw new moodle_exception('locktimeout');
            }
        } catch (moodle_exception $exception) {
            debugging($exception->getMessage(), DEBUG_DEVELOPER);
            $this->add_error($exception->getMessage());
            return false;
        } finally {
            $lock->release();
        }
        return true;
    }

    /**
     * @param persistent $registration
     * @throws \dml_exception
     * @throws coding_exception
     */
    public static function process_registration(persistent $registration) {
        /** @var $plugin \enrol_arlo_plugin */
        $plugin = api::get_enrolment_plugin();
        if ($registration->get('id') <= 0) {
            throw new coding_exception('Registration must be valid record.');
        }
        $enrolmentinstance = $plugin::get_instance_record($registration->get('enrolid'), MUST_EXIST);
        $contact = $registration->get_contact();
        if (!$contact) {
            throw new coding_exception('Cannot find stored contact for registration.');
        }
        $contactmergerequests = contact_merge_request_persistent::find_active_requests_for_contact($contact->get('sourceguid'));
        list($status, $destinationcontact) = static::process_contact_merge_requests($contact, $contactmergerequests);
        if (!$status) {
            throw new coding_exception('Merge requests fail');
        }
        // Do have an associated user or do we need to match or create?
        if ($destinationcontact->get('userid') <= 0) {
            $matches = user_matcher::get_matches_based_on_preference($contact);
            $matchcount = count($matches);
            if ($matchcount > 1) {
                $destinationcontact->set('userassociationfailure', 1);
                $destinationcontact->save();
                throw new moodle_exception('More than one Moodle account matches');
            }
            // Associate to Moodle account.
            if ($matchcount == 1) {
                $match = reset($matches);
                $destinationcontact->set('userid', $match->id);
                $destinationcontact->save();
                $registration->set('userid', $match->id);
                $registration->save();
            }
            // Create new Moodle account and associate.
            if ($matchcount == 0) {
                $user = new user_persistent();
                $username = username_generator::generate(
                    $destinationcontact->get('firstname'),
                    $destinationcontact->get('lastname'),
                    $destinationcontact->get('email')
                );
                $user->set('username', $username);
                $user->set('firstname', $destinationcontact->get('firstname'));
                $user->set('lastname', $destinationcontact->get('lastname'));
                $user->set('email', $destinationcontact->get('email'));
                $user->set('phone1', $destinationcontact->get('phonemobile'));
                $user->set('phone2', $destinationcontact->get('phonework'));
                $user->save();
                $destinationcontact->set('userid', $user->get('id'));
                $destinationcontact->save();
                $registration->set('userid', $user->get('id'));
                $registration->save();
            }

        } else {
            $user = user_persistent::create_from($destinationcontact->get('userid'));
            $user->set('firstname', $destinationcontact->get('firstname'));
            $user->set('lastname', $destinationcontact->get('lastname'));
            $user->set('email', $destinationcontact->get('email'));
            $user->set('phone1', $destinationcontact->get('phonemobile'));
            $user->set('phone2', $destinationcontact->get('phonework'));
            $user->save();

            $registration->set('userid', $destinationcontact->get('userid'));
            $registration->save();
        }
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

        return true;
    }

    public static function process_contact_merge_requests(persistent $contact, array $contactmergerequests) {
        $status = true;
        $destinationcontact = $contact;
        foreach ($contactmergerequests as $contactmergerequest) {
            /** @var $contactmergerequest contact_merge_request_persistent */
            $sourcecontact = $contactmergerequest->get_source_contact();
            $destinationcontact = $contactmergerequest->get_destination_contact();
            // First stage: No source contact, a destination contact with no associated user.
            if (!$sourcecontact && ($contact->get('sourceguid') == $destinationcontact->get('sourceguid'))) {
                $contactmergerequest->set('active', 0);
                $contactmergerequest->update();
                $destinationcontact = $contact;
                continue;
            }
            throw new coding_exception('TODO handler other scenerios');
        }
        return [$status, $destinationcontact];
    }
}
