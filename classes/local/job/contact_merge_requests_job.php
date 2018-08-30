<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local\job;

defined('MOODLE_INTERNAL') || die();

use enrol_arlo\api;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\Arlo\AuthAPI\Resource\AbstractCollection;
use enrol_arlo\local\client;
use enrol_arlo\local\config\arlo_plugin_config;
use enrol_arlo\local\persistent\contact_merge_request_persistent;
use GuzzleHttp\Psr7\Request;
use Exception;
use moodle_exception;

class contact_merge_requests_job extends job {

    public function run() {
        $pluginconfig = new arlo_plugin_config();
        $jobpersistent = $this->get_job_persistent();
        try {
            $hasnext = true;
            while ($hasnext) {
                $hasnext = false; // Break paging by default.
                $uri = new RequestUri();
                $uri->setHost($pluginconfig->get('platform'));
                $uri->setResourcePath('contactmergerequests/');
                $uri->addExpand('ContactMergeRequest');
                $filter = "(CreatedDateTime gt datetime('" . $jobpersistent->get('lastsourcetimemodified') . "'))";
                $uri->setFilterBy($filter);
                $uri->setOrderBy('CreatedDateTime ASC');
                $request = new Request('GET', $uri->output(true));
                $response = api::send_request(client::get_instance(), $request);
                $collection = api::parse_response($response);
                if ($collection instanceof AbstractCollection && $collection->count() > 0) {
                    foreach ($collection as $resource) {
                        $sourceid               = $resource->RequestID;
                        $sourcecontactid        = $resource->SourceContactInfo->ContactID;
                        $sourcecontactguid      = $resource->SourceContactInfo->UniqueIdentifier;
                        $destinationcontactid   = $resource->DestinationContactInfo->ContactID;
                        $destinationcontactguid = $resource->DestinationContactInfo->UniqueIdentifier;
                        $sourcecreated          = $resource->CreatedDateTime;
                        try {
                            $contactmergerequest = new contact_merge_request_persistent();
                            $contactmergerequest->from_record_property('sourceid', $sourceid);
                            $contactmergerequest->set('platform', $pluginconfig->get('platform'));
                            $contactmergerequest->set('sourcecontactid', $sourcecontactid);
                            $contactmergerequest->set('sourcecontactguid', $sourcecontactguid);
                            $contactmergerequest->set('destinationcontactid', $destinationcontactid);
                            $contactmergerequest->set('destinationcontactguid', $destinationcontactguid);
                            $contactmergerequest->set('sourcecreated', $sourcecreated);
                            $contactmergerequest->save();
                            $jobpersistent->set('lastsourcetimemodified', $sourcecreated);
                            $jobpersistent->update();
                        } catch (moodle_exception $exception) {
                            $this->add_error($exception->getMessage());
                        }
                    }
                }
                $hasnext = (bool) $collection->hasNext();
            }
        } catch (moodle_exception $exception) {
            $this->add_error($exception->getMessage());
            return false;
        }
        return true;
    }
}
