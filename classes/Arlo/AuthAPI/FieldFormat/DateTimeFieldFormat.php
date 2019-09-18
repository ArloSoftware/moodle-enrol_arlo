<?php namespace enrol_arlo\Arlo\AuthAPI\FieldFormat;

/**
 * DateTime field format.
 *
 * @package     enrol_arlo\Arlo\AuthAPI\FieldFormat
 * @author      Troy Williams
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class DateTimeFieldFormat implements FieldValueValidatorInterface {

    /**
     * @link https://developer.arlo.co/doc/api/2012-02-01/auth/datetimeformats
     *
     * DateTime values are expressed as normalized UTC values in the format yyyy-mm-ddThh:mm[:ss[.fff]]Z.
     *
     * Example: 2010-03-23T09:25:12.3130000Z
     *
     * @param $Value
     * @return bool
     */
    public static function Validate($Value) :  bool  {
        if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}(:[0-9]{2}.[0-9]{3,7})?Z$/i', $Value)) {
            return true;
        }
        return false;
    }
}
