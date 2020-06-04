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

namespace enrol_arlo\local\config;

defined('MOODLE_INTERNAL') || die();

use enrol_arlo\local\enum\user_matching;
use enrol_arlo\local\generator\username_generator;

class arlo_plugin_config extends plugin_config {

    /** @var string FRANKEN_NAME Arlo enrolment component */
    const FRANKEN_NAME = 'enrol_arlo';

    /**
     * Plugin settings definition.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'platform' => [
                'type' => PARAM_RAW
            ],
            'apiusername' => [
                'type' => PARAM_RAW
            ],
            'apipassword' => [
                'type' => PARAM_RAW
            ],
            'apistatus' => [
                'type' => PARAM_INT,
                'default' => -1
            ],
            'apitimelastrequest' => [
                'type' => PARAM_INT,
                'default' => 0
            ],
            'apierrormessage' => [
                'type' => PARAM_TEXT,
                'default' => ''
            ],
            'apierrorcounter' => [
                'type' => PARAM_INT,
                'default' => 0
            ],
            'apierrorcountresetdelay' => [
                'type' => PARAM_INT,
                'default' => 10800
            ],
            'matchuseraccountsby' => [
                'type' => PARAM_INT,
                'default' => user_matching::MATCH_BY_DEFAULT
            ],
            'authplugin' => [
                'type' => PARAM_TEXT,
                'default' => 'manual'
            ],
            'roleid' => [
                'type' => PARAM_INT,
                'default' => function() {
                    $student = get_archetype_roles('student');
                    $student = reset($student);
                    return $student->id;
                },
            ],
            'unenrolaction' => [
                'type' => PARAM_INT,
                'default' => ENROL_EXT_REMOVED_UNENROL,
            ],
            'expiredaction' => [
                'type' => PARAM_INT,
                'default' => ENROL_EXT_REMOVED_SUSPEND,
            ],
            'usernameformatorder' => [
                'type' => PARAM_RAW,
                'default' => username_generator::get_default_order()
            ],
            'pushonlineactivityresults' => [
                'type' => PARAM_INT,
                'default' => 1
            ],
            'pusheventresults' => [
                'type' => PARAM_INT,
                'default' => 1
            ],
            'emailsendnewaccountdetails' => [
                'type' => PARAM_INT,
                'default' => 1
            ],
            'emailsendimmediately' => [
                'type' => PARAM_INT,
                'default' => 1
            ],
            'emailprocessingviacli' => [
                'type' => PARAM_INT,
                'default' => 0
            ],
            'allowcompletedevents' => [
                'type' => PARAM_INT,
                'default' => 1
            ],
            'allowcompletedonlineactivities' => [
                'type' => PARAM_INT,
                'default' => 1
            ],
            'allowhiddencourses' => [
                'type' => PARAM_INT,
                'default' => 0
            ],
            'allowoutcomespushing' => [
                'type' => PARAM_INT,
                'default' => 1
            ],
            'requestlogcleanup' => [
                'type' => PARAM_INT,
                'default' => 7
            ],
            'throttlerequests' => [
                'type' => PARAM_INT,
                'default' => 1
            ],
            'allowunenrolactiveenrolmentsui' => [
                'type' => PARAM_INT,
                'default' => 0
            ],
            'allowunenrolaccessedui' => [
                'type' => PARAM_INT,
                'default' => 0
            ],
            'respectallowaccountssameemail' => [
                'type' => PARAM_INT,
                'default' => 0
            ],
            'allowportalintegration' => [
                'type' => PARAM_INT,
                'default' => 1
            ],
            'updatebleregistrationproperties' => [
                'type' => PARAM_TAGLIST,
                'default' => 'LastActivityDateTime,Outcome,Grade,ProgressStatus,ProgressPercent,CompletedDateTime',
                'choices' => [
                    'LastActivityDateTime',
                    'Outcome',
                    'Grade',
                    'ProgressStatus',
                    'ProgressPercent',
                    'CompletedDateTime'
                ]
            ],
            'outcomejobdefaultlimit' => [
                'type' => PARAM_INT,
                'default' => 100
            ],
        ];
    }

    /**
     * Installs plugin configuration defaults.
     *
     * @throws \coding_exception
     */
    public static function install_defaults() {
        $plugin = new static();
        foreach (static::properties_definition() as $property => $settings) {
            $default = static::get_property_default($property);
            if (!is_null($default)) {
                $plugin->raw_set($property, $default);
            }
        }
    }

    /**
     * Custom setter, actually tracks to properties status and error
     * counter.
     *
     * @param $value
     * @throws \coding_exception
     * @throws \dml_exception
     */
    protected function set_apistatus($value) {
        $previousapistatus = $this->raw_get('apistatus');
        $apierrorcounter = $this->raw_get('apierrorcounter');
        switch ($value) {
            case 401;
            case 403:
            case 500:
            case 503:
                if ($previousapistatus == $value) {
                    $this->raw_set('apierrorcounter', ++$apierrorcounter);
                } else {
                    $this->raw_set('apistatus', $value);
                    $this->raw_set('apierrorcounter', 0);
                }
                break;
            default:
                $this->raw_set('apistatus', $value);
                $this->raw_set('apierrorcounter', 0);
        }
    }

    /**
     * Updateble registration properties.
     *
     * @param $value
     * @throws \coding_exception
     */
    protected function set_updatebleregistrationproperties($value) {
        $properties = explode(',', $value);
        $choices = self::get_property_choices('updatebleregistrationproperties');
        $new = [];
        if (!empty($properties)) {
            foreach ($properties as $property) {
                if (in_array($property, $choices)) {
                    $new[] = $property;
                }
            }
        }
        if (empty($new)) {
            $new = explode(',', self::get_property_default('updatebleregistrationproperties'));
        }
        $this->raw_set('updatebleregistrationproperties', implode(',', $new));
    }

}
