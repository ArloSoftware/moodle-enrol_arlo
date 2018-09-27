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
 * Class for working with contact merge requests for a contact.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\handler;

defined('MOODLE_INTERNAL') || die();

use coding_exception;
use enrol_arlo\persistent;
use enrol_arlo\local\persistent\contact_persistent;
use enrol_arlo\local\persistent\contact_merge_request_persistent;

/**
 * Class for working with contact merge requests for a contact.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contact_merge_requests_handler {

    /** @var int $appliedcount */
    protected $appliedcount;

    /** @var contact_persistent $initialcontact */
    protected $initialcontact;

    /** @var contact_persistent $currentdestinationcontact */
    protected $currentdestinationcontact;

    /** @var contact_persistent[] $removecontacts */
    protected $removecontacts;

    /**
     * Constructor.
     *
     * @param contact_persistent $contact
     */
    public function __construct(contact_persistent $contact) {
        $this->appliedcount = 0;
        $this->initialcontact = $contact;
        $this->currentdestinationcontact = $contact;
        $this->removecontacts = [];
    }

    /**
     * Get active merge request where contact is destination
     *
     * @param contact_persistent $contact
     * @return contact_merge_request_persistent|false
     * @throws coding_exception
     */
    public function get_active_request(contact_persistent $contact) {
        if ($contact->get('id') <= 0) {
            throw new coding_exception('invalidcontact');
        }
        $conditions = [
            'destinationcontactguid' => $contact->get('sourceguid'),
            'active' => 1
        ];
        return contact_merge_request_persistent::get_record($conditions);
    }

    /**
     * Active contact merge requests for a contact.
     *
     * @throws coding_exception
     */
    public function get_active_requests() {
        $contact = $this->initialcontact;
        if ($contact->get('id') <= 0) {
            throw new coding_exception('invalidcontact');
        }
        $contactguid = $contact->get('sourceguid');
        $conditions = [
            'sourcecontactguid' => $contactguid,
            'destinationcontactguid' => $contactguid,
            'active' => 1
        ];
        $select = "(sourcecontactguid = :sourcecontactguid OR
                    destinationcontactguid = :destinationcontactguid) AND
                    active = :active";
        return contact_merge_request_persistent::get_records_select(
            $select,
            $conditions,
            'sourcecreated DESC, id DESC'
        );
    }

    /**
     * The current contact persistent.
     *
     * @return contact_persistent
     */
    public function get_current_destination_contact() {
        return $this->currentdestinationcontact;
    }

    /**
     * The initial contact set on constructor.
     *
     * @return contact_persistent
     */
    public function get_initial_contact() {
        return $this->initialcontact;
    }

    /**
     * @return bool
     * @throws \dml_exception
     * @throws \enrol_arlo\invalid_persistent_exception
     * @throws coding_exception
     */
    public function apply_all_merge_requests() {
        while(true) {
            $contactmergerequest = $this->get_active_request($this->currentdestinationcontact);

            // No active requests for current destination contact so exit.
            if (!$contactmergerequest) {
                if ($this->removecontacts) {
                    foreach ($this->removecontacts as $contact) {
                        //mtrace('deleting');
                        //print_object($contact);
                        $contact->delete();
                    }
                }
                return true;
            }
            // Set up required destination variables for checking against.
            $destinationcontact= $contactmergerequest->get_destination_contact();
            if ($destinationcontact) {
                $destinationuser = $destinationcontact->get_associated_user();
                if ($destinationuser) {
                    $destinationuserhasenrolments = $destinationuser->has_course_enrolments();
                } else {
                    $destinationuserhasenrolments = false;
                }
            } else {
                $destinationuser = false;
                $destinationuserhasenrolments = false;
            }
            // Set up required source variables for checking against.
            $sourcecontact = $contactmergerequest->get_source_contact();
            if ($sourcecontact) {
                $sourceuser = $sourcecontact->get_associated_user();
                if ($sourceuser) {
                    $sourceuserhasenrolments = $sourceuser->has_course_enrolments();
                } else {
                    $sourceuserhasenrolments = false;
                }
            } else {
                $sourceuser = false;
                $sourceuserhasenrolments = false;
            }
            // Start evaluation.

            // Both source and destination have enrolments.
            if ($sourceuserhasenrolments && $destinationuserhasenrolments) {
                return false;
            }
            // No source enrolments, destination has enrolments.
            if (!$sourceuserhasenrolments && $destinationuserhasenrolments) {
                if ($sourceuser) {
                    $sourceuser->set('suspended', 1);
                    $sourceuser->update();
                }
                if ($sourcecontact) {
                    // Source contacts to delete.
                    //$sourcecontact->delete();
                    $this->removecontacts[] = $sourcecontact;
                }
                // Set active flag to done.
                $contactmergerequest->set('active', 0);
                $contactmergerequest->update();
            }
            // Source has enrolments, no destination enrolments.
            if ($sourceuserhasenrolments && !$destinationuserhasenrolments) {
                if ($destinationcontact) {
                    // Associated source user on destinaction contact.
                    $destinationcontact->set('userid', $sourceuser->get('id'));
                    $destinationcontact->update();

                    $this->removecontacts[] = $sourcecontact;

                    // Suspend destination user.
                    $destinationuser->set('suspended', 1);
                    $destinationuser->update();
                    // Set active flag to done.
                    $contactmergerequest->set('active', 0);
                    $contactmergerequest->update();
                } else {
                    throw new coding_exception('nodestinationcontact');
                }
            }
        }
    }

}
