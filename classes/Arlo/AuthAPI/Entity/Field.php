<?php namespace enrol_arlo\Arlo\AuthAPI\Entity;

/**
 * Field Entity.
 *
 * Represents a field with Name/Value used by CustomFields entity.
 *
 * @package     enrol_arlo\Arlo\AuthAPI
 * @author      Troy Williams
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class Field {

    private $Name;

    private $Value;

    /**
     * @var array $Links Related resource links.
     */
    private $Links = [];

    /**
     * The descriptive name of the field, copied from the underlying FieldDescription.
     *
     * @return mixed
     */
    public function getName() {
        return $this->Name;
    }

    public function getValue() {
        return $this->Value;
    }

    public function setName($Name) {
        $this->Name = $Name;
        return $this;
    }

    public function setValue($Value) {
        $this->Value = $Value;
        return $this;
    }

    public function setStringValue(string $Value) {}
    public function setBoolValue(bool $Value) {}
    public function setArrayValue(array $Value) {}
    public function setIntValue(int $Value) {}
    public function setDateValue(string $Value) {}
}
