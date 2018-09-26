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

    /** @var int $initialcount */
    protected $initialcount;

    /** @var contact_merge_request_persistent[] $contactmergerequests */
    protected $contactmergerequests;

    protected $lastmergedestinationcontact;

    /**
     * Constructor.
     *
     * @param contact_persistent $contact
     * @throws coding_exception
     */
    public function __construct(contact_persistent $contact) {
        $this->initialcontact = $contact;
        $this->initialcount = $this->count_active_requests();
        $this->appliedcount = 0;
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
            'sourcecreated ASC'
        );
    }

    /**
     * Count of active contact merge requests.
     *
     * @return int
     * @throws coding_exception
     */
    public function count_active_requests() {
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
        return contact_merge_request_persistent::count_records_select(
            $select,
            $conditions,
            'sourcecreated ASC'
        );
    }

    /**
     * Has active requests.
     *
     * @return bool
     * @throws coding_exception
     */
    public function has_active_requests() {
        return ($this->count_active_requests()) ? true : false;
    }

    /**
     * Can all active contact merge requests be merged.
     *
     * @return bool
     * @throws coding_exception
     */
    public function can_merge_all_requests() {
        if (!$this->has_active_requests()) {
            return true;
        }
        foreach ($this->get_active_requests() as $contactmergerequest) {
            if (!$contactmergerequest->can_merge()) {
                return false;
            }
        }
        return true;
    }

    protected function apply_merge_request(contact_merge_request_persistent $contactmergerequest) {
        if (!$contactmergerequest->can_merge()) {
            return false;
        }
        $sourcecontact = $contactmergerequest->get_source_contact();
        $destinationcontact = $contactmergerequest->get_destination_contact();
        // Require both source and destination contacts to apply merge.
        if ($sourcecontact && $destinationcontact) {
            $sourceuser = $sourcecontact->get_associated_user();
            if ($sourceuser) {
                $destinationuser = $destinationcontact->get_associated_user();
                if ($destinationuser) {
                    // Shouldn't get here, but double checking.
                    if ($sourceuser->has_course_enrolments() && $destinationuser->has_course_enrolments()) {
                        return false;
                    } else if (!$sourceuser->has_course_enrolments() && !$destinationuser->has_course_enrolments()) {
                        mtrace('Source no enrolments and destination no enrolments');
                        $sourceuser->set('suspended', 1);
                        $sourceuser->update();
                        //$sourcecontact->delete();
                        $contactmergerequest->set('active', 0);
                        $contactmergerequest->set('sourceuserid', $sourceuser->get('id'));
                        $contactmergerequest->set('destinationuserid', $destinationuser->get('id'));
                        $contactmergerequest->update();
                        ++$this->appliedcount;
                        return true;
                    } else {
                        if ($sourceuser->has_course_enrolments() && !$destinationuser->has_course_enrolments()) {
                            mtrace('Source has enrolments and destination no enrolments');
                            $destinationcontact->set('userid', $sourceuser->get('id'));
                            $destinationcontact->update();
                            $this->initialcontact->set('userid', $sourceuser->get('id'));
                            $destinationcontact->update();
                            //$sourcecontact->delete();
                            $contactmergerequest->set('active', 0);
                            $contactmergerequest->set('sourceuserid', $sourceuser->get('id'));
                            $contactmergerequest->set('destinationuserid', $destinationuser->get('id'));
                            $contactmergerequest->update();
                            ++$this->appliedcount;
                            return true;

                        } else if (!$sourceuser->has_course_enrolments() && $destinationuser->has_course_enrolments()) {
                            mtrace('Source no enrolments and destination nhas enrolments');
                            $sourceuser->set('suspended', 1);
                            $sourceuser->update();
                            //$sourcecontact->delete();
                            $contactmergerequest->set('active', 0);
                            $contactmergerequest->set('sourceuserid', $sourceuser->get('id'));
                            $contactmergerequest->set('destinationuserid', $destinationuser->get('id'));
                            $contactmergerequest->update();
                            ++$this->appliedcount;
                            return true;
                        }
                    }
                } else {
                    $destinationcontact->set('userid', $sourceuser->get('id'));
                    $destinationcontact->update();
                    $sourceuser->set('suspended', 1);
                    $sourceuser->update();
                    $sourcecontact->delete();
                    $contactmergerequest->set('active', 0);
                    $contactmergerequest->set('sourceuserid', $sourceuser->get('id'));
                    $contactmergerequest->update();
                    ++$this->appliedcount;
                    return true;
                }
            } else {
                // No associated source user, remove source contact.
                //$sourcecontact->delete();
                $contactmergerequest->set('active', 0);
                $contactmergerequest->update();
                ++$this->appliedcount;
                return true;
            }
        }
        return false;
    }

    public function apply_all_merge_requests() {
        if (!$this->can_merge_all_requests()) {
            return false;
        }
        foreach ($this->get_active_requests() as $contactmergerequest) {
            $result = $this->apply_merge_request($contactmergerequest);
            if (!$result) {
                return false;
            }
        }
        return true;
    }

}
