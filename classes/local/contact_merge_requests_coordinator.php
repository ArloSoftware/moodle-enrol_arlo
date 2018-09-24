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
 * Class for working with contact merge request for a contact.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local;

defined('MOODLE_INTERNAL') || die();

use coding_exception;
use enrol_arlo\persistent;
use enrol_arlo\local\persistent\contact_persistent;
use enrol_arlo\local\persistent\contact_merge_request_persistent;

/**
 * Class for working with contact merge request for a contact.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contact_merge_requests_coordinator {

    /** @var contact_persistent $originalcontact */
    protected $originalcontact;

    /** @var contact_persistent $contact */
    protected $contact;

    /** @var \core\persistent[] @var */
    protected $contactmergerequests;

    /** @var bool $status */
    protected $status;

    /** @var contact_merge_request_persistent $lastfailedcontactmergerequest */
    protected $lastfailedcontactmergerequest;

    /**
     * Check if any outstanding merge request for a contact record.
     *
     * @param contact_persistent $contact
     * @return int
     * @throws coding_exception
     */
    public static function contact_has_active_requests(contact_persistent $contact) {
        if ($contact->get('id') <= 0) {
            throw new coding_exception('Require valid contact record.');
        }
        $contactguid = $contact->get('sourceguid');
        $conditions = [
            'sourcecontactguid' => $contactguid,
            'destinationcontactguid' => $contactguid,
            'active' => 1
        ];
        $select = "(sourcecontactguid = :sourcecontactguid OR destinationcontactguid = :destinationcontactguid) AND active = :active";
        return contact_merge_request_persistent::count_records_select($select, $conditions) ? true : false;
    }

    /**
     * Get active Contact Merge Requests where contact is a source or destination in order of sourceid ASC.
     *
     * @param contact_persistent $contact
     * @return \core\persistent[]
     * @throws coding_exception
     */
    public static function get_active_requests_for_contact(contact_persistent $contact) {
        if ($contact->get('id') <= 0) {
            throw new coding_exception('Require valid contact record.');
        }
        $contactguid = $contact->get('sourceguid');
        $conditions = [
            'sourcecontactguid' => $contactguid,
            'destinationcontactguid' => $contactguid,
            'active' => 1
        ];
        $select = "(sourcecontactguid = :sourcecontactguid OR destinationcontactguid = :destinationcontactguid) AND active = :active";
        return contact_merge_request_persistent::get_records_select($select, $conditions, 'sourcecreated ASC');
    }

    /**
     * Constructor.
     *
     * @param contact_persistent $contact
     * @throws coding_exception
     */
    public function __construct(contact_persistent $contact) {
        $this->originalcontact = $contact;
        $this->contact = $contact;
        $this->contactmergerequests = static::get_active_requests_for_contact($contact);
    }

    /**
     * Return current contact.
     *
     * @return contact_persistent
     */
    public function get_contact() {
        return $this->contact;
    }

    /**
     * Last failed request.
     *
     * @return contact_merge_request_persistent
     */
    public function get_lastfailedcontactmergerequest() {
        return $this->lastfailedcontactmergerequest;
    }

    /**
     * Return current status of applied contact merge requests.
     *
     * @return bool
     */
    public function get_status() {
        return $this->status;
    }

    /**
     * Main function to apply contact merge requests on a contact.
     *
     * @return bool
     * @throws coding_exception
     */
    public function apply_merge_requests() {
        // No merge requests to apply.
        if (empty($this->contactmergerequests)) {
            return $this->status = true;
        }
        foreach ($this->contactmergerequests as $contactmergerequest) {
            $sourcecontact = $contactmergerequest->get_source_contact();
            $destinationcontact = $contactmergerequest->get_destination_contact();
            $sourceuser = false;
            if ($sourcecontact) {
                $sourceuser = $sourcecontact->get_associated_user();
            }
            $destinationuser = false;
            if ($destinationcontact) {
                $destinationuser = $destinationcontact->get_associated_user();
            }
            // No source contact or associated user. Based on scenerios 1 and 2.
            if (!$sourcecontact || !$sourceuser) {
                // Associated Moodle user to destination contact, esle user matching should handle.
                if ($destinationuser) {
                    $destinationcontact->set('userid', $destinationuser->get('id'));
                    $destinationuser->update();
                    $contactmergerequest->set('destinationuserid', $destinationuser->get('id'));
                }
                $contactmergerequest->set('active', 0);
                $contactmergerequest->update();
                $this->contact = $destinationcontact;
                $this->status = true;
                continue;
            }
            // Source contact has course enrolments. Destination contact has no associated user so switch by
            // setting source user id on destination contact. Based on scenerio 3.
            if ($sourceuser && !$destinationuser) {
                // Switch contact to be associated with source user.
                $destinationcontact->set('userid', $sourceuser->get('id'));
                $destinationcontact->update();
                // Remove source contact.
                $sourcecontact->delete();
                $contactmergerequest->set('sourceuserid', $sourceuser->get('id'));
                $contactmergerequest->set('active', 0);
                $contactmergerequest->update();
                $this->contact = $destinationcontact;
                $this->status = true;
                continue;
            }
            // Both source and destination have associated user accounts.
            if ($sourceuser && $destinationuser) {
                // No course enrolments for source user.
                if (!$sourceuser->has_course_enrolments() && $destinationuser->has_course_enrolments()) {
                    // Suspend source user account.
                    $sourceuser->set('suspended', 1);
                    $sourceuser->update();
                    // Remove source contact.
                    $sourcecontact->delete();
                    $contactmergerequest->set('sourceuserid', $sourceuser->get('id'));
                    $contactmergerequest->set('destinationuserid', $destinationuser->get('id'));
                    $contactmergerequest->set('active', 0);
                    $contactmergerequest->update();
                    $this->contact = $destinationcontact;
                    $this->status = true;
                    continue;
                }
                // Source user has enrolments, destination does not.
                if ($sourceuser->has_course_enrolments() && !$destinationuser->has_course_enrolments()) {
                    // Switch contact to be associated with source user.
                    $destinationcontact->set('userid', $sourceuser->get('id'));
                    $destinationcontact->update();
                    // Remove source contact.
                    $sourcecontact->delete();
                    // Suspend source user account.
                    $destinationuser->set('suspended', 1);
                    $destinationuser->update();
                    // Update merge request and status information.
                    $contactmergerequest->set('sourceuserid', $sourceuser->get('id'));
                    $contactmergerequest->set('destinationuserid', $destinationuser->get('id'));
                    $contactmergerequest->set('active', 0);
                    $contactmergerequest->update();
                    $this->contact = $destinationcontact;
                    $this->status = true;
                    continue;
                }
                if ($sourceuser->has_course_enrolments() && $destinationuser->has_course_enrolments()) {
                    $contactmergerequest->set('sourceuserid', $sourceuser->get('id'));
                    $contactmergerequest->set('destinationuserid', $destinationuser->get('id'));
                    $contactmergerequest->set('mergefailed', 1);
                    $contactmergerequest->update();
                    $this->lastfailedcontactmergerequest = $contactmergerequest;
                    $this->contact = $destinationcontact;
                    $this->status = false;
                    break;
                }
            }
            throw new coding_exception('failedmergerequestscenerio');
        }
    }

}
