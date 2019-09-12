<?php namespace enrol_arlo\Arlo\AuthAPI\FieldFormat;

/**
 *
 * @package
 * @author      Troy Williams
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class DateTimeFieldFormat implements FieldValueValidatorInterface {

    public static function Validate($Value) :bool {
        return true;
    }
}