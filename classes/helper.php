<?php

namespace enrol_arlo;

use stdClass;
use ReflectionClass;
use enrol_arlo\Arlo\AuthAPI\Resource\AbstractResource;
use enrol_arlo\Arlo\AuthAPI\Resource\Contact;
use enrol_arlo\Arlo\AuthAPI\Resource\Event;
use enrol_arlo\Arlo\AuthAPI\Resource\EventTemplate;
use enrol_arlo\Arlo\AuthAPI\Resource\OnlineActivity;
use enrol_arlo\Arlo\AuthAPI\Resource\Registration;

class helper {
    /**
     * Public static function to pass class of to supported resource to record function.
     *
     * @param AbstractResource $resource
     * @param array $parameters
     * @return mixed|stdClass
     */
    public static function resource_to_record(AbstractResource $resource, $parameters = array()) {
        if ($resource instanceof Contact) {
            return static::contact_to_record($resource, $parameters);
        }
        if ($resource instanceof Event) {
            return static::event_to_record($resource, $parameters);
        }
        if ($resource instanceof OnlineActivity) {
            return static::onlineactivity_to_record($resource, $parameters);
        }
        if ($resource instanceof EventTemplate) {
            return static::template_to_record($resource, $parameters);
        }
        if ($resource instanceof Registration) {
            return static::registration_to_record($resource, $parameters);
        }
        // Return empty stdClass if resource not implemented.
        return new stdClass();
    }

    /**
     * Apply common resource properties.
     *
     * @param AbstractResource $resource
     * @return stdClass
     */
    protected static function init_record(AbstractResource $resource) {
        $reflect = new ReflectionClass($resource);
        $record = new stdClass();
        $record->platform = get_config('enrol_arlo', 'platform');
        // Create ID field based on class short name.
        $idfieldname = $reflect->getShortName() . 'ID';
        $record->sourceid = $resource->{$idfieldname};
        $record->sourceguid = $resource->UniqueIdentifier;
        $record->sourcestatus = $resource->Status;
        $record->sourcecreated = $resource->CreatedDateTime;
        $record->sourcemodified = $resource->LastModifiedDateTime;
        return $record;
    }

    /**
     * Apply passed in parameters. Used for existing records. i.e identifiers.
     *
     * @param $record
     * @param $parameters
     * @return mixed
     */
    protected static function apply_parameters($record, $parameters) {
        foreach ($parameters as $key => $value) {
            if (is_string($key)) {
                $record->{$key} = $value;
            }
        }
        return $record;
    }

    /**
     * Prep contact for database.
     *
     * @param Contact $resource
     * @param array $parameters
     * @return mixed
     */
    protected static function contact_to_record(Contact $resource, $parameters = array()) {
        $record = static::init_record($resource);
        return static::apply_parameters($record, $parameters);
    }

    /**
     * Prep event for database.
     *
     * @param Event $resource
     * @param array $parameters
     * @return mixed
     */
    protected static function event_to_record(Event $resource, $parameters = array()) {
        $record = static::init_record($resource);
        $record->code = $resource->Code;
        $record->startdatetime = $resource->StartDateTime;
        $record->finishdatetime = $resource->FinishDateTime;
        $template = $resource->getEventTemplate();
        // Add EventTemplate if passed.
        if ($template) {
            $record->sourcetemplateid = $template->TemplateID;
            $record->sourcetemplateguid = $template->UniqueIdentifier;
        }
        return static::apply_parameters($record, $parameters);
    }

    /**
     * Prep online activity for database.
     *
     * @param OnlineActivity $resource
     * @param array $parameters
     * @return mixed
     */
    protected static function onlineactivity_to_record(OnlineActivity $resource, $parameters = array()) {
        $record = static::init_record($resource);
        $record->name = $resource->Name;
        $record->code = $resource->Code;
        $record->contenturi = $resource->ContentUri;
        $template = $resource->getEventTemplate();
        // Add EventTemplate if passed.
        if ($template) {
            $record->sourcetemplateid = $template->TemplateID;
            $record->sourcetemplateguid = $template->UniqueIdentifier;
        }
        return static::apply_parameters($record, $parameters);
    }

    /**
     * Prep registration for database.
     *
     * @param Registration $resource
     * @param array $parameters
     * @return mixed
     */
    protected static function registration_to_record(Registration $resource, $parameters = array()) {
        $record = static::init_record($resource);
        $record->attendance = $resource->Attendance;
        $record->grade = $resource->Grade;
        $record->lastactivity = $resource->LastActivityDateTime;
        $record->progressstatus = $resource->ProgressStatus;
        $record->progresspercent = $resource->ProgressPercent;
        // Add Contact if passed.
        $contact = $resource->getContact();
        if (isset($contact )) {
            $record->sourcecontactid = $contact->ContactID;
            $record->sourcecontactguid = $contact->UniqueIdentifier;
        }
        // Add Event if passed.
        $event = $resource->getEvent();
        if (isset($event)) {
           $record->sourceeventid = $event->EventID;
           $record->sourceeventguid = $event->UniqueIdentifier;
        }
        // Add OnlineActivity if passed.
        $onlineactivity = $resource->getOnlineActivity();
        if (isset($onlineactivity)) {
            $record->sourceonlineactivityid = $onlineactivity->OnlineActivityID;
            $record->sourceonlineactivityguid = $onlineactivity->UniqueIdentifier;
        }
        return static::apply_parameters($record, $parameters);
    }

    /**
     * Prep event template for database.
     *
     * @param EventTemplate $resource
     * @param array $parameters
     * @return mixed
     */
    protected static function template_to_record(EventTemplate $resource, $parameters = array()) {
        $record = static::init_record($resource);
        $record->name = $resource->Name;
        $record->code = $resource->Code;
        return static::apply_parameters($record, $parameters);
    }
}
