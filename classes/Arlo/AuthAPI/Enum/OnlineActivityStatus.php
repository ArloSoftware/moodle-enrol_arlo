<?php

namespace enrol_arlo\Arlo\AuthAPI\Enum;

/**
 * Describes the lifecycle states an OnlineActivity resource may be in.
 *
 * Class OnlineActivityStatus
 * @package Arlo\AuthAPI\Enum
 */
class OnlineActivityStatus {
    /**
     * @var string Describes an OnlineActivity that is available.
     */
    const ACTIVE = 'Active';
    /**
     * @var string Describes an OnlineActivity that has been archived and is no longer available.
     */
    const ARCHIVED = 'Archived';
    /**
     * @var string Describes an OnlineActivity that has been marked by the systems administrator as completed.
     */
    const COMPLETED = 'Completed';
    /**
     * @var string Describes an OnlineActivity that has been created, but with unconfirmed details.
     * Events in this state are not published and cannot accept registrations.
     */
    const DRAFT = 'Draft';
    /**
     * @var string Describes an OnlineActivity with a state not supported by the API representation.
     */
    const UNKNOWN = 'Unknown';
}
