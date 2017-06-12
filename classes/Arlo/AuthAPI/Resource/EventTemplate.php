<?php

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * Class EventTemplate
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class EventTemplate extends AbstractResource {
    public $TemplateID;
    public $UniqueIdentifier;
    public $Code;
    public $Name;
    public $AdvertisedDuration;
    public $TemplateHosting;
    public $IsPrivate;
    public $DefaultEventSessionType;
    public $PublishOnWebsite;
}