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

namespace enrol_arlo\exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Custom exception.
 */
class abstract_exception extends \Exception {
    /** @var   */
    private $stringidentifier;
    /** @var   */
    private $parameters;
    /** @var   */
    private $apiexception;

    public function __construct($message = '',
                                $code = 0,
                                $stringidentifier = '',
                                $parameters = array(),
                                $apiexception = null,
                                \Throwable $previous = null) {
        $this->message = $message;
        $this->code = $code;
        $this->stringidentifier = $stringidentifier;
        $this->parameters = $parameters;
        $this->apiexception = $apiexception;
    }

    /**
     * @return mixed
     */
    public function get_string_identifier() {
        return $this->stringidentifier;
    }

    /**
     * @return mixed
     */
    public function get_parameters() {
        return $this->parameters;
    }

    /**
     * @return mixed
     */
    public function get_api_exception() {
        return $this->apiexception;
    }
}
