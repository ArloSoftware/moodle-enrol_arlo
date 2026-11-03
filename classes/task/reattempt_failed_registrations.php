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

namespace enrol_arlo\task;

use core\task\adhoc_task;
use enrol_arlo\local\job\memberships_job;
use enrol_arlo\local\persistent\contact_merge_request_persistent;
use enrol_arlo\local\persistent\contact_persistent;

/**
 * Resolves same-user merge failures and re-attempts registrations left failed without a cause.
 *
 * @package   enrol_arlo
 * @copyright 2026 Moodle US
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reattempt_failed_registrations extends adhoc_task {
    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name() {
        return get_string('reattemptfailedregistrationstask', 'enrol_arlo');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/enrol/arlo/lib.php');
        if (!enrol_is_enabled('arlo')) {
            return;
        }
        $failedmerges = contact_merge_request_persistent::get_records(['active' => 1, 'mergefailed' => 1]);
        foreach ($failedmerges as $contactmergerequest) {
            if ($contactmergerequest->resolves_to_same_user()) {
                $contactmergerequest->set('active', 0);
                $contactmergerequest->set('mergefailed', 0);
                $contactmergerequest->update();
            }
        }
        $sql = "SELECT DISTINCT eac.id
                  FROM {enrol_arlo_registration} ear
                  JOIN {enrol_arlo_contact} eac ON eac.sourceguid = ear.sourcecontactguid
                 WHERE ear.enrolmentfailure = 1
                   AND eac.userassociationfailure = 0
                   AND eac.usercreationfailure = 0
                   AND NOT EXISTS (
                       SELECT 1
                         FROM {enrol_arlo_contactmerge} eacmr
                        WHERE eacmr.destinationcontactguid = eac.sourceguid
                          AND eacmr.mergefailed = 1
                   )";
        foreach ($DB->get_fieldset_sql($sql) as $contactid) {
            $contact = new contact_persistent($contactid);
            $result = memberships_job::reattempt_failed_registrations($contact);
            mtrace("Contact {$contact->get('sourceid')}: " . ($result ? 'enrolled' : 'still failing'));
        }
    }
}
