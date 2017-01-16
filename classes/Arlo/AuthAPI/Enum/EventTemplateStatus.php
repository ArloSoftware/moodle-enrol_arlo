<?php

namespace enrol_arlo\Arlo\AuthAPI\Enum;

/**
 * Describes the lifecycle states an EventTemplate resource may be in.
 *
 * Class EventTemplateStatus
 * @package Arlo\AuthAPI\Enum
 */
class EventTemplateStatus {
    /**
     * @var string Describes an EventTemplate that is ready for use.
     */
    const ACTIVE = 'Active';
    /**
     * @var string Describes an EventTemplate that has been created, but with unconfirmed details.
     */
    const DRAFT = 'Draft';
    /**
     * @var string Describes an EventTemplate which has been disabled or archived.
     */
    const INACTIVE = 'Inactive';
    /**
     * @var string Describes an EventTemplate with a state not supported by the API representation.
     */
    const UNKNOWN = 'Unknown';
}
