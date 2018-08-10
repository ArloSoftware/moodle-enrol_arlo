<?php

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * Class ContactMergeRequest
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class ContactMergeRequest extends AbstractResource {
    /** @var RequestID unique identifier. */
    public $RequestID;

    /** @var ContactInfo source contact identifier structure. */
    public $SourceContactInfo;

    /** @var ContactInfo destination contact identifier structure. */
    public $DestinationContactInfo;

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