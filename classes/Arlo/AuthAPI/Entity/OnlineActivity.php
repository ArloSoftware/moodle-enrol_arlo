<?php namespace enrol_arlo\Arlo\AuthAPI\Entity;

use enrol_arlo\Arlo\AuthAPI\FieldFormat\GuidFieldFormat;
use UnexpectedValueException;

/**
 * OnlineActivity Entity Class
 *
 * Online activities represent an online instance of an EventTemplate resource, and a
 * collection of Registrations representing contacts who will undertake the event.
 *
 * @package    enrol_arlo\Arlo\AuthAPI\FieldFormat
 * @copyright  Troy Williams
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class OnlineActivity {

    /** @var int $OnlineActivityID */
    private $OnlineActivityID;

    /** @var string $UniqueIdentifier */
    private $UniqueIdentifier;

    /** @var string $Code */
    private $Code;

    /** @var string $Name */
    private $Name;

    /** @var string $DeliveryDescription */
    private $DeliveryDescription;

    /** @var string $ContentUri */
    private $ContentUri;

    /** @var string $Status */
    private $Status;

    /** @var string $CreatedDateTime */
    private $CreatedDateTime;

    /** @var string $LastModifiedDateTime */
    private $LastModifiedDateTime;

    /**
     * @var array $Links Related resource links.
     */
    private $Links = [];

    /** @var CustomFields $CustomFields */
    private $CustomFields;

    /**
     * Add Link to associated resource link collection.
     *
     * @param Link $Link
     */
    public function addLink(Link $Link) {
        $this->Links[] = $Link;
    }

    /**
     * A integer value that uniquely identifies this resource within the platform.
     *
     * @return mixed
     */
    public function getOnlineActivityID() {
        return $this->OnlineActivityID;
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
     * A string representing the short code used when referring to this activity, up to 32 characters long.
     * By default this value is based off the EventTemplate Code with a unique integer ID appended.
     *
     * @return mixed
     */
    public function getCode() {
        return $this->Code;
    }

    /**
     * A string with the name for the OnlineActivity, up to 128 characters long.
     *
     * @return mixed
     */
    public function getName() {
        return $this->Name;
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
     * A string describing how the activity will be delivered, up to 128 characters long.
     *
     * @return mixed
     */
    public function getDeliveryDescription() {
        return $this->DeliveryDescription;
    }

    /**
     * A string value representing a URL referring to the content for OnlneActivity up to 256 characters
     * long. Relevant for Events associated with e-learning.
     *
     * @return mixed
     */
    public function getContentUri() {
        return $this->ContentUri;
    }

    /**
     * An OnlineActivityStatus value representing the current state of this resource,
     * such as draft, active, completed or archived.
     * @return mixed
     */
    public function getStatus() {
        return $this->Status;
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
     * Has associated CustomFields collection linked.
     *
     * @return bool
     */
    public function hasCustomFields() {
        return (is_null($this->CustomFields)) ? false : true;
    }

    /**
     * Check value beings set is record ID greater than 0.
     *
     * @param int $Value
     * @return $this
     */
    public function setOnlineActivityID(int $Value) {
        if ($Value <= 0) {
            throw new UnexpectedValueException("OnlineActivityID must be an integer greater than Zero");
        }
        $this->OnlineActivityID = $Value;
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

    public function setCode(string $Value) {
        $this->Code = $Value;
        return $this;
    }

    public function setName(string $Value) {
        $this->Name = $Value;
        return $this;
    }

    public function setDeliveryDescription(string $Value) {
        $this->DeliveryDescription = $Value;
        return $this;
    }

    public function setContentUri(string $Value) {
        $this->ContentUri = $Value;
        return $this;
    }

    public function setStatus(string $Value) {
        $this->Status = $Value;
        return $this;
    }

    public function setCreatedDateTime(string $Value) {
        $this->CreatedDateTime = $Value;
        return $this;
    }

    public function setLastModifiedDateTime(string $Value) {
        $this->LastModifiedDateTime = $Value;
        return $this;
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
}
