<?php namespace enrol_arlo\Arlo\AuthAPI\Entity;

use enrol_arlo\Arlo\AuthAPI\FieldFormat\DateTimeFieldFormat;
use enrol_arlo\Arlo\AuthAPI\FieldFormat\GuidFieldFormat;
use UnexpectedValueException;

/**
 * EventTemplate Entity Class
 *
 * @package     enrol_arlo\Arlo\AuthAPI\FieldFormat
 * @author      Troy Williams
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class EventTemplate {

    /** @var int $TemplateID */
    private $TemplateID;

    /** @var string $UniqueIdentifier GUID value represented as a VARCHAR(36). */
    private $UniqueIdentifier;

    /** @var string $Name*/
    private $Name;

    /** @var string $Code */
    private $Code;

    /** @var string $AdvertisedDuration */
    private $AdvertisedDuration;

    /** @var string $TemplateHosting */
    private $TemplateHosting;

    /** @var boolean $IsPrivate */
    private $IsPrivate;

    /** @var string $DefaultEventSessionType */
    private $DefaultEventSessionType;

    /** @var string $Status */
    private $Status;

    /** @var boolean $PublishOnWebsite */
    private $PublishOnWebsite;

    /** @var string $CreatedDateTime */
    private $CreatedDateTime;

    /** @var string $LastModifiedDateTime*/
    private $LastModifiedDateTime;

    /** @var array $Links Collection of related resource links. */
    private $Links = [];

    /**
     * 	A integer value that uniquely identifies this resource within the platform.
     *
     * @return int
     */
    public function getTemplateID() {
        return $this->TemplateID;
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
     * The string name of this template, up to 128 characters long. Usually the descriptive title of the course or event.
     *
     * @return string
     */
    public function getName() {
        return $this->Name;
    }

    /**
     * 	A string representing the short code used when referring to this template, up to 32 characters long.
     * Usually the business reference code of an event, such as MGMT101.
     *
     * @return string
     */
    public function getCode() {
        return $this->Code;
    }

    /**
     * A string containing friendly, human-readable text to use when describing the duration of the various
     * events that use this template, up to 32 characters long.
     *
     * @return string
     */
    public function getAdvertisedDuration() {
        return $this->AdvertisedDuration;
    }

    /**
     * 	An EventTemplateHosting value representing the hosting mode for this template.
     *
     * @return string
     */
    public function getTemplateHosting() {
        return $this->TemplateHosting;
    }

    /**
     * 	A Boolean value indicating whether this template represents a privately-run event for a specific
     * client (such as a private function or onsite training), or a public event available for general registration.
     * Events based on private templates are not publicly promoted on the website.
     *
     * @return string
     */
    public function getIsPrivate() {
        return $this->IsPrivate;
    }

    /**
     * 	An EventSessionType value indicating the default type of session that Events based on this template will use.
     * This default value can be used to broadly classify templates as either online or venue-based, but it is not binding,
     * and events based on this template may override the default and use a different type for their sessions.
     *
     * @return string
     */
    public function getDefaultEventSessionType() {
        return $this->DefaultEventSessionType;
    }

    /**
     * An EventTemplateStatus value representing the current state of this template, such as active or inactive (archived).
     *
     * @return string
     */
    public function getStatus() {
        return $this->Status;
    }

    /**
     * 	A Boolean value indicating whether the page for this template should be published on the website.
     *
     * @return bool
     */
    public function getPublishOnWebsite() {
        return $this->PublishOnWebsite;
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
     * Collection of associated resource links.
     *
     * @return array
     */
    public function getLinks() {
        return $this->Links;
    }

    /**
     * Check value beings set is record ID greater than 0.
     *
     * @param int $Value
     * @return $this
     */
    public function setTemplateID(int $Value) {
        if ($Value <= 0) {
            throw new UnexpectedValueException("TemplateID must be an integer greater than Zero");
        }
        $this->TemplateID = $Value;
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

    /**
     * Set Name.
     *
     * @param string $Value
     * @return $this
     */
    public function setName(string $Value) {
        $this->Name = $Value;
        return $this;
    }

    /**
     * Set Code.
     *
     * @param string $Value
     * @return $this
     */
    public function setCode(string $Value) {
        $this->Code = $Value;
        return $this;
    }

    /**
     * Set AdvertisedDuration.
     *
     * @param string $Value
     * @return $this
     */
    public function setAdvertisedDuration(string $Value) {
        $this->AdvertisedDuration = $Value;
        return $this;
    }

    /**
     * Set TemplateHosting.
     *
     * @param string $Value
     * @return $this
     */
    public function setTemplateHosting(string $Value) {
        $this->TemplateHosting = $Value;
        return $this;
    }

    /**
     * Set IsPrivate.
     *
     * @param bool $Value
     * @return $this
     */
    public function setIsPrivate(bool $Value) {
        $this->IsPrivate = $Value;
        return $this;
    }

    /**
     * Set setDefaultEventSessionType.
     *
     * @param string $Value
     * @return $this
     */
    public function setDefaultEventSessionType(string $Value) {
        $this->DefaultEventSessionType = $Value;
        return $this;
    }

    /**
     * Set Status.
     *
     * @param string $Value
     * @return $this
     */
    public function setStatus(string $Value) {
        $this->Status = $Value;
        return $this;
    }

    /**
     * Set PublishOnWebsite.
     *
     *
     * @param bool $Value
     * @return $this
     */
    public function setPublishOnWebsite(bool $Value) {
        $this->PublishOnWebsite = $Value;
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

}
