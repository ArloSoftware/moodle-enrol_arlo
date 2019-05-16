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
 * Moodle username generator.
 *
 * @package   enrol_arlo
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\generator;

defined('MOODLE_INTERNAL') || die();

use coding_exception;
use core_text;
use stdClass;
use enrol_arlo\local\format\username\username_format_interface;
use enrol_arlo\local\format\username\firstnamelastnamerandomnumber;
use enrol_arlo\local\format\username\emaillocalpart;
use enrol_arlo\local\format\username\emaillocalpartrandomnumber;
use enrol_arlo\local\format\username\email;
use enrol_arlo\local\format\username\emailrandomnumber;

/**
 * Moodle username generator.
 *
 * @package   enrol_arlo
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class username_generator {

    /**
     * @var stdClass $data Data passed to formats.
     */
    protected $data;

    /**
     * @var array $formats indexed array of format classes.
     */
    protected $formats = [];

    /**
     * @var array $options Options passed to formats.
     */
    protected $options = [];

    /**
     * @var string $order The order formats are called.
     */
    protected $order;

    /**
     * username_generator constructor.
     *
     * @param null $data
     * @param string|null $order
     * @param array|null $options
     * @throws coding_exception
     */
    public function __construct($data = null, string $order = null, array $options = null) {
        $this->register_default_formats();
        if (!is_null($data)) {
            $this->add_data($data);
        } else {
            $this->add_data(new stdClass);
        }
        if (!is_null($order)) {
            $this->set_order($order);
        } else {
            $this->set_order(static::get_default_order());
        }
        if (!is_null($options)) {
            $this->add_options($options);
        }
    }

    /**
     * @param $data
     */
    public function add_data($data) {
        $this->data = $data;
    }

    /**
     * @param array $options
     */
    public function add_options(array $options) {
        $this->options = $options;
    }

    /**
     * @return mixed
     */
    public function get_data() {
        return $this->data;
    }

    /**
     * Default order of out of the box formats.
     *
     * @return string
     */
    final public static function get_default_order() {
        return 'firstnamelastnamerandomnumber,emaillocalpart,emaillocalpartrandomnumber,email,emailrandomnumber';
    }

    public function get_format($uniquename) {
        if (isset($this->formats[$uniquename])) {
            return $this->formats[$uniquename];
        }
        return false;
    }

    /**
     * @return array
     */
    public function get_options() {
        return $this->options;
    }

    /**
     * Get order of formats to process, fallback to default order.
     *
     * @param bool $fallbackdefault
     * @return string
     */
    final public function get_order($fallbackdefault = true) {
        if (is_null($this->order) && $fallbackdefault) {
            return static::get_default_order();
        }
        return $this->order;
    }

    /**
     * Creates an ordered array of information about each format. This includes order,
     * shortname, name, and description.
     *
     * @return array
     */
    public function export_current_order_to_array() {
        $data = [];
        $order = 0;
        foreach (explode(',', $this->get_order()) as $formatuniquename) {
            /** @var $format username_format_interface $format */
            $format = $this->get_format($formatuniquename);
            $data[$formatuniquename] = [
                'order' => ++$order,
                'shortname' => $format->get_shortname(),
                'name' => $format->get_name(),
                'description' => $format->get_description(),
            ];
        }
        return $data;
    }

    /**
     * @param $uniquename
     * @return bool
     */
    public function has_format($uniquename) {
        if (isset($this->formats[$uniquename])) {
            return true;
        }
        return false;
    }

    /**
     * Procedual method used to generate username based on ordered list of username formats.
     *
     * @return bool|string
     * @throws \dml_exception
     * @throws coding_exception
     */
    public function generate() {
        global $DB;
        foreach (explode(',', $this->get_order()) as $formatuniquename) {
            /** @var $format username_format_interface $format */
            $format = $this->get_format($formatuniquename);
            $this->validate_required_fields($format->get_required_fields());
            $format->add_data($this->get_data());
            $format->add_options($this->get_options());
            $username = $format->get_username();
            $usernamelower = core_text::strtolower($username);
            $exists = $DB->get_record('user', ['username' => $usernamelower]);
            if (!$exists) {
                return $usernamelower;
            }
        }
        return false;
    }

    /**
     * Static method wrapper used to generate username based on ordered list of username formats.
     *
     * @param $data
     * @param $order
     * @return bool|string
     * @throws \dml_exception
     * @throws coding_exception
     */
    final public static function generate_username($data, $order) {
        $usernamegenerator = new username_generator($data, $order);
        return $usernamegenerator->generate();
    }

    /**
     * Move format one position down the current order.
     *
     * @param $uniquename
     * @return string
     * @throws coding_exception
     */
    final public function move_format_down_order($uniquename) {
        if (!$this->has_format($uniquename)) {
            throw new coding_exception("Format {$uniquename} must be registered used register_format() first");
        }
        $order = explode(',', $this->get_order());
        foreach ($order as $key => $value) {
            if ($key == count($order) && $value == $uniquename) {
                break;
            }
            if ($value == $uniquename) {
                $next = $order[$key + 1];
                $order[$key + 1] = $value;
                $order[$key] = $next;
            }
        }
        $this->set_order(implode(',', $order));
        return $this->get_order();
    }

    /**
     * Move format one position up the current order.
     *
     * @param $uniquename
     * @return string
     * @throws coding_exception
     */
    final public function move_format_up_order($uniquename) {
        if (!$this->has_format($uniquename)) {
            throw new coding_exception("Format {$uniquename} must be registered used register_format() first");
        }
        $order = explode(',', $this->get_order());
        foreach ($order as $key => $value) {
            if ($key == 0 && $value == $uniquename) {
                break;
            }
            if ($value == $uniquename) {
                $prev = $order[$key - 1];
                $order[$key - 1] = $value;
                $order[$key] = $prev;
            }
        }
        $this->set_order(implode(',', $order));
        return $this->get_order();
    }

    /**
     * Register out of the box username formats.
     */
    final protected function register_default_formats() {
        $this->register_format('firstnamelastnamerandomnumber', new firstnamelastnamerandomnumber());
        $this->register_format('emaillocalpart', new emaillocalpart());
        $this->register_format('emaillocalpartrandomnumber', new emaillocalpartrandomnumber());
        $this->register_format('email', new email());
        $this->register_format('emailrandomnumber', new emailrandomnumber());
    }

    /**
     * @param string $order
     * @throws coding_exception
     */
    final public function set_order(string $order) {
        $this->validate_order($order);
        $this->order = $order;
    }

    /**
     * Used to add a username format to the list. Must implement username format interface.
     *
     * @param $uniquename
     * @param username_format_interface $class
     */
    final public function register_format($uniquename, username_format_interface $class) {
        $this->formats[$uniquename] = $class;
    }

    /**
     * Check format classes loaded for passed in order.
     *
     * @param string $order
     * @return bool
     * @throws coding_exception
     */
    final public function validate_order(string $order) {
        $validateorder = explode(',', trim($order));
        if (empty($validateorder )) {
            throw new coding_exception("Empty order");
        }
        foreach ($validateorder as $uniquename) {
            if (!$this->has_format($uniquename)) {
                throw new coding_exception("Format {$uniquename} must be registered used register_format() first");
            }
        }
        return true;
    }

    /**
     * Check if data property has passed in required fields.
     *
     * @param array $requiredfields
     * @throws coding_exception
     */
    public function validate_required_fields(array $requiredfields) {
        foreach ($requiredfields as $requiredfield) {
            if (!isset($this->data->{$requiredfield})) {
                throw new coding_exception("Datafield {$requiredfield} required in data by a formatter");
            }
        }
    }

}
