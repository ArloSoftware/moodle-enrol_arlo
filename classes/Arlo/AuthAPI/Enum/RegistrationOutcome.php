<?php

namespace enrol_arlo\Arlo\AuthAPI\Enum;

/**
 * Describes the outcome (pass/fail) states a Registration resource may be in.
 *
 * Class RegistrationOutcome
 * @package Arlo\AuthAPI\Enum
 */
class RegistrationOutcome {
    /**
     * @var string Describes a Registration with a fail result.
     */
    const FAIL = 'Fail';
    /**
     * @var string Describes a Registration with a pass result.
     */
    const PASS = 'Pass';
    /**
     * @var string Describes a Registration with a result not supported by the API representation.
     */
    const UNKNOWN = 'Unknown';
}
