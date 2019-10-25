<?php namespace enrol_arlo\Arlo\AuthAPI\Resource;

use enrol_arlo\Arlo\AuthAPI\Enum\IntegrationData;
use Exception;
use GuzzleHttp\Psr7\Uri;
use UnexpectedValueException;

class OnlineActivityIntegrationData {

    private $editUri;

    private $onlineActivity;

    public function __construct($onlineActivity) {
        $this->setOnlineActivity($onlineActivity);
    }

    public function getVendorID() {
        return IntegrationData::VendorID;
    }

    public function getEditUri($toString = true) {
        if ($toString) {
            return (string) $this->editUri;
        }
        return $this->editUri;
    }

    public function setEditUri(string $uri) {
        $this->editUri = new Uri($uri);
    }

    public function setOnlineActivity($onlineActivity) {
        if (empty($onlineActivity->OnlineActivityID)) {
            throw new UnexpectedValueException("OnlineActivityID property must be set");
        }
        $this->onlineActivity = $onlineActivity;
    }

    public function buildResourcePath() {
        if (is_null($this->onlineActivity)) {
            throw new Exception("OnlineActivity must be set");
        }
        return 'onlineactivities/' . $this->onlineActivity->OnlineActivityID . '/integrationdata/' . strtolower(self::getVendorID());
    }

}
