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
     * @var OrderLine $OrderLine Reference to the OrderLine resource for this registration, if associated with a purchase order.
     */
    protected $OrderLine;

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
     * Set the OrderLine data for this Registration.
     *
     * @param OrderLine $orderline The order line resource.
     */
    public function setOrderLine(OrderLine $orderline) {
        $this->OrderLine = $orderline;
    }

    /**
     * Get the OrderLine resource.
     *
     * @return OrderLine
     */
    public function getOrderLine() {
        return $this->OrderLine;
    }

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