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
use enrol_arlo\local\administrator_notification;
use enrol_arlo\local\persistent\user_persistent;
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

    /** @var user_persistent[] $suspendedusers */
    protected $suspendedusers;

    /** @var array $stack */
    protected $stack;

    /** @var int $processedcounter */
    public $processedcounter;

    /**
     * Constructor.
     *
     * @param contact_persistent $contact
     */
    public function __construct(contact_persistent $contact) {
        $this->appliedcount = 0;
        $this->initialcontact = $contact;
        $this->removecontacts = [];
        $this->suspendedusers = [];
        $this->stack = [];
    }

    /**
     * Add contact merge requests to processing stack.
     *
     * @param array $contactmergerequests
     * @return array
     */
    protected function add_to_stack($contactmergerequests) {
        if (is_array($contactmergerequests)) {
            foreach ($contactmergerequests as $contactmergerequest) {
                if ($contactmergerequest instanceof contact_merge_request_persistent) {
                    $this->stack[] = $contactmergerequest;
                }
            }
        }
        return $this->stack;
    }

    /**
     * Get active contact merge requests for a contact.
     *
     * @param contact_persistent $contact
     * @return contact_merge_request_persistent[]|false
     * @throws coding_exception
     */
    public function get_active_requests(contact_persistent $contact) {
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
     * Main class for appling contact merge requests.
     *
     * @return bool
     * @throws \enrol_arlo\invalid_persistent_exception
     * @throws coding_exception
     */
    public function apply_all_merge_requests() {
        $this->processedcounter = 0;
        // Load active merge requests into stack for initial contact.
        $this->add_to_stack($this->get_active_requests($this->initialcontact));
        // Process the stack of contact merge requests until none are left.
        while ($this->stack) {
            // Get first contact merge request of top for processing.
            $contactmergerequest = array_shift($this->stack);
            // Set up required destination variables for checking against.
            $destinationcontact = $contactmergerequest->get_destination_contact();
            // Set current destination contact.
            $this->currentdestinationcontact = $destinationcontact;
            $destinationuser = false;
            $destinationuserhasenrolments = false;
            $destinationuserhasaccessedcourses = false;
            if ($destinationcontact) {
                $destinationuser = $destinationcontact->get_associated_user();
                if ($destinationuser) {
                    $destinationuserhasenrolments = $destinationuser->has_course_enrolments();
                    if ($destinationuserhasenrolments) {
                        $destinationuserhasaccessedcourses = $destinationuser->has_accessed_courses();
                    }
                }
            }
            // Set up required source variables for checking against.
            $sourcecontact = $contactmergerequest->get_source_contact();
            $sourceuser = false;
            $sourceuserhasenrolments = false;
            $sourceuserhasaccessedcourses = false;
            if ($sourcecontact) {
                $sourceuser = $sourcecontact->get_associated_user();
                if ($sourceuser) {
                    $sourceuserhasenrolments = $sourceuser->has_course_enrolments();
                    if ($sourceuserhasenrolments) {
                        $sourceuserhasaccessedcourses = $sourceuser->has_accessed_courses();
                    }
                }
            }
            // Start evaluation. Using switch to take advantage of break.
            switch (true) {
                // Both source and destination hav enrolments.
                case ($sourceuserhasenrolments && $destinationuserhasenrolments):
                    $contactmergerequest->set('mergefailed', 1);
                    $contactmergerequest->update();
                    return false;
                // Source doesn't have enrolments, destination does have enrolments.
                case (!$sourceuserhasenrolments && $destinationuserhasenrolments):
                    if ($sourcecontact) {
                        // Add source contact to remove list.
                        $this->removecontacts[$sourcecontact->get('id')] = $sourcecontact;
                        // Suspend source user.
                        if ($sourceuser) {
                            $sourceuser->set('suspended', 1);
                            $sourceuser->update();
                            // Add source user to list for later administrator notifications.
                            $this->suspendedusers[$sourceuser->get('id')] = $sourceuser;
                        }
                    }
                    break;
                // Source has enrolments and destination does not.
                case ($sourceuserhasenrolments && !$destinationuserhasenrolments):
                    // Associated source user on initial destination contact.
                    $this->initialcontact->set('userid', $sourceuser->get('id'));
                    $this->initialcontact->update();
                    // Add source contact to remove list.
                    $this->removecontacts[$sourcecontact->get('id')] = $sourcecontact;
                    if ($destinationcontact) {
                        if ($destinationuser) {
                            // Suspend destination user.
                            $destinationuser->set('suspended', 1);
                            $destinationuser->update();
                            // Add destination user to list for later administrator notifications.
                            $this->suspendedusers[$destinationuser->get('id')] = $destinationuser;
                        }
                    }
                    break;
                // No source or destination user has enrolments.
                case (!$sourceuserhasenrolments && !$destinationuserhasenrolments):
                    if ($sourcecontact) {
                        // Add source contact to remove list.
                        $this->removecontacts[$sourcecontact->get('id')] = $sourcecontact;
                        // Suspend source user.
                        if ($sourceuser) {
                            $sourceuser->set('suspended', 1);
                            $sourceuser->update();
                            // Add source user to list for later administrator notifications.
                            $this->suspendedusers[$sourceuser->get('id')] = $sourceuser;
                        }
                    }
                    break;
                default:
                    return false;
            }
            // Set active flag to done. Reset fail flag.
            $contactmergerequest->set('active', 0);
            $contactmergerequest->set('mergefailed', 0);
            $contactmergerequest->update();
            $this->processedcounter++;
            // We previous contact merge request may have related contacts in current merge request.
            if ($destinationcontact) {
                if ($destinationcontact->get('sourceguid') != $this->initialcontact->get('sourceguid')) {
                    $this->add_to_stack($this->get_active_requests($destinationcontact));
                }
            }
        }
        // Finally remove contacts.
        if ($this->removecontacts) {
            foreach ($this->removecontacts as $contact) {
                // Important safety check, never remove the initial contact.
                if ($contact->get('sourceguid') == $this->initialcontact->get('sourceguid')) {
                    continue;
                }
                $contact->delete();
            }
        }
        // Notify administrators about suspended users.
        if ($this->suspendedusers) {
            foreach ($this->suspendedusers as $suspendeduser) {
                administrator_notification::send_user_account_suspended_message($suspendeduser->to_record());
            }
        }
        // No active requests for current destination contact so exit.
        return true;
    }

}
