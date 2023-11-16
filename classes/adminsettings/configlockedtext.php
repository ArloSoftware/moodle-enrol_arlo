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

/**
 * Locked text field, allows unlocking of text to edit
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class configlockedtext extends \admin_setting_configtext {
    /**
     * Constructor
     * @param string $name unique name, 'mysetting' for settings that in config, 'myplugin/mysetting' for ones in config_plugins.
     * @param string $visiblename localised
     * @param string $description long localised info
     * @param string $defaultsetting default password
     */
    public function __construct($name, $visiblename, $description, $defaultsetting) {
        parent::__construct($name, $visiblename, $description, $defaultsetting, PARAM_RAW, 30);
    }

    /**
     * Returns XHTML for the field
     * Writes Javascript into the HTML below right before the last div
     *
     * @todo Make javascript available through newer methods if possible
     * @param string $data Value for the field
     * @param string $query Passed as final argument for format_admin_setting
     * @return string XHTML field
     */
    public function output_html($data, $query='') {
        $id = $this->get_id();
        $unmask = get_string('unlock', 'enrol_arlo');
        $unmaskjs = '<script type="text/javascript">
        //<![CDATA[
        var is_ie = (navigator.userAgent.toLowerCase().indexOf("msie") != -1);
        var textbox = document.getElementById("'.$id.'");
        textbox.setAttribute("autocomplete", "off");
        if (textbox.value != "") {
            textbox.setAttribute("readonly", "readonly");
        }
        var unmaskdiv = document.getElementById("'.$id.'unmaskdiv");
        var unmaskchb = document.createElement("input");
        unmaskchb.setAttribute("type", "checkbox");
        unmaskchb.setAttribute("id", "'.$id.'unmask");
        unmaskchb.onchange = function() {
            document.getElementById("id_s_enrol_arlo_platform").readOnly ^= true;
        };
        unmaskdiv.appendChild(unmaskchb);
        var unmasklbl = document.createElement("label");
        unmasklbl.innerHTML = "'.addslashes_js($unmask).'";
        if (is_ie) {
          unmasklbl.setAttribute("htmlFor", "'.$id.'unmask");
        } else {
          unmasklbl.setAttribute("for", "'.$id.'unmask");
        }
        unmaskdiv.appendChild(unmasklbl);
        if (is_ie) {
          // ugly hack to work around the famous onchange IE bug
          unmaskchb.onclick = function() {this.blur();};
          unmaskdiv.onclick = function() {this.blur();};
        }
        //]]>
        </script>';
        $html = '<div class="form-text">
                 <input type="text" class="form-control text-ltr"vsize=" '.$this->size.'" id="'.$id.'" name="'.$this->get_full_name().'" value="'.s($data).'" />
                 <div class="unmask" id="'.$id.'unmaskdiv">
                 </div>'.$unmaskjs.'</div>';
        return format_admin_setting($this, $this->visiblename, $html,
            $this->description, true, '', null, $query);
    }

    /**
     * Extent in order to trigger event.
     *
     * @param $data
     * @return mixed|string
     * @throws coding_exception
     * @throws dml_exception
     */
    public function write_setting($data) {
        // Fix user input for Arlo platform URL.
        global $USER;
        $replace = '/^(https:\/\/)' . '|' . // Matches leading https://
                   '^(http:\/\/)'   . '|' . // Matches leading http://
                   '\/$/'                   // Matches trailing /
        ;
        if (preg_match($replace, $data)) {
            $data = preg_replace($replace, "", $data);
            // Notify user that their input was altered to adhere to standards
            $message = new \core\message\message();
            $message->component = 'enrol_arlo';
            $message->name = 'administratornotification';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $USER->id;
            $message->subject = get_string('platform_bad_input_domain_subject', 'enrol_arlo');
            $message->fullmessage = get_string('platform_bad_input_domain', 'enrol_arlo');
            $message->fullmessageformat = FORMAT_MARKDOWN;
            $message->fullmessagehtml = '<p>' . get_string('platform_bad_input_domain', 'enrol_arlo') . '</p>';
            $message->smallmessage = get_string('platform_bad_input_domain_small', 'enrol_arlo');
            $messageid = message_send($message);
        }

        $name = $this->name;
        $oldvalue = $this->get_setting();
        $newvalue = $data;
        $return = parent::write_setting($data);
        // Reset API flags.
        set_config('apistatus', -1, 'enrol_arlo');
        set_config('apitimelastrequest', 0, 'enrol_arlo');
        set_config('apierrormessage', '', 'enrol_arlo');
        set_config('apierrorcounter', 0, 'enrol_arlo');
        // Trigger an event for updating this field.
        if (!empty($oldvalue)) {
            $event = \enrol_arlo\event\fqdn_updated::create(array(
                'objectid' => 1,
                'context' => \context_system::instance(),
                'other' => array(
                    'name' => $name,
                    'oldvalue' => $oldvalue,
                    'newvalue' => $newvalue
                )
            ));
            $event->trigger();
        }
        return $return;
    }

    /**
     * Validate FQDN - host.
     *
     * @param $data
     * @return bool|mixed|string
     * @throws coding_exception
     */
    public function validate($data) {
        // Fix user input for Arlo platform URL.
        $protocol = '/^(https:\/\/)' . '|' . // Matches leading https://
                   '^(http:\/\/)'   . '|' . // Matches leading http://
                   '\/$/'                   // Matches trailing /
        ;
        if (preg_match($protocol, $data)) {
            return get_string('validateerror', 'admin');
        }

        $cleaned = clean_param($data, PARAM_HOST);
        if (empty($cleaned)) {
            return get_string('validateerror', 'admin');
        }
        return true;
    }
}
