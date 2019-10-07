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

    public $LastActivityDateTime;

    public $ProgressPercent;

    public $ProgressStatus;

    public $CertificateSentDateTime;

    public $CompletedDateTime;

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
     * @return mixed
     */
    public function getContact() {
        return $this->Contact;
    }

    /**
     * @return Event
     */
    public function getEvent() {
        return $this->Event;
    }

    /**
     * @return OnlineActivity
     */
    public function getOnlineActivity() {
        return $this->OnlineActivity;
    }

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