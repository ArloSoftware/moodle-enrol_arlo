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

use coding_exception;

abstract class plugin_config {

    /** The franken name of component/plugin. */
    const FRANKEN_NAME = null;

    /**
     * Config property getter.
     *
     * This is the main getter for all the properties. Developers can implement their own getters (get_propertyname)
     * and they will be called by this function. Custom getters can use raw_get to get the raw value.
     *
     * @param $property
     * @return mixed|null
     * @throws coding_exception
     */
    final public function get($property) {
        if (!static::has_property($property)) {
            throw new coding_exception("Unexpected property {$property} requested.");
        }
        $methodname = 'get_' . $property;
        if (method_exists($this, $methodname)) {
            return $this->$methodname();
        }
        return $this->raw_get($property);
    }

    /**
     * Internal config property getter.
     *
     * @param $property
     * @return mixed|null
     * @throws \dml_exception
     * @throws coding_exception
     */
    final protected function raw_get($property) {
        if (!static::has_property($property)) {
            throw new coding_exception("Unexpected property {$property} requested.");
        }
        if (is_null(static::FRANKEN_NAME)) {
            throw new coding_exception("Franken name has not been set on child");
        }
        $value = get_config(static::FRANKEN_NAME, $property);
        if ($value === false) {
            return static::get_property_default($property);
        }
        return $value;
    }

    /**
     * Config property setter.
     *
     * This is the main setter for all the properties. Developers can implement their own setters (set_propertyname)
     * and they will be called by this function.
     *
     * @param $property
     * @param $value
     * @return $this|plugin_config
     * @throws coding_exception
     */
    final public function set($property, $value) {
        if (!static::has_property($property)) {
            throw new coding_exception("Unexpected property {$property} requested.");
        }
        $methodname = 'set_' . $property;
        if (method_exists($this, $methodname)) {
            $this->$methodname($value);
            return $this;
        }
        return $this->raw_set($property, $value);
    }

    /**
     * Internal config property setter.
     *
     * @param $property
     * @param $value
     * @return $this
     * @throws coding_exception
     */
    final protected function raw_set($property, $value) {
        if (!static::has_property($property)) {
            throw new coding_exception("Unexpected property {$property} requested.");
        }
        if (is_null(static::FRANKEN_NAME)) {
            throw new coding_exception("Franken name has not been set on child");
        }
        set_config($property, $value, static::FRANKEN_NAME);
        return $this;
    }

    /**
     * Return the custom definition of the properties.
     *
     * Each property MUST be listed here.
     *
     * The result of this method is cached internally for the whole request.
     *
     * The 'default' value can be a Closure when its value may change during a single request.
     * For example if the default value is based on a $CFG property, then it should be wrapped in a closure
     * to avoid running into scenarios where the true value of $CFG is not reflected in the definition.
     * Do not abuse closures as they obviously add some overhead.
     *
     * Examples:
     *
     * array(
     *     'property_name' => array(
     *         'type' => PARAM_TYPE,                // Mandatory.
     *         'default' => 'Default value',
     *         'choices' => array(1, 2, 3)          // An array of accepted values.
     *     )
     * )
     *
     * array(
     *     'dynamic_property_name' => array(
     *         'default' => function() {
     *             return $CFG->something;
     *         },
     *         'type' => PARAM_INT,
     *     )
     * )
     *
     * @return array
     */
    protected static function define_properties() {
        return array();
    }


    /**
     * Gets the choices for a property.
     *
     * @param $property
     * @return array
     */
    final public static function get_property_choices($property) {
        $properties = static::properties_definition();
        if (!isset($properties[$property]['choices'])) {
            return array();
        }
        $choices = $properties[$property]['choices'];
        if ($choices instanceof \Closure) {
            return $choices();
        }
        return $choices;
    }

    /**
     * Gets the default value for a property.
     *
     * @param $property
     * @return mixed|null
     */
    final public static function get_property_default($property) {
        $properties = static::properties_definition();
        if (!isset($properties[$property]['default'])) {
            return null;
        }
        $value = $properties[$property]['default'];
        if ($value instanceof \Closure) {
            return $value();
        }
        return $value;
    }

    /**
     * Returns whether or not a property was defined.
     *
     * @param $property
     * @return bool
     */
    final public static function has_property($property) {
        $properties = static::properties_definition();
        return isset($properties[$property]);
    }

    /**
     * Get the settings/properties definition for plugin.
     *
     * @return array|null
     */
    final public static function properties_definition() {
        static $definition = null;
        if (is_null($definition)) {
            $definition = static::define_properties();
            if (empty($definition)) {
                throw new coding_exception("No property definition");
            }
        }
        return $definition;
    }
}
