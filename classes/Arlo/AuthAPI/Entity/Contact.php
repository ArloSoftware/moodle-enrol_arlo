<?php namespace enrol_arlo\Arlo\AuthAPI\Entity;

use enrol_arlo\Arlo\AuthAPI\FieldFormat\DateTimeFieldFormat;
use enrol_arlo\Arlo\AuthAPI\FieldFormat\GuidFieldFormat;
use UnexpectedValueException;

/**
 * Contact Entity Class.
 *
 * @package     enrol_arlo\Arlo\AuthAPI\FieldFormat
 * @author      Troy Williams
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Contact {

    /** @var int $ContactID */
    private $ContactID;

    /** @var string $UniqueIdentifier GUID value represented as a VARCHAR(36). */
    private $UniqueIdentifier;

    /** @var string $FirstName */
    private $FirstName;

    /** @var string $LastName */
    private $LastName;

    /** @var string $Email */
    private $Email;

    /** @var string $PhoneWork */
    private $PhoneWork;

    /** @var string $PhoneHome */
    private $PhoneHome;

    /** @var string $PhoneMobile */
    private $PhoneMobile;

    /** @var string $CodePrimary */
    private $CodePrimary;

    /** @var string $Status */
    private $Status;

    /** @var string $CreatedDateTime */
    private $CreatedDateTime;

    /** @var string $LastModifiedDateTime*/
    private $LastModifiedDateTime;

    /**
     * @var array $Links Collection of related resource links.
     */
    private $Links = [];

    /** @var CustomFields $CustomFields */
    private $CustomFields;

    /**
     * @param Link $Link
     */
    public function addLink(Link $Link) {
        $this->Links[] = $Link;
    }

    /**
     * An integer value that uniquely identifies this resource within the platform. This value is read-only.
     *
     * @return int
     */
    public function getContactID() {
        return $this->ContactID;
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
     * A string representing the first name of this individual, up to 64 characters long.
     *
     * @return mixed
     */
    public function getFirstName() {
        return $this->FirstName;
    }

    /**
     * A string representing the last name of this individual, up to 64 characters long.
     *
     * @return mixed
     */
    public function getLastName() {
        return $this->LastName;
    }

    /**
     * A string representing the email of this individual, up to 128 characters long.
     *
     * @return mixed
     */
    public function getEmail() {
        return $this->Email;
    }

    /**
     * A string representing the work contact phone number of this individual, up to 128 characters long.
     *
     * @return mixed
     */
    public function getPhoneWork() {
        return $this->PhoneWork;
    }

    /**
     * A string representing the work contact phone number of this individual, up to 128 characters long.
     *
     * @return mixed
     */
    public function getPhoneHome() {
        return $this->PhoneHome;
    }

    /**
     * A string representing the home contact mobile phone number of this individual, up to 128 characters long.
     *
     * @return mixed
     */
    public function getPhoneMobile() {
        return $this->PhoneMobile;
    }

    /**
     * 	A string representing an internal (primary) code used to reference this contact, up to 50 characters long.
     * This property is useful for storing and managing identifier values from external systems.
     *
     * @return mixed
     */
    public function getCodePrimary() {
        return $this->CodePrimary;
    }

    /**
     * A ContactStatus value representing the current state of this contact, such as active or inactive (archived).
     *
     * @return string
     */
    public function getStatus() {
        return $this->Status;
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
     * CustomFields associated with this Registration.
     *
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
     * Check value beings set is record ID greater than 0.
     *
     * @param int $Value
     * @return $this
     */
    public function setContactID(int $Value) {
        if ($Value <= 0) {
            throw new UnexpectedValueException("ContactID must be an integer greater than Zero");
        }
        $this->ContactID = $Value;
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
     * Set FirstName.
     *
     * @param string $Value
     * @return $this
     */
    public function setFirstName(string $Value) {
        $this->FirstName = $Value;
        return $this;
    }

    /**
     * Set LastName.
     *
     * @param string $Value
     * @return $this
     */
    public function setLastName(string $Value) {
        $this->LastName = $Value;
        return $this;
    }

    /**
     * Set Email.
     *
     * @param string $Value
     * @return $this
     */
    public function setEmail(string $Value) {
        $this->Email = $Value;
        return $this;
    }

    /**
     * Set PhoneWork.
     *
     * @param string $Value
     * @return $this
     */
    public function setPhoneWork(string $Value) {
        $this->PhoneWork = $Value;
        return $this;
    }

    /**
     * Set PhoneHome.
     *
     * @param string $Value
     * @return $this
     */
    public function setPhoneHome(string $Value) {
        $this->PhoneHome = $Value;
        return $this;
    }

    /**
     * Set PhoneMobile.
     *
     * @param string $Value
     * @return $this
     */
    public function setPhoneMobile(string $Value) {
        $this->PhoneMobile = $Value;
        return $this;
    }

    /**
     * Set CodePrimary.
     *
     * @param string $Value
     * @return $this
     */
    public function setCodePrimary(string $Value) {
        $this->CodePrimary = $Value;
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
