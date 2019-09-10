<?php namespace enrol_arlo\Arlo\AuthAPI\Entity;

/**
 * Registration Entity Class
 *
 * A Registration represents a request from a Contact to attend an Event or Online Activity.
 *
 * @package enrol_arlo\Arlo\AuthAPI\Entity
 */
class Registration {

    private $RegistrationID;

    private $UniqueIdentifier;

    private $Attendance;

    private $Grade;

    private $Outcome;

    private $LastActivityDateTime;

    private $ProgressStatus;

    private $ProgressPercent;

    private $Status;

    private $CertificateSentDateTime;

    private $CompletedDateTime;

    private $Comments;

    private $CreatedDateTime;

    private $LastModifiedDateTime;

    private $CustomFields;

    private $Event;

    private $OnlineActivity;

    private $Contact;

    /**
     * @var array $Links Related resource links.
     */
    private $Links = [];

    /**
     * An integer value that uniquely identifies this resource within the platform.
     *
     * @return mixed
     */
    public function getRegistrationID() {
        return $this->RegistrationID;
    }

    /**
     * A GUID value that uniquely identifies this resource across any platform, such as
     * Attended, DidNotAttend and Unknown.
     *
     * @return mixed
     */
    public function getUniqueIdentifier() {
        return $this->UniqueIdentifier;
    }

    /**
     * A RegistrationContactAttendance value indicating whether the Contact attended the Event.
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
     * @param int $Value
     * @return $this
     */
    public function setRegistrationID(int $Value) {
        $this->RegistrationID = $Value;
        return $this;
    }

    public function setUniqueIdentifier(string $Value) {
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

    public function setCertificateSentDateTime($Value) {
        $this->CertificateSentDateTime = $Value;
        return $this;
    }

    public function setCompletedDateTime(string $Value) {
        $this->CompletedDateTime = $Value;
        return $this;
    }

    public function setComments($Value) {
        $this->Comments = $Value;
        return $this;
    }

    public function setCreatedDateTime($Value) {
        $this->CreatedDateTime = $Value;
        return $this;
    }

    public function setLastModifiedDateTime($Value) {
        $this->LastModifiedDateTime = $Value;
        return $this;
    }

    public function getLinks() {
        return $this->Links;
    }

    public function getCustomFields() {
        return $this->CustomFields;
    }

    public function getEvent() {
        return $this->Event;
    }

    public function getOnlineActivity() {
        return $this->OnlineActivity;
    }

    public function getContact() {
        return $this->Contact;
    }

    public function addLink(Link $Link) {}
    public function setCustomFields(CustomFields $Entity) {}
    public function setEvent(Event $Entity) {}
    public function setOnlineActivity(OnlineActivity $Entity) {}
    public function setContact(Contact $Entity) {}

}
