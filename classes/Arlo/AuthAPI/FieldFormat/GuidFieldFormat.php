<?php namespace enrol_arlo\Arlo\AuthAPI\FieldFormat;

/**
 * Class GuidFieldFormat
 *
 * Globally Unique Identifer use in Arlo to uniquely identify any resources across any platform.
 *
 * @package     enrol_arlo\Arlo\AuthAPI
 * @author      Troy Williams
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class GuidFieldFormat implements FieldValueValidatorInterface {

    /**
     * Check value being set is a hexadecimal (base-16) digits, displayed in 5 groups separated by hyphens,
     * in the form 8-4-4-4-12 for a total of 36 characters.
     *
     * @param $Value
     * @return bool
     */
    public static function Validate($Value) : bool {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $Value)) {
            return true;
        }
        return false;
    }

}
