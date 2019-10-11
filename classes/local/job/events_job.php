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
 * Events job class.
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
use enrol_arlo\local\persistent\event_persistent;
use enrol_arlo\local\response_processor;
use GuzzleHttp\Psr7\Request;
use Exception;
use moodle_exception;

/**
 * Events job class.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class events_job extends job {

    /** @var int TIME_PERIOD_DELAY override base class. */
    const TIME_PERIOD_DELAY = 0;

    /** @var int TIME_PERIOD_EXTENSION override base class. */
    const TIME_PERIOD_EXTENSION = 0;

    /** @var string area */
    const AREA = 'site';

    /** @var string type */
    const TYPE = 'events';

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
                $uri->setPagingTop(250);
                $uri->setResourcePath('events/');
                $uri->addExpand('Event/EventTemplate');
                $filter = "(LastModifiedDateTime gt datetime('" . $jobpersistent->get('lastsourcetimemodified') . "'))";
                if ($jobpersistent->get('lastsourceid')) {
                    $filter .= " OR ";
                    $filter .= "(LastModifiedDateTime eq datetime('" . $jobpersistent->get('lastsourcetimemodified') . "')";
                    $filter .= " AND ";
                    $filter .= "EventID gt " . $jobpersistent->get('lastsourceid') . ")";
                }
                $uri->setFilterBy($filter);
                $uri->setOrderBy("LastModifiedDateTime ASC,EventID ASC");
                $request = new Request('GET', $uri->output(true));
                $response = client::get_instance()->send_request($request);
                $collection = response_processor::process($response);
                if ($collection instanceof AbstractCollection && $collection->count() > 0) {
                    foreach ($collection as $resource) {
                        $sourceid       = $resource->EventID;
                        $sourceguid     = $resource->UniqueIdentifier;
                        $code           = $resource->Code;
                        $startdatetime  = $resource->StartDateTime;
                        $finishdatetime = $resource->FinishDateTime;
                        $sourcestatus   = $resource->Status;
                        $sourcecreated  = $resource->CreatedDateTime;
                        $sourcemodified = $resource->LastModifiedDateTime;
                        $eventtemplate  = $resource->getEventTemplate();
                        if ($eventtemplate) {
                            $sourcetemplateid   = $eventtemplate->TemplateID;
                            $sourcetemplateguid = $eventtemplate->UniqueIdentifier;
                        }
                        try {
                            $event = event_persistent::get_record(
                                ['sourceguid' => $sourceguid]
                            );
                            if (!$event) {
                                $event = new event_persistent();
                            }
                            $event->set('sourceid', $sourceid);
                            $event->set('sourceguid', $sourceguid);
                            $event->set('code', $code);
                            $event->set('startdatetime', $startdatetime);
                            $event->set('finishdatetime', $finishdatetime);
                            $event->set('sourcestatus', $sourcestatus);
                            $event->set('sourcecreated', $sourcecreated);
                            $event->set('sourcemodified', $sourcemodified);
                            $event->set('sourcetemplateid', $sourcetemplateid);
                            $event->set('sourcetemplateguid' , $sourcetemplateguid);
                            $event->save();
                            // Update scheduling information on persistent after successfull save.
                            $jobpersistent->set('timelastrequest', time());
                            $jobpersistent->set('lastsourceid', $sourceid);
                            $jobpersistent->set('lastsourcetimemodified', $sourcemodified);
                            $jobpersistent->update();
                        } catch (moodle_exception $exception) {
                            debugging($exception->getMessage(), DEBUG_DEVELOPER);
                            $this->add_error($exception->getMessage());
                        }
                    }
                }
                $hasnext = (bool) $collection->hasNext();
            }
        } catch (moodle_exception $exception) {
            debugging($exception->getMessage(), DEBUG_DEVELOPER);
            $this->add_error($exception->getMessage());
            return false;
        }
        return true;
    }
}
