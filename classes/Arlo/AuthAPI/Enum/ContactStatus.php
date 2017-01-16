<?php

namespace enrol_arlo\Arlo\AuthAPI\Enum;

/**
 * Describes the status of Contact resources.
 *
 * Class ContactStatus
 * @package Arlo\AuthAPI\Enum
 */
class ContactStatus {
    /*
     * @var string Describes a Contact that is currently active.
     */
    const ACTIVE = 'Active';
    /*
     * @var string Describes a Contact that has been archived.
     */
    const INACTIVE = 'Inactive';
    /*
     * @var string Describes a Contact that has a state not currently supported by the API representation.
     */
    const UNKNOWN = 'Unknown';
}
