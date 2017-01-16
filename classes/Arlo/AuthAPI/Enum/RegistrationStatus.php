<?php

namespace enrol_arlo\Arlo\AuthAPI\Enum;

/**
 * Describes the lifecycle states a Registration resource may be in.
 *
 * Class RegistrationStatus
 * @package Arlo\AuthAPI\Enum
 */
class RegistrationStatus {
    /**
     * @var string Describes a Registration which has been approved/confirmed for a future Event.
     */
    const APPROVED = 'Approved';
    /**
     * @var string Describes a Registration which has been cancelled.
     */
    const CANCELLED = 'Cancelled';
    /**
     * @var string Describes a Registration associated with an Event which has now completed.
     */
    const COMPLETED = 'Completed';
    /**
     * @var string Describes a provisional Registration which has been created, but is pending approval based on the
     * state of another resource (such as an Order awaiting payment), or an action from an administrator.
     */
    const PENDING_APPROVAL= 'PendingApproval';
    /**
     * @var string Describes a Registration with a state not supported by the API representation.
     */
    const UNKNOWN = 'Unknown';
}
