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
    /** @var string platformName Arlo plaform name. */
    private $platformName;
    /** @var string apiUsername username. */
    private $apiUsername;
    /** @var string apiPassword password. */
    private $apiPassword;
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
     * @param $platformName
     * @param $apiUsername
     * @param $apiPassword
     * @throws \Exception
     */
    public function __construct($platformName, $apiUsername, $apiPassword) {
        // Check platform name.
        if (empty($platformName)) {
            throw new \Exception("PlatformName cannot be empty.");
        }
        if (!is_string($platformName)) {
            throw new \Exception("PlatformName must be a string.");
        }
        $this->platformName = $platformName;
        // Check apiUsername.
        if (empty($apiUsername)) {
            throw new \Exception("apiUsername name cannot be empty.");
        }
        if (!is_string($apiUsername)) {
            throw new \Exception("apiUsername name must be a string.");
        }
        $this->apiUsername = $apiUsername;
        // Check apiPassword and set.
        if (empty($apiPassword)) {
            throw new \Exception("apiPassword name cannot be empty.");
        }
        if (!is_string($apiPassword)) {
            throw new \Exception("apiPassword name must be a string.");
        }
        $this->apiPassword = $apiPassword;
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
     * @param array $options
     * @return mixed|null|\Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    public function request($method, RequestUri $requestUri, array $options = [], $body = null) {
        if (!$requestUri->isValid()) {
            throw new \Exception('Invalid RequestUri.');
        }
        try {
            $headers = array();
            $options = array();
            $options['auth'] = array(
                $this->apiUsername,
                $this->apiPassword
            );
            $options['decode_content'] = 'gzip';
            $options['connect_timeout'] = self::CONNECTION_TIMEOUT;
            $request = new Request($method, $requestUri->output(), $headers, $body);
            $this->lastRequest = $request;
            $this->lastRequestTime = time();
            $response = $this->httpClient->send($request, $options);
            $this->lastResponse = $response;
        } catch (BadResponseException $e) {
            return $e->getResponse();
        }
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