<?php

namespace enrol_arlo\exception;

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
                                \Throwable $previous = null)
    {
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
    public function get_api_exception(){
        return $this->apiexception;
    }
}
