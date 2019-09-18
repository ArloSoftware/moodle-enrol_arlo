<?php namespace enrol_arlo\Arlo\AuthAPI\Entity;

use enrol_arlo\Arlo\AuthAPI\FieldFormat\DateTimeFieldFormat;
use enrol_arlo\Arlo\AuthAPI\FieldFormat\GuidFieldFormat;
use UnexpectedValueException;

/**
 * Registration Entity Class
 *
 * A Registration represents a request from a Contact to attend an Event or Online Activity.
 *
 * @package     enrol_arlo\Arlo\AuthAPI\FieldFormat
 * @author      Troy Williams
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Registration {

    /** @var int $RegistrationID */
    private $RegistrationID;

    /** @var string $UniqueIdentifier GUID value represented as a VARCHAR(36) */
    private $UniqueIdentifier;

    /** @var string $Attendance */
    private $Attendance;

    /** @var string $Grade */
    private $Grade;

    /** @var string $Outcome */
    private $Outcome;

    /** @var string $LastActivityDateTime  */
    private $LastActivityDateTime;

    /** @var string $ProgressStatus */
    private $ProgressStatus;

    /** @var string $ProgressPercent */
    private $ProgressPercent;

    /** @var string $Status */
    private $Status;

    /** @var string $CertificateSentDateTime */
    private $CertificateSentDateTime;

    /** @var string $CompletedDateTime */
    private $CompletedDateTime;

    /** @var string $Comments */
    private $Comments;

    /** @var string $CreatedDateTime */
    private $CreatedDateTime;

    /** @var string $LastModifiedDateTime*/
    private $LastModifiedDateTime;

    /** @var CustomFields $CustomFields */
    private $CustomFields;

    /** @var Event $Event */
    private $Event;

    /** @var OnlineActivity $OnlineActivity */
    private $OnlineActivity;

    /** @var Contact $Contact */
    private $Contact;

    /**
     * @var array $Links Related resource links.
     */
    private $Links = [];

    public function addLink(Link $Link) {
        $this->Links[] = $Link;
    }

    /**
     * An integer value that uniquely identifies this resource within the platform.
     *
     * @return mixed
     */
    public function getRegistrationID() {
        return $this->RegistrationID;
    }

    /**
     * A GUID value that uniquely identifies this resource across any platform.
     *
     * @return mixed
     */
    public function getUniqueIdentifier() {
        return $this->UniqueIdentifier;
    }

    /**
     * A RegistrationContactAttendance value indicating whether the Contact attended the Event. Such as
     * Attended, DidNotAttend and Unknown.
     *
     * @return mixed
     */
    public function getAttendance() {
        return $this->Attendance;
    }

    /**
     * An string representing the grade associated with this resource, of up to 64 characters.
     * May be any readable text representing the level of achievement, including letters,
     * numbers, or both. Omitted if no grade has been assigned.
     *
     * @return mixed
     */
    public function getGrade() {
        return $this->Grade;
    }

    /**
     * A RegistrationOutcome value representing the outcome of the registration in terms of Pass or Fail.
     * Omitted if no outcome has been assigned.
     *
     * @return mixed
     */
    public function getOutcome() {
        return $this->Outcome;
    }

    /**
     * A DateTimeOffset value representing the time when there was some activity in an external
     * system associated with this registration. Most relevant for registrations associated with e-learning.
     * This property is provided for external integration purposes and is persisted but not otherwise
     * managed by the Arlo platform.
     *
     * @return mixed
     */
    public function getLastActivityDateTime() {
        return $this->LastActivityDateTime;
    }

    /**
     * A user-readable string representing the status of the registration, up to 64 characters.
     * Most relevant for registrations associated with e-learning. This property is provided for
     * external integration purposes and is persisted but not otherwise managed by the Arlo platform.
     *
     * @return mixed
     */
    public function getProgressStatus() {
        return $this->ProgressStatus;
    }

    /**
     * A decimal value representing a progress of the registration. Most relevant for registrations
     * associated with e-learning. This property is provided for external integration purposes and is
     * persisted but not otherwise managed by Arlo.
     *
     * NOTE: Setting this value to '100' will not change the status of the registration to Completed.
     *
     * @return mixed
     */
    public function getProgressPercent() {
        return $this->ProgressPercent;
    }

    /**
     * A RegistrationStatus value representing the current state of this registration,
     * such as pending approval, approved, cancelled, or completed.
     *
     * @return mixed
     */
    public function getStatus() {
        return $this->Status;
    }

    /**
     * A UTC DateTime value indicating when a certificate was last sent to the registrant.
     *
     * @return mixed
     */
    public function getCertificateSeentDateTime() {
        return $this->CertificateSentDateTime;
    }

    /**
     * A DateTime value representing the time when the course was completed in the external system associated
     * with the registration.
     *
     * @return mixed
     */
    public function getCompletedDateTime() {
        return $this->CompletedDateTime;
    }

    /**
     * An inline RichContent resource representing comments made against this Registration.
     *
     * @return mixed
     */
    public function getComments() {
        return $this->Comments;
    }

    /**
     * A UTC DateTime value indicating when this resource was created.
     *
     * @return mixed
     */
    public function getCreatedDateTime() {
        return $this->CreatedDateTime;
    }

    /**
     * A UTC DateTime value indicating when this resource was last modified.
     *
     * @return mixed
     */
    public function getLastModifiedDateTime() {
        return $this->LastModifiedDateTime;
    }

    /**
     * Check value beings set is record ID greater than 0.
     *
     * @param int $Value
     * @return $this
     */
    public function setRegistrationID(int $Value) {
        if ($Value <= 0) {
            throw new UnexpectedValueException("RegistrationID must be an integer greater than Zero");
        }
        $this->RegistrationID = $Value;
        return $this;
    }

    /**
     * Check value is valid GUID string.
     *
     * @param string $Value
     * @return $this
     */
    public function setUniqueIdentifier(string $Value) {
        if (!GuidFieldFormat::Validate($Value)) {
            throw new UnexpectedValueException("UniqueIdentifier must be a GUID string");
        }
        $this->UniqueIdentifier = $Value;
        return $this;
    }

    public function setAttendance(string $Value) {
        $this->Attendance = $Value;
        return $this;
    }

    public function setGrade(string $Value) {
        $this->Grade = $Value;
        return $this;
    }

    public function setOutcome(string $Value) {
        $this->Outcome = $Value;
        return $this;
    }

    public function setLastActivityDateTime(string $Value) {
        $this->LastActivityDateTime = $Value;
        return $this;
    }

    public function setProgressStatus(string $Value) {
        $this->ProgressStatus = $Value;
        return $this;
    }

    public function setProgressPercent(string $Value) {
        $this->ProgressPercent = $Value;
        return $this;
    }

    public function setStatus(string $Value) {
        $this->Status = $Value;
        return $this;
    }

    public function setCertificateSentDateTime(string $Value) {
        $this->CertificateSentDateTime = $Value;
        return $this;
    }

    public function setCompletedDateTime(string $Value) {
        $this->CompletedDateTime = $Value;
        return $this;
    }

    public function setComments(string $Value) {
        $this->Comments = $Value;
        return $this;
    }

    /**
     * Check value is valid DateTime string.
     *
     * @param string $Value
     * @return $this
     */
    public function setCreatedDateTime(string $Value) {
        if (!DateTimeFieldFormat::Validate($Value)) {
            throw new UnexpectedValueException("CreatedDateTime must be a DateTime string");
        }
        $this->CreatedDateTime = $Value;
        return $this;
    }

    /**
     * Check value is valid DateTime string.
     *
     * @param string $Value
     * @return $this
     */
    public function setLastModifiedDateTime(string $Value) {
        if (!DateTimeFieldFormat::Validate($Value)) {
            throw new UnexpectedValueException("LastModifiedDateTime must be a DateTime string");
        }
        $this->LastModifiedDateTime = $Value;
        return $this;
    }

    /**
     * Collection of associated resource links.
     *
     * @return array
     */
    public function getLinks() {
        return $this->Links;
    }

    /**
     * CustomFields associated with this Registration.
     *
     * @return CustomFields
     */
    public function getCustomFields() {
        return $this->CustomFields;
    }

    /**
     * Event associated with this Registration.
     *
     * @return Event
     */
    public function getEvent() {
        return $this->Event;
    }

    /**
     * OnlineActivity associated with this Registration.
     *
     * @return OnlineActivity
     */
    public function getOnlineActivity() {
        return $this->OnlineActivity;
    }

    /**
     * Contact associated with this Registration.
     *
     * @return Contact
     */
    public function getContact() {
        return $this->Contact;
    }

    /**
     * Has associated CustomFields collection linked.
     *
     * @return bool
     */
    public function hasCustomFields() {
        return (is_null($this->CustomFields)) ? false : true;
    }

    /**
     * Has associated Event linked.
     *
     * @return bool
     */
    public function hasEvent() {
        return (is_null($this->Event)) ? false : true;
    }

    /**
     * Has associated OnlineActivity linked.
     *
     * @return bool
     */
    public function hasOnlineActivity() {
        return (is_null($this->OnlineActivity)) ? false : true;
    }

    /**
     * Has associated Contact linked.
     *
     * @return bool
     */
    public function hasContact() {
        return (is_null($this->Contact)) ? false : true;
    }

    /**
     * Associated CustomFields.
     *
     * @param CustomFields $Entity
     * @return $this
     */
    public function setCustomFields(CustomFields $Entity) {
        $this->CustomFields = $Entity;
        return $this;
    }

    /**
     * Associated Event.
     *
     * @param Event $Entity
     * @return $this
     */
    public function setEvent(Event $Entity) {
        if (is_null($Entity->getEventID()) || $Entity->getUniqueIdentifier()) {
            throw new UnexpectedValueException("Event must have EventID and UniqueIdentifier properties set");
        }
        $this->Event = $Entity;
        return $this;
    }

    /**
     * Associated OnlineActivity.
     *
     * @param OnlineActivity $Entity
     * @return $this
     */
    public function setOnlineActivity(OnlineActivity $Entity) {
        if (is_null($Entity->getOnlineActivityID()) || $Entity->getUniqueIdentifier()) {
            throw new UnexpectedValueException("OnlineActivity must have OnlineActivityID and UniqueIdentifier properties set");
        }
        $this->OnlineActivity = $Entity;
        return $this;
    }

    /**
     * Associated Contact.
     *
     * @param Contact $Entity
     * @return $this
     */
    public function setContact(Contact $Entity) {
        if (is_null($Entity->getContactID()) || is_null($Entity->getUniqueIdentifier())) {
            throw new UnexpectedValueException("Contact must have ContactID and UniqueIdentifier properties set");
        }
        $this->Contact = $Entity;
        return $this;
    }

}
