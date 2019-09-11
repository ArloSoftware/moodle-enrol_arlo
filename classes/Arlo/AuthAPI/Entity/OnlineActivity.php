<?php namespace enrol_arlo\Arlo\AuthAPI\Entity;

use UnexpectedValueException;

/**
 * OnlineActivity Entity Class
 *
 * Online activities represent an online instance of an EventTemplate resource, and a
 * collection of Registrations representing contacts who will undertake the event.
 *
 * @package enrol_arlo\Arlo\AuthAPI\Entity
 */

class OnlineActivity {

    private $OnlineActivityID;

    private $UniqueIdentifier;

    private $Code;

    private $Name;

    private $DeliveryDescription;

    private $ContentUri;

    private $Status;

    private $CreatedDateTime;

    private $LastModifiedDateTime;

    private $CustomFields;
}
