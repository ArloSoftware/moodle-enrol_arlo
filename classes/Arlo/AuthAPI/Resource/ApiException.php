<?php

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * 500 Internal Server Error.
 *
 * Class ApiException
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class ApiException extends AbstractResource {
    /** @var string error code */
    public $Code;
    /** @var string error message */
    public $Message;
}