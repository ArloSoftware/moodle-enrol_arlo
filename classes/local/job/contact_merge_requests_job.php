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
 * Job using to update contact merge requests information.
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
use enrol_arlo\invalid_persistent_exception;
use enrol_arlo\local\client;
use enrol_arlo\local\config\arlo_plugin_config;
use enrol_arlo\local\persistent\contact_merge_request_persistent;
use enrol_arlo\local\response_processor;
use GuzzleHttp\Psr7\Request;
use Exception;
use coding_exception;
use moodle_exception;

/**
 * Job using to update contact merge requests information.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class contact_merge_requests_job extends job {

    /** @var int TIME_PERIOD_DELAY override base class. */
    const TIME_PERIOD_DELAY = 0;

    /** @var int TIME_PERIOD_EXTENSION override base class. */
    const TIME_PERIOD_EXTENSION = 0;

    /** @var string area */
    const AREA = 'site';

    /** @var string type */
    const TYPE = 'contact_merge_requests';

    /**
     * Run the job.
     *
     * @return bool|mixed
     * @throws Exception
     */
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
                $response = client::get_instance()->send_request($request);
                $collection = response_processor::process($response);
                if ($collection instanceof AbstractCollection && $collection->count() > 0) {
                    foreach ($collection as $resource) {
                        $sourceid               = $resource->RequestID;
                        $sourcecontactid        = $resource->SourceContactInfo->ContactID;
                        $sourcecontactguid      = $resource->SourceContactInfo->UniqueIdentifier;
                        $destinationcontactid   = $resource->DestinationContactInfo->ContactID;
                        $destinationcontactguid = $resource->DestinationContactInfo->UniqueIdentifier;
                        $sourcecreated          = $resource->CreatedDateTime;
                        try {
                            $contactmergerequest = contact_merge_request_persistent::get_record(
                                ['sourceid' => $sourceid]
                            );
                            if (!$contactmergerequest) {
                                $contactmergerequest = new contact_merge_request_persistent();
                                $contactmergerequest->set('sourceid', $sourceid);
                            }
                            $contactmergerequest->set('platform', $pluginconfig->get('platform'));
                            $contactmergerequest->set('sourcecontactid', $sourcecontactid);
                            $contactmergerequest->set('sourcecontactguid', $sourcecontactguid);
                            $contactmergerequest->set('destinationcontactid', $destinationcontactid);
                            $contactmergerequest->set('destinationcontactguid', $destinationcontactguid);
                            $contactmergerequest->set('sourcecreated', $sourcecreated);
                            $contactmergerequest->save();
                            // Update scheduling information on persistent after successfull save.
                            // Note, lastsourceid doesn't get updated as is a GUID.
                            $jobpersistent->set('timelastrequest', time());
                            $jobpersistent->set('lastsourcetimemodified', $sourcecreated);
                            $jobpersistent->update();
                        } catch (moodle_exception $exception) {
                            $this->add_error($exception->getMessage());
                            if ($exception instanceof invalid_persistent_exception || $exception instanceof coding_exception) {
                                debugging($exception->getMessage(), DEBUG_DEVELOPER, $exception->getTrace());
                                return false;
                            }
                        }
                    }
                }
                $hasnext = (bool) $collection->hasNext();
            }
        } catch (moodle_exception $exception) {
            debugging($exception->getMessage(), DEBUG_DEVELOPER, $exception->getTrace());
            $this->add_error($exception->getMessage());
            return false;
        }
        return true;
    }
}
