<?php namespace enrol_arlo\Arlo\AuthAPI\Enum;

/**
 * Class RegistrationContactAttendance
 *
 * Describes the attendance type for a Contact that registered to attend an Event.
 *
 * @package enrol_arlo\Arlo\AuthAPI\Enum
 */
class RegistrationContactAttendance {

    /**
     * @var string ATTENDED The Contact attended the Event.
     */
    const ATTENDED = 'Attended';

    /**
     * @var string DID_NOT_ATTENDED The Contact did not attend the Event.
     */
    const DID_NOT_ATTENDED = 'DidNotAttended';

    /**
     * @var string UNKNOWN No record of attendance is available.
     */
    const UNKNOWN = 'Unknown';

}
