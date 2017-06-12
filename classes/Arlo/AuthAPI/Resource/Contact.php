<?php

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * Class Contact
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class Contact extends AbstractResource {
    public $ContactID;
    public $UniqueIdentifier;
    public $FirstName;
    public $LastName;
    public $Email;
    public $CodePrimary;
    public $PhoneWork;
    public $PhoneHome;
    public $PhoneMobile;
}
