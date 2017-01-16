<?php

namespace enrol_arlo\Arlo\AuthAPI\Enum;

/**
 * Describes the lifecycle states an Event resource may be in.
 *
 * Class EventStatus
 * @package Arlo\AuthAPI\Enum
 */
class EventStatus {
    /**
     * @var string Describes an Event that is scheduled, or in progress, with a FinishDateTime in the future.
     */
    const ACTIVE = 'Active';
    /**
     * @var string Describes an Event that has been cancelled.
     */
    const CANCELLED = 'Cancelled';
    /**
     * @var string Describes an Event with a FinishDateTime that has now elapsed.
     */
    const COMPLETED = 'Completed';
    /**
     * @var string Describes an Event that has been created, but with unconfirmed details.
     * Events in this state are not published and cannot accept registrations.
     */
    const DRAFT = 'Draft';
    /**
     * @var string Describes an Event with a state not supported by the API representation.
     */
    const UNKNOWN = 'Unknown';
}
