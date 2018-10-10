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
 * Event Templates job class.
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
use enrol_arlo\local\response_processor;
use enrol_arlo\local\persistent\event_template_persistent;
use GuzzleHttp\Psr7\Request;
use moodle_exception;

/**
 * Event Templates job class.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class event_templates_job extends job {

    /** @var int TIME_PERIOD_DELAY override base class. */
    const TIME_PERIOD_DELAY = 0;

    /** @var int TIME_PERIOD_EXTENSION override base class. */
    const TIME_PERIOD_EXTENSION = 0;

    /** @var string area */
    const AREA = 'site';

    /** @var string type */
    const TYPE = 'event_templates';

    /**
     * Run the job.
     *
     * @return bool|mixed
     * @throws \Exception
     */
    public function run() {
        $pluginconfig = api::get_enrolment_plugin()->get_plugin_config();
        $jobpersistent = $this->get_job_persistent();
        try {
            $hasnext = true;
            while ($hasnext) {
                $hasnext = false; // Break paging by default.
                $uri = new RequestUri();
                $uri->setHost($pluginconfig->get('platform'));
                $uri->setPagingTop(250);
                $uri->setResourcePath('eventtemplates/');
                $uri->addExpand('EventTemplate');
                $filter = "(LastModifiedDateTime gt datetimeoffset('" . $jobpersistent->get('lastsourcetimemodified') . "'))";
                if ($jobpersistent->get('lastsourceid')) {
                    $filter .= " OR ";
                    $filter .= "(LastModifiedDateTime eq datetimeoffset('" . $jobpersistent->get('lastsourcetimemodified') . "')";
                    $filter .= " AND ";
                    $filter .= "TemplateID gt " . $jobpersistent->get('lastsourceid') . ")";
                }
                $uri->setFilterBy($filter);
                $uri->setOrderBy("LastModifiedDateTime ASC,TemplateID ASC");
                $request = new Request('GET', $uri->output(true));
                $response = client::get_instance()->send_request($request);
                $collection = response_processor::process($response);
                if ($collection instanceof AbstractCollection && $collection->count() > 0) {
                    foreach ($collection as $resource) {
                        $sourceid       = $resource->TemplateID;
                        $sourceguid     = $resource->UniqueIdentifier;
                        $code           = $resource->Code;
                        $name           = $resource->Name;
                        $sourcestatus   = $resource->Status;
                        $sourcecreated  = $resource->CreatedDateTime;
                        $sourcemodified = $resource->LastModifiedDateTime;
                        try {
                            $eventtemplate = event_template_persistent::get_record(
                                ['sourceguid' => $sourceguid]
                            );
                            if (!$eventtemplate) {
                                $eventtemplate = new event_template_persistent();
                            }
                            $eventtemplate->set('sourceid', $sourceid);
                            $eventtemplate->set('sourceguid', $sourceguid);
                            $eventtemplate->set('code', $code);
                            $eventtemplate->set('name', $name);
                            $eventtemplate->set('sourcestatus', $sourcestatus);
                            $eventtemplate->set('sourcecreated', $sourcecreated);
                            $eventtemplate->set('sourcemodified', $sourcemodified);
                            $eventtemplate->save();
                            // Update scheduling information on persistent after successfull save.
                            $jobpersistent->set('timelastrequest', time());
                            $jobpersistent->set('lastsourceid', $sourceid);
                            $jobpersistent->set('lastsourcetimemodified', $sourcemodified);
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
