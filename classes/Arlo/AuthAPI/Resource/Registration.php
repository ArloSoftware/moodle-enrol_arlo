<?php

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * Class Registration
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class Registration extends AbstractResource {
    public $RegistrationID;
    public $UniqueIdentifier;
    public $Attendance;
    public $Outcome;
    public $Grade;
    public $ProgressPercent;
    public $ProgressStatus;
    public $LastActivityDateTime;
    public $Comments;

    /**
     * @var Contact associated resource.
     */
    protected $Contact;

    /**
     * @var Event associated resource.
     */
    protected $Event;

    /**
     * @var OnlineActivity associated resource.
     */
    protected $OnlineActivity;

    /**
     * @param Contact $contact
     */
    public function setContact(Contact $contact) {
        $this->Contact = $contact;
    }

    /**
     * @param Event $event
     */
    public function setEvent(Event $event) {
        $this->Event = $event;
    }

    /**
     * @param OnlineActivity $onlineActivity
     */
    public function setOnlineActivity(OnlineActivity $onlineActivity) {
        $this->OnlineActivity = $onlineActivity;
    }
}