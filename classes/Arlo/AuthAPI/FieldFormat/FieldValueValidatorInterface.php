<?php namespace enrol_arlo\Arlo\AuthAPI\FieldFormat;

/**
 * Interface FieldFormatValidatorInterface
 *
 * Use to validate typically string type values getting sent back to Arlo API.
 *
 * @package     enrol_arlo\Arlo\AuthAPI
 * @author      Troy Williams
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface FieldValueValidatorInterface {

    public static function Validate($Value) : bool;

}
