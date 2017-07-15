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
 * The enrol_arlo field updated event.
 *
 * @package     enrol_arlo
 * @author      Mathew May
 * @copyright   2017 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The enrol_arlo field updated event class.
 *
 * @property-read array $other {
 *      Extra information about event.
 *
 *      - string name: The name of the field updated
 *      - string oldvalue: The previous value of the field
 *      - string newvalue: The new value of the field
 * }
 *
 * @package    enrol_arlo
 * @since      Moodle 3.1
 * @author     Mathew May
 * @copyright  2017 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class onlineactivity_updated extends \core\event\base {

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['objecttable'] = 'data_fields';
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('onlineactivityupdated', 'enrol_arlo');
    }

    /**
     * Returns description of what happened.
     * @return string
     */
    public function get_description() {
        return "Updated Arlo Online Activity record '" . $this->other['id'] . "'.";
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/admin/settings.php', array('section' => 'enrolsettingsarlo'));
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception when validation does not pass.
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['id'])) {
            throw new \coding_exception("The id value must be set in other.");
        }
        if (!isset($this->other['sourceid'])) {
            throw new \coding_exception("The sourceid value must be set in other.");
        }
        if (!isset($this->other['sourceguid'])) {
            throw new \coding_exception("The sourceguid value must be set in other.");
        }
        if (!isset($this->other['sourcestatus'])) {
            throw new \coding_exception("The sourcestatus value must be set in other.");
        }
        if (!isset($this->other['sourcetemplateid'])) {
            throw new \coding_exception("The sourcetemplateid value must be set in other.");
        }
        if (!isset($this->other['sourcetemplateguid'])) {
            throw new \coding_exception("The sourcetemplateguid value must be set in other.");
        }
    }

    public static function get_objectid_mapping() {
        return array('db' => 'data_fields', 'restore' => 'data_field');
    }
}
