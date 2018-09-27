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


/**
 * Arlo enrolment plugin test data generator class.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_arlo_generator extends testing_module_generator {
    /**
     * Platform name.
     *
     * @return string
     */
    public function get_platform() {
        return 'phpunit.arlo.co';
    }

    public function get_arlo_type_datetime() {
        return date('Y-m-d\TH:i:sP');
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
        $contact->set('sourcecreated', $datetime);
        $contact->set('sourcemodified', $datetime);
        $contact = $contact->create();
        $id = $contact->get('id');
        $guid = str_pad($id, 36, '0', STR_PAD_LEFT);
        $contact->set('sourceid', $id);
        $contact->set('sourceguid', $guid);
        if (!is_null($data)) {
            foreach (get_object_vars($data) as $property => $value) {
                if ($contact::has_property($property)) {
                    $contact->set($property, $value);
                }
            }
        }
        $contact->update();
        return $contact;
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
    public function create_contact_merge_request(contact_persistent $sourcecontact, contact_persistent $destinationcontact) {
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
        $id = $contactmergerequest->get('id');
        $guid = str_pad($id, 36, '0', STR_PAD_LEFT);
        $contactmergerequest->set('sourceid', $guid);
        return $contactmergerequest;
    }
}