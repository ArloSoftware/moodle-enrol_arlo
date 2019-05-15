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
 * Arlo enrolment plugin test data generator class.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use enrol_arlo\local\persistent\contact_persistent;
use enrol_arlo\local\persistent\contact_merge_request_persistent;
use enrol_arlo\local\persistent\event_template_persistent;
use enrol_arlo\local\persistent\event_persistent;
use enrol_arlo\local\persistent\registration_persistent;
use enrol_arlo\Arlo\AuthAPI\Enum\ContactStatus;
use enrol_arlo\Arlo\AuthAPI\Enum\EventStatus;
use enrol_arlo\Arlo\AuthAPI\Enum\EventTemplateStatus;
use enrol_arlo\Arlo\AuthAPI\Enum\RegistrationStatus;
use enrol_arlo\local\config\arlo_plugin_config;
use enrol_arlo\local\enum\arlo_type;

/**
 * Arlo enrolment plugin test data generator class.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_arlo_generator extends testing_module_generator {
    /**
     * Test platform name.
     *
     * @return string
     */
    public function get_platform() {
        return 'phpunit.arlo.co';
    }

    public function get_arlo_type_datetime() {
        return date('Y-m-d\TH:i:sP');
    }

    public function get_arlo_datetime_format() {
        return 'Y-m-d\TH:i:sP';
    }

    /**
     * Core model properties that need to be protected.
     *
     * @var array
     */
    protected static $protected = [
        'id',
        'usermodified',
        'timecreated',
        'timemodified',
        'platform',
        'sourceid',
        'sourceguid',
        'sourcecreated',
        'sourcemodified'
    ];

    /**
     * Make sure plugin is enabled.
     *
     */
    public function enable_plugin() {
        $enabled = enrol_get_plugins(true);
        $enabled['arlo'] = true;
        $enabled = array_keys($enabled);
        set_config('enrol_plugins_enabled', implode(',', $enabled));
    }

    /**
     * Configure plugin settings.
     *
     * @throws coding_exception
     */
    public function setup_plugin() {
        $pluginconfig = new arlo_plugin_config();
        $pluginconfig::install_defaults();
        $pluginconfig->set('platform', $this->get_platform());
        $pluginconfig->set('apiusername', 'phpunit@phpunit.arlo.co');
        $pluginconfig->set('apipassword', 'password1234');
    }

    /**
     * Create a contact record.
     *
     * @param stdClass|null $data
     * @return contact_persistent|\enrol_arlo\persistent
     * @throws \enrol_arlo\invalid_persistent_exception
     * @throws coding_exception
     */
    public function create_contact(stdClass $data = null) {
        $randomnumber = rand();
        $datetime = $this->get_arlo_type_datetime();
        $contact = new contact_persistent();
        $contact->set('platform', $this->get_platform());
        $contact->set('sourceid', $randomnumber);
        $contact->set('sourceguid', $randomnumber);
        $contact->set('sourcestatus', ContactStatus::ACTIVE);
        $contact->set('sourcecreated', $datetime);
        $contact->set('sourcemodified', $datetime);
        if (!is_null($data)) {
            foreach (get_object_vars($data) as $property => $value) {
                if ($contact::has_property($property) && !in_array($property, self::$protected)) {
                    $contact->set($property, $value);
                }
            }
        }
        $contact->save();
        return $contact;
    }

    /**
     * Create Event record off a Event template record.
     *
     * @param event_template_persistent $template
     * @param stdClass|null $data
     * @return event_persistent
     * @throws coding_exception
     */
    public function create_event(event_template_persistent $template, stdClass $data = null) {
        $randomnumber = rand();
        $datetimeformat = $this->get_arlo_datetime_format();
        $date = new DateTime(
            'now',
            core_date::get_user_timezone_object()
        );
        $event = new event_persistent();
        $event->set('platform', $this->get_platform());
        $event->set('sourceid', $randomnumber);
        $event->set('sourceguid', $randomnumber);
        $event->set('sourcecreated', $date->format($datetimeformat));
        $event->set('sourcemodified', $date->format($datetimeformat));
        $event->set('sourcestatus', EventStatus::ACTIVE);
        $code = $template->get('code') . 'E-' . $randomnumber;
        $event->set('code', $code);
        $event->set('startdatetime', $date->format($datetimeformat));
        $date->add(new DateInterval('P1D'));
        $event->set('finishdatetime', $date->format($datetimeformat));
        $event->set('sourcetemplateid', $template->get('sourceid'));
        $event->set('sourcetemplateguid', $template->get('sourceguid'));
        if (!is_null($data)) {
            foreach (get_object_vars($data) as $property => $value) {
                if ($event::has_property($property) && !in_array($property, self::$protected)) {
                    $event->set($property, $value);
                }
            }
        }
        $event->save();
        return $event;
    }

    /**
     * Create a Event template record.
     *
     * @return event_template_persistent
     * @throws coding_exception
     */
    public function create_event_template() {
        $randomnumber = rand();
        $datetime = $this->get_arlo_type_datetime();
        $template = new event_template_persistent();
        $template->set('platform', $this->get_platform());
        $template->set('sourceid', $randomnumber);
        $template->set('sourceguid', $randomnumber);
        $template->set('sourcestatus', EventTemplateStatus::ACTIVE);
        $template->set('name', 'EvtTplName-' . $randomnumber);
        $template->set('code', 'EvtTplCode-' . $randomnumber);
        $template->set('sourcecreated', $datetime);
        $template->set('sourcemodified', $datetime);
        $template->save();
        return $template;
    }

    /**
     * Create an Arlo enrolment instance.
     *
     * @param $course
     * @param event_persistent $event
     * @param array $data
     * @return mixed
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     */
    public function create_event_enrolment_instance($course, event_persistent $event, $data = []) {
        global $DB;
        /** @var $plugin enrol_arlo_plugin */
        $plugin = enrol_get_plugin('arlo');
        $data = array_merge([
            'arlotype' => arlo_type::EVENT,
            'arloevent' => $event->get('sourceguid')],
            $data
        );
        $id = $plugin->add_instance($course, $data);
        return $DB->get_record(
            'enrol',
            ['id' => $id, 'courseid' => $course->id, 'enrol' => 'arlo'],
            '*',
            MUST_EXIST
        );

    }

    /**
     * Create a registration record.
     *
     * @param contact_persistent $contact
     * @param event_persistent $event
     * @param null $enrolmentinstance
     * @param stdClass|null $data
     * @return registration_persistent
     * @throws coding_exception
     */
    public function create_event_registration(contact_persistent $contact,
                                              event_persistent $event,
                                              $enrolmentinstance = null,
                                              stdClass $data = null) {
        $randomnumber = rand();
        $datetime = $this->get_arlo_type_datetime();
        $registration = new registration_persistent();
        $registration->set('platform', $this->get_platform());
        $registration->set('sourceid', $randomnumber);
        $registration->set('sourceguid', $randomnumber);
        $registration->set('sourcestatus', RegistrationStatus::APPROVED);
        $registration->set('sourcecreated', $datetime);
        $registration->set('sourcemodified', $datetime);
        $registration->set('sourcecontactid', $contact->get('sourceid'));
        $registration->set('sourcecontactguid', $contact->get('sourceguid'));
        $registration->set('sourceeventid', $event->get('sourceid'));
        $registration->set('sourceeventguid', $event->get('sourceguid'));
        if ($enrolmentinstance) {
            $registration->set('enrolid', $enrolmentinstance->id);
            $registration->set('userid', $contact->get('userid'));
        }
        $registration->save();
        if (!is_null($data)) {
            foreach (get_object_vars($data) as $property => $value) {
                if ($event::has_property($property) && !in_array($property, self::$protected)) {
                    $event->set($property, $value);
                }
            }
        }
        return $registration;
    }

    /**
     * Create a contact merge request based on contact persistents.
     *
     * @param contact_persistent $sourcecontact
     * @param contact_persistent $destinationcontact
     * @return contact_merge_request_persistent|\enrol_arlo\persistent
     * @throws \enrol_arlo\invalid_persistent_exception
     * @throws coding_exception
     */
    public function create_contact_merge_request(contact_persistent $sourcecontact,
                                                 contact_persistent $destinationcontact) {
        $randomnumber = rand();
        $datetime = $this->get_arlo_type_datetime();
        $contactmergerequest = new contact_merge_request_persistent();
        $contactmergerequest->set('platform', $this->get_platform());
        $contactmergerequest->set('sourceid', $randomnumber);
        $contactmergerequest->set('sourcecontactid', $sourcecontact->get('sourceid'));
        $contactmergerequest->set('sourcecontactguid', $sourcecontact->get('sourceguid'));
        $contactmergerequest->set('destinationcontactid', $destinationcontact->get('sourceid'));
        $contactmergerequest->set('destinationcontactguid', $destinationcontact->get('sourceguid'));
        $contactmergerequest->set('sourcecreated', $datetime);
        $contactmergerequest = $contactmergerequest->create();
        return $contactmergerequest;
    }
}