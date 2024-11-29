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

use Exception;
use enrol_arlo\api;
use enrol_arlo\Arlo\AuthAPI\Enum\RegistrationStatus;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\invalid_persistent_exception;
use enrol_arlo\local\client;
use enrol_arlo\persistent;
use enrol_arlo\local\persistent\contact_persistent;
use enrol_arlo\local\persistent\user_persistent;
use enrol_arlo\local\response_processor;
use GuzzleHttp\Psr7\Request;
use coding_exception;
use moodle_exception;
use enrol_arlo\Arlo\AuthAPI\XmlDeserializer;
use DOMDocument;
use enrol_arlo\Arlo\AuthAPI\Resource\Field;

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

    /** @var string area */
    const AREA = 'enrolment';

    /** @var string type */
    const TYPE = 'contacts';

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
     * Process the custom fields of a contact and return the filtered results.
     * 
     * @param \enrol_arlo\Arlo\AuthAPI\Resource\Contact $contactresource
     * @param array $filterfields
     * @return array
     * @throws moodle_exception
     */
    public function process_customfields_for_contact($contactresource, $filterfields = ['BirthDate', 'passportnumber']) {
        $plugin = api::get_enrolment_plugin();
        $pluginconfig = $plugin->get_plugin_config();
        $uri = new RequestUri();
        $uri->setHost($pluginconfig->get('platform'));
        $uri->setResourcePath('contacts/' . $contactresource->ContactID . '/customfields');
        $request = new Request('GET', $uri->output(true));
        $response = client::get_instance()->send_request($request);
        $statuscode = $response->getStatusCode();
        if ($statuscode != 200) {
            throw new moodle_exception('httpstatus:' . $statuscode);
        }
        $contenttype = $response->getHeaderLine('content-type');
        if (strpos($contenttype, 'application/xml') === false) {
            throw new moodle_exception('httpstatus:415', 'enrol_arlo');
        }
        $deserializer = new XmlDeserializer("enrol_arlo\\Arlo\\AuthAPI\\Resource\\");
        $stream = $response->getBody();
        $contents = $stream->getContents();
        if ($stream->eof()) {
            $stream->rewind();
        }
        $doc = new DOMDocument();
        $doc->loadXML($contents);

        $fields = $doc->getElementsByTagName('Field');

        foreach ($fields as $field) {
            $name = $field->getElementsByTagName('Name')->item(0)->nodeValue;
            $value = $field->getElementsByTagName('Value')->item(0)->nodeValue;
            $field_resource = new Field();
            if (!empty($filterfields) && !in_array($name, $filterfields)) {
                continue;
            }
            $field_resource->Name = $name;
            $field_resource->Value = $value;
            $fieldResources[] = $field_resource;
            // Store the name and value in the Field Resource
            // ...
        }
        // $content looks like <CustomFields><Field><Name><Value></Field><Field><Name><Value></Field>
        // Process each field's name and value and store it in the Field Resource.

        return $fieldResources;
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
        global $DB;
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
                $hasnext = true;
                while ($hasnext) {
                    $hasnext = false; // Break paging by default.
                    $uri = new RequestUri();
                    $uri->setHost($pluginconfig->get('platform'));
                    $uri->setPagingTop(250);
                    $uri->setResourcePath($jobpersistent->get('endpoint'));
                    $uri->addExpand('Registration/Contact');
                    //$uri->addExpand('Registration/CustomFields');
                    $filter = "Contact/LastModifiedDateTime gt datetime('" . $jobpersistent->get('lastsourcetimemodified') . "')";
                    $uri->setFilterBy($filter);
                    $uri->setOrderBy('Contact/LastModifiedDateTime ASC');
                    $request = new Request('GET', $uri->output(true));
                    $response = client::get_instance()->send_request($request);
                    $collection = response_processor::process($response);
                    if ($collection->count() > 0) {
                        foreach ($collection as $resource) {
                            try {
                                // No need to process cancelled registrations.
                                if ($resource->Status == RegistrationStatus::CANCELLED) {
                                    continue;
                                }
                                $contactresource = $resource->getContact();
                                if (empty($contactresource)) {
                                    throw new coding_exception(get_string('contactresourcemissing', 'enrol_arlo'));
                                }
                                // From the contactresource run another api call and get the custom fields.
                                $customfields = $this->process_customfields_for_contact($contactresource);

                                $sourceguid = $contactresource->UniqueIdentifier;
                                $contact = contact_persistent::get_record(['sourceguid' => $sourceguid]);
                                if (!$contact || ($contact->get('userid') <= 0)) {
                                    $registration = $DB->get_record_select(
                                        'enrol_arlo_registration',
                                        "sourcecontactguid = :sourcecontactguid AND userid > 0",
                                        ['sourcecontactguid' => $sourceguid],
                                        '*',
                                        IGNORE_MULTIPLE
                                    );
                                    // Try to rebuild contact record from registration record.
                                    if (!$registration) {
                                        throw new coding_exception(get_string('contactrecordmissing', 'enrol_arlo'));
                                    } else {
                                        $contact = new contact_persistent();
                                        $contact->set('userid', $registration->userid);
                                        $contact->set('sourceid', $registration->sourcecontactid);
                                        $contact->set('sourceguid', $registration->sourcecontactguid);
                                        $contact->save();
                                        $contact->read();
                                    }
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
                                $jobpersistent->set('lastsourceid', $contact->get('sourceid'));
                                $jobpersistent->set('lastsourcetimemodified', $contact->get('sourcemodified'));
                                // Update user record.
                                $user = user_persistent::get_record_and_unset(['id' => $contact->get('userid')]);
                                if (!$user || ($user->get('id') <= 0)) {
                                    throw new moodle_exception(get_string('noassociateduser', 'enrol_arlo'));
                                }
                                $user->set('firstname', $contact->get('firstname'));
                                $user->set('lastname', $contact->get('lastname'));
                                $user->set('email', $contact->get('email'));
                                $user->set('phone1', $contact->get('phonemobile'));
                                $user->set('phone2', $contact->get('phonework'));
                                // Update the custom fields here.
                                foreach ($customfields as $customfield) {
                                    //if Name is BirthDate, then try and convert it to unixtime.
                                    if ($customfield->Name == 'BirthDate') {
                                        $customfield->Value = strtotime($customfield->Value);
                                    }
                                    $user->set('profile_field_' . $customfield->Name, $customfield->Value);
                                }
                                $user->update();
                                $user->update_user();
                                // Clear errors on contact and update.
                                $contact->set('errormessage', '');
                                $contact->set('errorcounter', 0);
                                $contact->update();
                                $this->trace->output('Updated user id#' . $user->get('id'));
                            } catch (Exception $exception) {
                                debugging($exception->getMessage(), DEBUG_DEVELOPER);
                                $this->add_error($exception->getMessage());
                            } finally {
                                // Update scheduling information on persistent.
                                $jobpersistent->set('timelastrequest', time());
                                $jobpersistent->set('timenextrequestdelay', self::TIME_PERIOD_DELAY);
                                $jobpersistent->save();
                            }

                        }
                        // See if need to get another page of records.
                        $hasnext = (bool) $collection->hasNext();
                    }
                }
                return true;
            } catch (Exception $exception) {
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
