<?php

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * Class EventTemplates
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class EventTemplates extends AbstractCollection {
    /**
     * Add event template object to the resource collection.
     *
     * @param EventTemplate $eventTemplate
     */
    public function addEventTemplate(EventTemplate $eventTemplate) {
        $this->collection[] = $eventTemplate;
    }
    /**
     * Do we have any event templates.
     *
     * @return bool
     */
    public function hasEventTemplates() {
        return parent::hasCollection();
    }
}