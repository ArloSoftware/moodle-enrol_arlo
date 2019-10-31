<?php namespace enrol_arlo\Arlo\AuthAPI\Resource;

use enrol_arlo\Arlo\AuthAPI\Enum\IntegrationData;
use Exception;
use GuzzleHttp\Psr7\Uri;
use UnexpectedValueException;

class EventIntegrationData {

    private $manageUri;

    private $event;

    public function getVendorID() {
        return IntegrationData::VendorID;
    }

    public function __construct($event) {
        $this->setEvent($event);
    }

    public function getManageUri($toString = true) {
        if ($toString) {
            return (string) $this->manageUri;
        }
        return $this->manageUri;
    }

    public function setManageUri(string $uri) {
        $this->manageUri = new Uri($uri);
    }

    public function setEvent($event) {
        if (empty($event->EventID)) {
            throw new UnexpectedValueException("EventID property must be set");
        }
        $this->event = $event;
    }

    public function buildResourcePath() {
        if (is_null($this->event)) {
            throw new Exception("Event must be set");
        }
        return 'events/' . $this->event->EventID . '/integrationdata/' . strtolower(self::getVendorID());
    }

}
