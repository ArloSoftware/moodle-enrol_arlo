<?php

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * Class ContactMergeRequest
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class ContactMergeRequest extends AbstractResource {
    public $RequestID;
    /** @var ContactInfo source contact identifier structure. */
    protected $SourceContactInfo;
    /** @var ContactInfo destination contact identifier structure. */
    protected $DestinationContactInfo;

    /**
     * @param ContactInfo $contactInfo
     */
    public function setSourceContactInfo(ContactInfo $contactInfo) {
        $this->SourceContactInfo = $contactInfo;
    }

    /**
     * @param ContactInfo $contactInfo
     */
    public function setDestinationContactInfo(ContactInfo $contactInfo) {
        $this->DestinationContactInfo = $contactInfo;
    }
}