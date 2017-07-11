<?php

namespace enrol_arlo\Arlo\AuthAPI;

use enrol_arlo\Arlo\AuthAPI\XmlDeserializer;
use enrol_arlo\Arlo\AuthAPI\RequestUri;
use enrol_arlo\Arlo\AuthAPI\Filter;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\BadResponseException;

/**
 * Class Client
 *
 * Wrapper for GuzzleHttp
 *
 * @package enrol_arlo\Arlo\AuthAPI
 */
class Client {
    /** @var \GuzzleHttp\Client httpClient client to used to make requests. */
    private $httpClient;
    /** @var \GuzzleHttp\Psr7\Request lastRequest returns last request. */
    private $lastRequest;
    /** @var int lastRequestTime timestamp of last request. */
    private $lastRequestTime;
    /** @var \GuzzleHttp\Psr7\Response lastResponse returns last response. */
    private $lastResponse;
    /** @var int CONNECTION_TIMEOUT number of seconds to wait while trying to connect. */
    const CONNECTION_TIMEOUT = 30;

    /**
     * Client constructor.
     * 
     * @param $platform
     * @param $apiUsername
     * @param $apiPassword
     * @throws \Exception
     */
    public function __construct() {
        // Initialize Guzzle.
        $this->httpClient = new \GuzzleHttp\Client();
    }

    /**
     * Static method to pass response class to check if body contains Xml.
     *
     * @param Response $response
     * @return bool
     */
    public static function responseBodyIsXml(Response $response) {
        $contentType = $response->getHeaderLine('content-type');
        if (strpos($contentType, 'application/xml') === false) {
            return false;
        }
        return true;
    }

    /**
     * Wrapper method. Send Request. Pass Response back even if exception. The caller
     * if responsible for handling the error.
     *
     * @param $method
     * @param \enrol_arlo\Arlo\AuthAPI\RequestUri $requestUri
     * @param array $headers
     * @param null $body
     * @param array $options
     * @return mixed|\Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    public function request($method, RequestUri $requestUri, array $headers = [], $body = null, array $options = []) {
        if (!$requestUri->isValid()) {
            throw new \Exception('Invalid RequestUri.');
        }
        $options['decode_content'] = 'gzip';
        $options['connect_timeout'] = self::CONNECTION_TIMEOUT;
        $request = new Request($method, $requestUri->output(), $headers, $body);
        $response = $this->httpClient->send($request, $options);
        $this->lastRequest = $request;
        $this->lastRequestTime = time();
        $this->lastResponse = $response;
        return $response;
    }

    /**
     * Return last request.
     *
     * @return Request
     */
    public function getLastRequest() {
        return $this->lastRequest;
    }

    /**
     * Return last request timestamp.
     *
     * @return int
     */
    public function getLastRequestTime() {
        return $this->lastRequestTime;
    }

    /**
     * Return last response.
     *
     * @return Response
     */
    public function getLastResponse() {
        return $this->lastResponse;
    }
}