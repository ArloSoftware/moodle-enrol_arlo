<?php namespace enrol_arlo\Arlo\AuthAPI\Entity;

/**
 * Event Entity.
 *
 * Events represent a scheduled instance of an EventTemplate resource with associated start and end dates,
 * and a collection of Registrations representing contacts who will attend the event.
 *
 * @package enrol_arlo\Arlo\AuthAPI\Entity
 */
class Event {

    private $EventID;

    private $UniqueIdentifier;

    private $Code;

    private $StartDateTime;

    private $FinishDateTime;

    private $StartTimeZoneAbbr;

    private $FinishTimeZoneAbbr;

    private $Description;

    private $LocationName;

    private $ContentUri;

    private $Status;

    private $CreatedDateTime;

    private $LastModifiedDateTime;

    private $CustomFields;

    public function getEventID() {
        return $this->EventID;
    }

    public function getUniqueIdentifier() {
        return $this->UniqueIdentifier;
    }

    public function getCode() {
        return $this->Code;
    }

    public function getStartDateTime() {
        return $this->StartDateTime;
    }

    public function getFinishDateTime() {
        return $this->FinishDateTime;
    }

    public function getStartTimeZoneAbbr() {
        return $this->StartTimeZoneAbbr;
    }

    public function getFinishTimeZoneAbbr() {
        return $this->FinishTimeZoneAbbr;
    }

    public function getDescription() {
        return $this->Description;
    }

    public function getLocationName() {
        return $this->LocationName;
    }

    public function getContentUri() {
        return $this->ContentUri;
    }

    public function getStatus() {
        return $this->Status;
    }

    public function getCreatedDateTime() {
        return $this->CreatedDateTime;
    }

    public function getLastModifiedDateTime() {
        return $this->LastModifiedDateTime;
    }

    public function getCustomFields() {
        return $this->CustomFields;
    }
}