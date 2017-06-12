<?php

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * Class OnlineActivities
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class OnlineActivities extends AbstractCollection {
    /**
     * Add online activity object to the resource collection.
     *
     * @param OnlineActivity $onlineActivity
     */
    public function addOnlineActivity(OnlineActivity $onlineActivity) {
        $this->collection[] = $onlineActivity;
    }

    /**
     * Do we have any online activities.
     *
     * @return bool
     */
    public function hasOnlineActivities() {
        return parent::hasCollection();
    }
}