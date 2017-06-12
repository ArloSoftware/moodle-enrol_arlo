<?php

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * Class Organisation
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class Organisation extends AbstractResource {
    public $WebsiteUrl;
    public $OrganisationID;
    public $Name;
    public $LegalName;
    public $Email;
    public $CodePrimary;
    public $CodeSecondary;
    public $PhonePrimary;
    public $PhoneSecondary;
}