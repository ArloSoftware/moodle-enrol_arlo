<?php

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * Class Contacts
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class CustomFields extends AbstractResource {

    public $Field;

    public function getField() {
        return $this->Field;
    }
}