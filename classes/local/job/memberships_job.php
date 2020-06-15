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
use enrol_arlo\local\config\arlo_plugin_config;
use enrol_arlo\local\factory\job_factory;
use enrol_arlo\local\generator\username_generator;
use enrol_arlo\local\handler\contact_merge_requests_handler;
use enrol_arlo\local\persistent\user_persistent;
use enrol_arlo\local\user_matcher;
use enrol_arlo\persistent;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\local\client;
use enrol_arlo\local\persistent\contact_persistent;
use enrol_arlo\local\persistent\registration_persistent;
use enrol_arlo\local\response_processor;
use GuzzleHttp\Psr7\Request;
use coding_exception;
use moodle_exception;
use stdClass;


/**
 * Memberships job responsible for handling enrolments, user creation and matching based on
 * registration.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class memberships_job extends job {

    /** @var string area */
    const AREA = 'enrolment';

    /** @var string type */
    const TYPE = 'memberships';

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
        return true;
    }

    /**
     * Run the Job.
     *
     * @return bool|mixed
     * @throws coding_exception
     * @throws moodle_exception
     */
    public function run() {
        if (!$this->can_run()) {
            return false;
        }
        $trace = self::get_trace();
        $jobpersistent = $this->get_job_persistent();
        $plugin = api::get_enrolment_plugin();
        $pluginconfig = $plugin->get_plugin_config();
        $lockfactory = static::get_lock_factory();
        $lock = $lockfactory->get_lock($this->get_lock_resource(), self::TIME_LOCK_TIMEOUT);
        if ($lock) {
            try {
                // We don't know how many records we will be retrieving it maybe 5 it maybe 5000,
                // and the page size limit is 250. So we have to keep calling the endpoint and
                // adjusting and the filter each call to so we get all records and don't end up
                // getting same 250 each call.
                $hasnext = true;
                while ($hasnext) {
                    $hasnext = false; // Break paging by default.
                    // Update contact merge requests records every page.
                    $contactmergerequestsjob = job_factory::get_job(['type' => 'contact_merge_requests']);
                    $contactmergerequestsjob->run();
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
                                // Save Arlo registration information into Moodle persistents.
                                list($registration, $contact) = static::save_resource_information_to_persistents(
                                    $this->enrolmentinstance,
                                    $resource
                                );
                                // Invoke enrolment processing for this registration.
                                $result = static::process_enrolment_registration(
                                    $this->enrolmentinstance,
                                    $registration,
                                    $contact
                                );
                                if (!$result) {
                                    $jobpersistent->set('timelastrequest', time());
                                    $jobpersistent->update();
                                    $this->add_error(get_string('enrolmentfailure', 'enrol_arlo'));
                                    continue;
                                }
                                // Update scheduling information on persistent after successfull save.
                                $jobpersistent->set('timelastrequest', time());
                                $jobpersistent->set('lastsourceid', $registration->get('sourceid'));
                                $jobpersistent->set('lastsourcetimemodified', $registration->get('sourcemodified'));
                                $jobpersistent->set('errormessage', '');
                                $jobpersistent->set('errorcounter', 0);
                                $jobpersistent->update();
                            } catch (moodle_exception $exception) {
                                debugging($exception->getMessage(), DEBUG_DEVELOPER);
                                $this->add_error($exception->getMessage());
                                if ($registration) {
                                    $registration->set('errormessage', $exception->getMessage());
                                    $errorcounter = $registration->get('errorcounter');
                                    $registration->set('errorcounter', ++$errorcounter);
                                    $registration->update();
                                }
                                continue;
                            }
                        }
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

    /**
     * Helper method for matching Moodle user based on Arlo contact information.
     *
     * @param $contact
     * @return bool|user_persistent
     * @throws \dml_exception
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function match_user_from_contact($contact) {
        $matches = user_matcher::get_matches_based_on_preference($contact);
        $matchcount = count($matches);
        // More than one matches won't work.
        if ($matchcount > 1) {
            $contact->set('userassociationfailure', 1);
            $contact->save();
            return $matchcount;
        }
        // Associate to Moodle account.
        if ($matchcount == 1) {
            $match = reset($matches);
            $user = user_persistent::get_record_and_unset(
                ['id' => $match->id, 'deleted' => 0]
            );
            $contact->set('userid', $user->get('id'));
            $contact->save();
            return $user;
        }
        return false;
    }

    /**
     * Method to process an enrolment against a saved registration.
     *
     * @todo Refactor code, separation of concerns etc. Make easier to understand.
     *
     * @param stdClass $enrolmentinstance
     * @param registration_persistent $registration
     * @param contact_persistent|null $contact
     * @return bool
     * @throws \dml_exception
     * @throws \enrol_arlo\invalid_persistent_exception
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function process_enrolment_registration(stdClass $enrolmentinstance,
                                                          registration_persistent $registration,
                                                          contact_persistent $contact = null) {
        // Load plugin class instance.
        $plugin = api::get_enrolment_plugin();
        // Get plugin config.
        $pluginconfig = new arlo_plugin_config();
        // Reset enrolment failure flag by default.
        $registration->set('enrolmentfailure', 0);
        if (is_null($contact)) {
            $contact = $registration->get_contact();
            if (empty($contact)) {
                return false;
            }
        }
        $contact->set('usercreationfailure', 0);
        $contact->set('userassociationfailure', 0);
        // Process unenrolment.
        if ($registration->get('sourcestatus') == RegistrationStatus::CANCELLED) {
            $user = $contact->get_associated_user();
            if ($user instanceof user_persistent) {
                if ($user->get('id') > 0 && $user->get('deleted') != 1) {
                    $plugin->unenrol($enrolmentinstance, $user->to_record());
                }
            } else {
                $contact->delete();
            }
            // Cleanup registration.
            $registration->delete();
            // Return back to caller.
            return true;
        }
        // Process enrolment.
        if (in_array($registration->get('sourcestatus'), [RegistrationStatus::APPROVED, RegistrationStatus::COMPLETED])) {
            // Load contact merge request handler.
            $handler = new contact_merge_requests_handler($contact);
            $result = $handler->apply_all_merge_requests();
            if (!$result) {
                $registration->set('enrolmentfailure', 1);
                $registration->update();
                administrator_notification::send_unsuccessful_enrolment_message();
                return false;
            } else {
                $contact->read();
            }
            // No user associated with contact.
            if ($contact->get('userid') <= 0) {
                $user = static::match_user_from_contact($contact);
                if (!($user instanceof user_persistent)) {
                    // User is an integer greater than 1 means multiple matches.
                    if ($user) {
                        $contact->set('userassociationfailure', 1);
                        $contact->set('errormessage', 'Duplicate user accounts.');
                        $contact->update();
                        $registration->set('enrolmentfailure', 1);
                        $registration->update();
                        administrator_notification::send_unsuccessful_enrolment_message();
                        return false;
                    } else {
                        // Mo matches, create a new Moodle user.
                        $user = new user_persistent();
                        $usernamegenerator = new username_generator($contact->to_record(), $pluginconfig->get('usernameformatorder'));
                        $username = $usernamegenerator->generate();
                        if (!$username) {
                            $contact->set('usercreationfailure', 1);
                            $contact->set('errormessage', 'Failed to create username');
                            $contact->update();
                            $registration->set('enrolmentfailure', 1);
                            $registration->update();
                            administrator_notification::send_unsuccessful_enrolment_message();
                            return false;
                        }
                        $user->set('username', $username);
                        // Set new property values on user.
                        $user->set('firstname', $contact->get('firstname'));
                        $user->set('lastname', $contact->get('lastname'));
                        $user->set('email', $contact->get('email'));
                        // Conditionally add codeprimary as idnumber.
                        if (empty($user->get('idnumber'))) {
                            if (!empty($contact->get('codeprimary'))) {
                                $user->set('idnumber', $contact->get('codeprimary'));
                            }
                        }
                        $user->set('phone1', $contact->get('phonemobile'));
                        $user->set('phone2', $contact->get('phonework'));
                        $user->create();
                        // Important must associate user with contact.
                        $contact->set('userid', $user->get('id'));
                        $contact->save();
                    }
                }
            } else {
                // Get contacts associated user.
                $user = user_persistent::get_record_and_unset(
                    ['id' => $contact->get('userid'), 'deleted' => 0]
                );
                if (!$user) {
                    $registration->set('enrolmentfailure', 1);
                    $registration->update();
                    throw new moodle_exception('moodleaccountdoesnotexist');
                }
                // Update property values for existing user.
                $user->set('firstname', $contact->get('firstname'));
                $user->set('lastname', $contact->get('lastname'));
                $user->set('email', $contact->get('email'));
                // Conditionally add codeprimary as idnumber.
                if (empty($user->get('idnumber'))) {
                    if (!empty($contact->get('codeprimary'))) {
                        $user->set('idnumber', $contact->get('codeprimary'));
                    }
                }
                $user->set('phone1', $contact->get('phonemobile'));
                $user->set('phone2', $contact->get('phonework'));
                $user->update();

            }
            // Important must associate user with registration.
            $registration->set('userid', $user->get('id'));
            // Save registration record state.
            $registration->save();
            // Process enrolment.
            $plugin->enrol($enrolmentinstance, $user->to_record());

        }
        return true;
    }

    /**
     * Helper method for saving an Arlo registration in to Moodle records registration and contact.
     *
     * @param $enrolmentinstance
     * @param $resource
     * @return array
     * @throws coding_exception
     * @throws moodle_exception
     */
    public static function save_resource_information_to_persistents($enrolmentinstance, $resource) {
        $sourceid = $resource->RegistrationID;
        $sourceguid = $resource->UniqueIdentifier;
        // Require contact resource to be attached.
        $contactresource = $resource->getContact();
        if (empty($contactresource)) {
            throw new moodle_exception('missingresource',
                null, null,  null, 'contact');
        }
        // Require event or online activity resource to be attached.
        $eventresource = $resource->getEvent();
        $onlineactivityresource = $resource->getOnlineActivity();
        if (empty($eventresource) && empty($onlineactivityresource)) {
            throw new moodle_exception('missingresource',
                null, null,  null, 'course'); // Course is Event ot Online Activity.
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
        // Check for existing contact record.
        $contact = $registration->get_contact();
        // Create new contact.
        if (!$contact) {
            $contact = new contact_persistent();
            $contact->set('sourceid', $contactresource->ContactID);
            $contact->set('sourceguid', $contactresource->UniqueIdentifier);
            // Existing contact.
        } else {
            // Associate existing contacts mapped user account to registration.
            if ($contact->get('userid') > 0) {
                $registration->set('userid', $contact->get('userid'));
            }
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
        // Reset contact failure flags and save contact record.
        $contact->set('usercreationfailure', 0);
        $contact->set('userassociationfailure', 0);
        $contact->save();
        // Reset contact failure flags and save registration record.
        $registration->set('errormessage', '');
        $registration->set('enrolmentfailure', 0);
        $registration->save();
        // Return registration and contact persistents.
        return array($registration, $contact);
    }

}
