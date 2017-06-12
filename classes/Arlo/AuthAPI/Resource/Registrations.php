<?php

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * Class Registrations
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class Registrations extends AbstractCollection {
    /**
     * Add registration object to the resource collection.
     *
     * @param Registration $registration
     */
    public function addRegistration(Registration $registration) {
        $this->collection[] = $registration;
    }
    /**
     * Do we have any registrations.
     *
     * @return bool
     */
    public function hasRegistrations(){
        return parent::hasCollection();
    }
}