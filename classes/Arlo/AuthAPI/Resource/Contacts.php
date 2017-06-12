<?php

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * Class Contacts
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class Contacts extends AbstractCollection {
    /**
     * Add contact object to the resource collection.
     *
     * @param Contact $contact
     */
    public function addContact(Contact $contact) {
        $this->collection[] = $contact;
    }
    /**
     * Do we have any contacts.
     *
     * @return bool
     */
    public function hasContacts() {
        return parent::hasCollection();
    }
}