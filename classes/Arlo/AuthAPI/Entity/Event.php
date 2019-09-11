<?php namespace enrol_arlo\Arlo\AuthAPI\Entity;

use UnexpectedValueException;

/**
 * Event Entity.
 *
 * Events represent a scheduled instance of an EventTemplate resource with associated start and end dates,
 * and a collection of Registrations representing contacts who will attend the event.
 *
 * @package enrol_arlo\Arlo\AuthAPI\Entity
 */
class Event {

    /** @var int $EventID */
    private $EventID;

    /** @var string $UniqueIdentifier GUID value represented as a VARCHAR(36) */
    private $UniqueIdentifier;

    /** @var string $Code */
    private $Code;

    /** @var string $StartDateTime */
    private $StartDateTime;

    /** @var string $FinishDateTime */
    private $FinishDateTime;

    /** @var string $StartTimeZoneAbbr */
    private $StartTimeZoneAbbr;

    /** @var string $FinishTimeZoneAbbr */
    private $FinishTimeZoneAbbr;

    /** @var string $Description */
    private $Description;

    /** @var string $LocationName */
    private $LocationName;

    /** @var string $ContentUri */
    private $ContentUri;

    /** @var string $Status */
    private $Status;

    /** @var string $CreatedDateTime */
    private $CreatedDateTime;

    /** @var string $LastModifiedDateTime */
    private $LastModifiedDateTime;

    /** @var CustomFields $CustomFields */
    private $CustomFields;

    /** @var EventTemplate $EventTemplate */
    private $EventTemplate;

    /**
     * @var array $Links Related resource links.
     */
    private $Links = [];

    public function addLink(Link $Link) {
        $this->Links[] = $Link;
    }

    /**
     * A integer value that uniquely identifies this resource within the platform.
     *
     * @return int
     */
    public function getEventID() {
        return $this->EventID;
    }

    /**
     * A GUID value that uniquely identifies this resource across any platform.
     *
     * @return string
     */
    public function getUniqueIdentifier() {
        return $this->UniqueIdentifier;
    }

    /**
     * A string representing the short code used when referring to this event, up to 32 characters long.
     * By default this value is based off the EventTemplate Code with a unique integer ID appended.
     * For example, MGMT101-001 for the first Event instance based off a template with code MGMT101.
     *
     * @return string
     */
    public function getCode() {
        return $this->Code;
    }

    /**
     * A DateTimeOffset value indicating when the first (chronological) session associated with this event starts.
     *
     * @return string
     */
    public function getStartDateTime() {
        return $this->StartDateTime;
    }

    /**
     * 	A DateTimeOffset value indicating when the last (chronological) session associated with this event finishes.
     *
     * @return string
     */
    public function getFinishDateTime() {
        return $this->FinishDateTime;
    }

    /**
     * The timezone abbreviation respecting daylight savings constraints corresponding to the StartDateTime.
     *
     * @return string
     */
    public function getStartTimeZoneAbbr() {
        return $this->StartTimeZoneAbbr;
    }

    /**
     * The timezone abbreviation respecting daylight savings constraints corresponding to the FinishDateTime.
     *
     * @return string
     */
    public function getFinishTimeZoneAbbr() {
        return $this->FinishTimeZoneAbbr;
    }

    /**
     * A string describing the location where this event will run, up to 256 characters long.
     * For venue-based events, this property is defaults to the name of the city where the first session is hosted.
     * For online-based events, this property defaults to the string Online.
     *
     * @return string
     */
    public function getLocationName() {
        return $this->LocationName;
    }

    /**
     * A string describing the sessions for the Event, up to 128 characters long. This description is useful for
     * describing the session pattern for long-running events and a default description can be generated when the Event
     * is scheduled. For example: 9 weeks, Thu 12:00 PM“ 1:30 PM describes an Event that spans nine weeks, running one
     * session every week on Thursday afternoon for two hours. For short or single-session events, this property is
     * usually blank.
     *
     * @return string
     */
    public function getDescription() {
        return $this->Description;
    }

    /**
     * A string value representing a URL referring to the content for Event up to 256 characters long. Relevant for
     * Events associated with e-learning.
     *
     * @return string
     */
    public function getContentUri() {
        return $this->ContentUri;
    }

    /**
     * An EventStatus value representing the current state of this event, such as draft, active, completed or cancelled.
     *
     * @return string
     */
    public function getStatus() {
        return $this->Status;
    }

    /**
     * A UTC DateTime value indicating when this resource was created.
     *
     * @return string
     */
    public function getCreatedDateTime() {
        return $this->CreatedDateTime;
    }

    /**
     * A UTC DateTime value indicating when this resource was last modified.
     *
     * @return string
     */
    public function getLastModifiedDateTime() {
        return $this->LastModifiedDateTime;
    }

    /**
     * CustomFields associated with this Event.
     * @return CustomFields
     */
    public function getCustomFields() {
        return $this->CustomFields;
    }

    /**
     * EventTemplate that this Event is based on.
     *
     * @return EventTemplate
     */
    public function getEventTemplate() {
        return $this->EventTemplate;
    }

    /**
     * Check value beings set is record ID greater than 0.
     *
     * @param int $Value
     * @return $this
     */
    public function setEventID(int $Value) {
        if ($Value <= 0) {
            throw new UnexpectedValueException("EventID must be an integer greater than Zero");
        }
        $this->EventID = $Value;
        return $this;
    }

    /**
     * Check value being set is ahexadecimal (base-16) digits, displayed in 5 groups separated by hyphens,
     * in the form 8-4-4-4-12 for a total of 36 characters.
     *
     * @param string $Value
     * @return $this
     */
    public function setUniqueIdentifier(string $Value) {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $Value)) {
            throw new UnexpectedValueException("UniqueIdentifier must be a GUID string");
        }
        $this->UniqueIdentifier = $Value;
        return $this;
    }

}
