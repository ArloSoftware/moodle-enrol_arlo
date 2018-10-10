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
 * Card for contact and associated user and courses.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\output;

defined('MOODLE_INTERNAL') || die();

use enrol_arlo\api;
use enrol_arlo\local\persistent\contact_persistent;
use moodle_url;
use renderable;
use renderer_base;
use templatable;
use stdClass;

/**
 * Card for contact and associated user and courses.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contact implements renderable, templatable {

    /** @var contact_persistent */
    protected $contact;

    /** @var string Source or destination. */
    protected $contactmergeposition;

    public function __construct(contact_persistent $contact, $contactmergeposition = null) {
        $this->contact = $contact;
        $this->contactmergeposition = $contactmergeposition;
    }

    /**
     * Export the data.
     *
     * @param renderer_base $output
     * @return array|stdClass
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function export_for_template(renderer_base $output) {
        $contact = $this->contact;
        $user = $contact->get_associated_user();
        if (moodle_major_version() < 3.4) {
            $enrolurl = new moodle_url('/enrol/users.php');
        } else {
            $enrolurl = new moodle_url('/user/index.php');
        }
        $pluginconfig = api::get_enrolment_plugin()->get_plugin_config();
        $allowunenrolaccessed = $pluginconfig->get('allowunenrolaccessedui');
        $data = new stdClass();
        if ($this->contactmergeposition) {
            if ($this->contactmergeposition == 'source') {
                $data->title = get_string('sourcecontact', 'enrol_arlo');
            }
            if ($this->contactmergeposition == 'destination') {
                $data->title = get_string('destinationcontact', 'enrol_arlo');
            }
        }
        $data->contactfirstname = $contact->get('firstname');
        $data->contactlastname = $contact->get('lastname');
        $data->contactfullname = $data->contactfirstname . ' ' . $data->contactlastname;
        $data->contactemail = $contact->get('email');
        $data->contactcodeprimary = $contact->get('codeprimary');
        if ($user) {
            $data->hasuser = true;
            $data->userfirstname = $user->get('firstname');
            $data->userlastname = $user->get('lastname');
            $data->userfullname = $data->userfirstname . ' ' . $data->userlastname;
            $data->useremail = $user->get('email');
            $data->useridnumber = $user->get('idnumber');
            $userprofileurl = new moodle_url('/user/profile.php', ['id' => $user->get('id')]);
            $data->userprofileurl = $userprofileurl->out(false);
            $allcourses = $user->get_enrolled_courses(false, false);
            $arlocourses = $user->get_enrolled_courses(false, true);
            if ($allcourses) {
                $data->hascourses = true;
                $hasaccessed = false;
                $data->courses = [];
                foreach ($allcourses as $course) {
                    if (in_array($course->id, array_keys($arlocourses))) {
                        $course->arloenrolment = true;
                        $enrolurl->param('id', $course->id);
                        $course->enrolurl = $enrolurl->out(false);
                        if (!is_null($course->timeaccess)) {
                            $hasaccessed = true;
                        }
                    }
                    $outlineurl = new moodle_url('/report/outline/user.php', ['id' => $user->get('id'), 'course' => $course->id]);
                    $course->usercourseoutlineurl = $outlineurl->out(false);
                    $data->courses[] = $course;
                }
                $unenrolurl = new moodle_url('/enrol/arlo/admin/unenrolcontact.php', ['id' => $contact->get('id')]);
                if ($hasaccessed) {
                    if ($allowunenrolaccessed) {
                        $data->unenrolurl = $unenrolurl->out();
                    }
                } else {
                    $data->unenrolurl = $unenrolurl->out();
                }
            }
        }
        return $data;
    }
}