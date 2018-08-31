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
use enrol_arlo\local\persistent\online_activity_persistent;
use GuzzleHttp\Psr7\Request;
use moodle_exception;

class online_activities_job extends job {

    public function run() {
        $pluginconfig = new arlo_plugin_config();
        $jobpersistent = $this->get_job_persistent();
        try {
            $hasnext = true;
            while ($hasnext) {
                $hasnext = false; // Break paging by default.
                $uri = new RequestUri();
                $uri->setHost($pluginconfig->get('platform'));
                $uri->setResourcePath('onlineactivities/');
                $uri->addExpand('OnlineActivity/EventTemplate');
                $filter = "(LastModifiedDateTime gt datetime('" . $jobpersistent->get('lastsourcetimemodified') . "'))";
                if ($jobpersistent->get('lastsourceid')) {
                    $filter .= " OR ";
                    $filter .= "(LastModifiedDateTime eq datetime('" . $jobpersistent->get('lastsourcetimemodified') . "')";
                    $filter .= " AND ";
                    $filter .= "OnlineActivityID gt " . $jobpersistent->get('lastsourceid') . ")";
                }
                $uri->setFilterBy($filter);
                $uri->setOrderBy("LastModifiedDateTime ASC,OnlineActivityID ASC");
                $request = new Request('GET', $uri->output(true));
                $response = api::send_request(client::get_instance(), $request);
                $collection = api::parse_response($response);
                if ($collection instanceof AbstractCollection && $collection->count() > 0) {
                    foreach ($collection as $resource) {
                        $sourceid       = $resource->OnlineActivityID;
                        $sourceguid     = $resource->UniqueIdentifier;
                        $code           = $resource->Code;
                        $name           = $resource->Name;
                        $contenturi     = $resource->ContentUri;
                        $sourcestatus   = $resource->Status;
                        $sourcecreated  = $resource->CreatedDateTime;
                        $sourcemodified = $resource->LastModifiedDateTime;
                        $eventtemplate  = $resource->getEventTemplate();
                        if ($eventtemplate) {
                            $sourcetemplateid   = $eventtemplate->TemplateID;
                            $sourcetemplateguid = $eventtemplate->UniqueIdentifier;
                        }
                        try {
                            $onlineactivity = new online_activity_persistent();
                            $onlineactivity->from_record_property('sourceguid', $sourceguid);
                            $onlineactivity->set('sourceid', $sourceid);
                            $onlineactivity->set('sourceguid', $sourceguid);
                            $onlineactivity->set('code', $code);
                            $onlineactivity->set('name', $name);
                            $onlineactivity->set('contenturi', $contenturi);
                            $onlineactivity->set('sourcestatus', $sourcestatus);
                            $onlineactivity->set('sourcecreated', $sourcecreated);
                            $onlineactivity->set('sourcemodified', $sourcemodified);
                            $onlineactivity->set('sourcetemplateid', $sourcetemplateid);
                            $onlineactivity->set('sourcetemplateguid' , $sourcetemplateguid);
                            $onlineactivity->save();
                            $jobpersistent->set('lastsourceid', $sourceid);
                            $jobpersistent->set('lastsourcetimemodified', $sourcemodified);
                            $jobpersistent->update();
                        } catch (moodle_exception $exception) {
                            $this->add_error($exception->getMessage());
                            debugging($exception->getMessage(), DEBUG_DEVELOPER, $exception->getTrace());
                        }
                    }
                }
                $hasnext = (bool) $collection->hasNext();
            }
        } catch (moodle_exception $exception) {
            $this->add_error($exception->getMessage());
            debugging($exception->getMessage(), DEBUG_DEVELOPER, $exception->getTrace());
            return false;
        }
        return true;
    }

}
