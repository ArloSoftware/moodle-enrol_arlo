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
use enrol_arlo\local\persistent\event_persistent;
use GuzzleHttp\Psr7\Request;
use Exception;
use moodle_exception;

class memberships_job extends job {

    public function run() {
        $plugin = api::get_enrolment_plugin();
        $pluginconfig = $plugin->get_plugin_config();
        $lockfactory = static::get_lock_factory();
        $lock = $lockfactory->get_lock($this->get_lock_resource(), self::TIME_LOCK_TIMEOUT);
        try {
            $jobpersistent = $this->get_job_persistent();
            $enrolmentinstance = $plugin::get_instance_record($jobpersistent->get('instanceid'), MUST_EXIST);
            if ($lock) {
                // We don't know how many records we will be retreiving it maybe 5 it maybe 5000,
                // and the page size limit is 250. So we have to keep calling the endpoint and
                // adjusting and the filter each call to so we get all records and don't end up
                // getting same 250 each call.
                $hasnext = true;
                while ($hasnext) {
                    $hasnext = false; // Break paging by default.
                    $uri = new RequestUri();
                    $uri->setHost($pluginconfig->get('platform'));
                    $uri->setResourcePath($jobpersistent->get('endpoint'));
                    $uri->addExpand('Registration/Contact');
                    $resourcename = $jobpersistent->get_resource_name();
                    $resourceidname = $resourcename . 'ID';
                    $expand = 'Registration/' . $resourcename;
                    $uri->addExpand($expand);
                    $uri->setPagingTop(250);
                    $filter = "(LastModifiedDateTime gt datetime('" . $jobpersistent->get('lastsourcetimemodified') . "'))";
                    if ($jobpersistent->get('lastsourceid')) {
                        $filter .= " OR ";
                        $filter .= "(LastModifiedDateTime eq datetime('" . $jobpersistent->get('lastsourcetimemodified') . "')";
                        $filter .= " AND ";
                        $filter .= "RegistrationID gt " . $jobpersistent->get('lastsourceid') . ")";
                    }
                    $uri->setFilterBy($filter);
                    $uri->setOrderBy("LastModifiedDateTime ASC,RegistrationID ASC");
                    $request = new Request('GET', $uri->output(true));
                    $response = client::get_instance()->send_request($request);
                    $collection = api::parse_response($response);
                    if ($collection->count() > 0) {
                        try {
                            foreach ($collection as $registrationresource) {
                                $contactresource = $registrationresource->getContact();
                            }
                        } catch (Exception $exception) {
                            $this->add_error($exception->getMessage());
                        }
                    }
                }
            }
        } catch (Exception $exception) {
            $this->add_error($exception->getMessage());
            return false;
        } finally {
            $lock->release();
        }
        return true;
    }

}
