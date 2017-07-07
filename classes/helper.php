<?php

namespace enrol_arlo;

use stdClass;
use ReflectionClass;
use enrol_arlo\Arlo\AuthAPI\Resource\AbstractResource;
use enrol_arlo\Arlo\AuthAPI\Resource\Registration;

class helper {
    public static function resource_to_record(AbstractResource $resource, $parameters = array()) {
        if ($resource instanceof Registration) {
            return static::registration_to_record($resource, $parameters);
        }
    }

    protected static function init_record(AbstractResource $resource) {
        $reflect = new ReflectionClass($resource);
        $record = new stdClass();
        $record->platform = get_config('enrol_arlo', 'platform');
        $idfieldname = $reflect->getShortName() . 'ID';
        $record->sourceid = $resource->{$idfieldname};
        $record->sourceguid = $resource->UniqueIdentifier;
        $record->sourcestatus = $resource->Status;
        $record->sourcecreated = $resource->CreatedDateTime;
        $record->sourcemodified = $resource->LastModifiedDateTime;
        return $record;
    }
    
    protected static function apply_parameters($record, $parameters) {
        foreach ($parameters as $key => $value) {
            if (is_string($key)) {
                $record->{$key} = $value;
            }
        }
        return $record;
    }

    protected static function registration_to_record(Registration $resource, $parameters = array()) {
        $record = static::init_record($resource);
        $record->attendance = $resource->Attendance;
        $record->grade = $resource->Grade;
        $record->lastactivity = $resource->LastActivityDateTime;
        $record->progressstatus = $resource->ProgressStatus;
        $record->progresspercent = $resource->ProgressPercent;

        $contact = $resource->getContact();
        if (isset($contact )) {
            $record->sourcecontactid = $contact->ContactID;
            $record->sourcecontactguid = $contact->UniqueIdentifier;
        }

        $event = $resource->getEvent();
        if (isset($event)) {
           $record->sourceeventid = $event->EventID;
           $record->sourceeventguid = $event->UniqueIdentifier;
        }

        $onlineactivity = $resource->getOnlineActivity();
        if (isset($onlineactivity)) {
            $record->sourceonlineactivityid = $onlineactivity->OnlineActivityID;
            $record->sourceonlineactivityguid = $onlineactivity->UniqueIdentifier;
        }

        return static::apply_parameters($record, $parameters);
    }
}