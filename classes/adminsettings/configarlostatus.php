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
 * Arlo enrolment plugin settings and presets.
 *
 * Things that are accessable:
 *  - $ADMIN = $adminroot;
 *  - $plugininfo = The Arlo enrolment plugin class;
 *  - $enrol = The Arlo enrolment plugin class;
 *
 * @package     enrol_arlo
 * @author      Mathew May
 * @copyright   2017 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\adminsettings;

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/adminlib.php");
// require_once($CFG->dirroot . '/adminlib.php');

/**
 * Displays current Arlo API status in admin settings page.
 */
class configarlostatus extends \admin_setting {
    public function __construct($name, $visiblename) {
        $this->nosave = true;
        parent::__construct($name, $visiblename, '', '');
    }
    /**
     * Always returns true
     * @return bool Always returns true
     */
    public function get_setting() {
        return true;
    }
    /**
     * Always returns true
     * @return bool Always returns true
     */
    public function get_defaultsetting() {
        return true;
    }
    /**
     * Never write settings
     * @return string Always returns an empty string
     */
    public function write_setting($data) {
        // Do not write any setting.
        return '';
    }

    /**
     * Output the current Arlo API status.
     *
     * @param mixed $data
     * @param string $query
     * @return string
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function output_html($data, $query = '') {
        global $OUTPUT;
        $apistatus = get_config('enrol_arlo', 'apistatus');
        $apilastrequested = (int) get_config('enrol_arlo', 'apitimelastrequest');
        $useimageiconclass = false;
        if (class_exists('image_icon')) {
            $useimageiconclass = true;
        }
        $statusicon = '';
        $reason = '';
        $description = '';
        if (200 == $apistatus) {
            if ($useimageiconclass) {
                $statusicon = $OUTPUT->image_icon('t/go', get_string('ok', 'enrol_arlo'));
            } else {
                $statusicon = $OUTPUT->pix_icon('t/go', get_string('ok', 'enrol_arlo'));
            }
            $reason = get_string('apistatusok', 'enrol_arlo', userdate($apilastrequested));
            $description = '';
        } else if (0 == $apistatus || ($apistatus >= 400 && $apistatus < 499)) {
            if ($useimageiconclass) {
                $statusicon = $OUTPUT->image_icon('t/stop', get_string('notok', 'enrol_arlo'));
            } else {
                $statusicon = $OUTPUT->pix_icon('t/stop', get_string('notok', 'enrol_arlo'));
            }
            $reason = get_string('apistatusclienterror', 'enrol_arlo');
            $url = new \moodle_url('/enrol/arlo/admin/apirequests.php');
            $description = get_string('pleasecheckrequestlog', 'enrol_arlo', $url->out());
        } else if ($apistatus >= 500 && $apistatus < 599) {
            if ($useimageiconclass) {
                $statusicon = $OUTPUT->image_icon('t/stop', get_string('notok', 'enrol_arlo'));
            } else {
                $statusicon = $OUTPUT->pix_icon('t/stop', get_string('notok', 'enrol_arlo'));
            }
            $reason = get_string('apistatusservererror', 'enrol_arlo');
            $url = new moodle_url('/enrol/arlo/admin/apirequests.php');
            $description = get_string('pleasecheckrequestlog', 'enrol_arlo', $url->out());
        } else {
            return '';
        }
        $element = '<div class="form-text">'.$statusicon.'&nbsp;'.$reason.'</div>';
        return format_admin_setting($this, '', $element, $description, false, '', null, $query);
    }
}
