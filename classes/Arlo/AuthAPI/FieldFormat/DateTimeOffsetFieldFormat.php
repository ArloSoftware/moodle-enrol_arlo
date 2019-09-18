<?php namespace enrol_arlo\Arlo\AuthAPI\FieldFormat;

/**
 * DateTimeOffset field format.
 *
 * @package     enrol_arlo\Arlo\AuthAPI\FieldFormat
 * @author      Troy Williams
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class DateTimeOffsetFieldFormat implements FieldValueValidatorInterface {

    /**
     * @link https://developer.arlo.co/doc/api/2012-02-01/auth/datetimeformats
     *
     * DateTimeOffset values are expressed as in the format yyyy-mm-ddThh:mm[:ss[.ffffff]]zzzz.
     *
     * Example: 2009-07-22T12:00:00.0000000+12:00
     *
     * @param $Value
     * @return bool
     */
    public static function Validate($Value) : bool {
        $pattern = '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}(:[0-9]{2}.[0-9]{3,7})?[+-][0-9]{2}:[0-9]{2}$/i';
        if (preg_match($pattern, $Value)) {
            return true;
        }
        return false;
    }
}