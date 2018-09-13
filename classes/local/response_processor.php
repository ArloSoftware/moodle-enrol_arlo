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
 * Process PSR-7 response.
 *
 * @package   enrol_arlo {@link https://docs.moodle.org/dev/Frankenstyle}
 * @copyright 2018 LearningWorks Ltd {@link http://www.learningworks.co.nz}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_arlo\local;

defined('MOODLE_INTERNAL') || die();

use enrol_arlo\Arlo\AuthAPI\XmlDeserializer;
use GuzzleHttp\Psr7\Response;
use moodle_exception;

class response_processor {

    /**
     * Deserialize response into usable collection of objects.
     *
     * @param Response $response
     * @param int $expectedstatus
     * @return mixed
     * @throws \Exception
     * @throws moodle_exception
     */
    public static function process(Response $response, $expectedstatus = 200) {
        $statuscode = $response->getStatusCode();
        if ($statuscode != $expectedstatus) {
            throw new moodle_exception('httpstatus:' . $statuscode);
        }
        $contenttype = $response->getHeaderLine('content-type');
        if (strpos($contenttype, 'application/xml') === false) {
            throw new moodle_exception('httpstatus:415', 'enrol_arlo');
        }
        $deserializer = new XmlDeserializer("enrol_arlo\\Arlo\\AuthAPI\\Resource\\");
        $stream = $response->getBody();
        $contents = $stream->getContents();
        if ($stream->eof()) {
            $stream->rewind();
        }
        return $deserializer->deserialize($contents);
    }

}
