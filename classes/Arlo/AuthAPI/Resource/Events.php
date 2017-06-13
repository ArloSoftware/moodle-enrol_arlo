<?php

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * Class Events
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class Events extends AbstractCollection {
    /**
     * Add event object to the resource collection.
     *
     * @param Event $event
     */
    public function addEvent(Event $event) {
        $this->collection[] = $event;
    }
    /**
     * Do we have any events.
     *
     * @return bool
     */
    public function hasEvents() {
        return parent::hasCollection();
    }
}