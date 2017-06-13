<?php

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * Class Event
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class Event extends AbstractResource {
    public $EventID;
    public $UniqueIdentifier;
    public $Code;
    public $StartDateTime;
    public $FinishDateTime;
    public $LocationName;
    public $Description;
    /**
     * @var EventTemplate associated resource.
     */
    protected $eventTemplate;
    /**
     * @param EventTemplate $eventTemplate
     */
    public function setEventTemplate(EventTemplate $eventTemplate) {
        $this->eventTemplate = $eventTemplate;
    }
}