<?php

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * Class OnlineActivity
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class OnlineActivity extends AbstractResource {
    public $OnlineActivityID;
    public $UniqueIdentifier;
    public $Code;
    public $Name;
    public $DeliveryDescription;
    public $ContentUri;
    /**
     * @var EventTemplate associated resource.
     */
    protected $eventTemplate;

    /**
     * @return EventTemplate
     */
    public function getEventTemplate() {
        return $this->eventTemplate;
    }

    /**
     * @param EventTemplate $eventTemplate
     */
    public function setEventTemplate(EventTemplate $eventTemplate) {
        $this->eventTemplate = $eventTemplate;
    }
}