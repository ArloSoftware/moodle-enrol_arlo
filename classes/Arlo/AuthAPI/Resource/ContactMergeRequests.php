<?php

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * Class ContactMergeRequests
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class ContactMergeRequests extends AbstractCollection {
    /**
     * Add merge request to the resource collection.
     *
     * @param ContactMergeRequest $contactMergeRequest
     */
    public function addContactMergeRequest(ContactMergeRequest $contactMergeRequest) {
        $this->collection[] = $contactMergeRequest;
    }
    /**
     * Do we have any merge requests.
     *
     * @return bool
     */
    public function hasContactMergeRequest() {
        return parent::hasCollection();
    }
}