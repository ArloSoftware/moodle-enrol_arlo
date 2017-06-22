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
     * Apply Auth to options array.
     *
     * @param array $options
     * @return array
     */
    private function applyAuth(array $options) {
        $options['auth'] = [
            $this->apiUsername,
            $this->apiPassword
        ];
        return $options;
    }

    /**
     * Static method to pass response class to check if body contains Xml.
     *
     * @param Response $response
     * @return bool
     */
    public static function isXml(Response $response) {
        $contenttype = $response->getHeaderLine('content-type');
        if (strpos($contenttype, 'application/xml') === false) {
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
    public function request($method, RequestUri $requestUri, array $options = []) {
        if (!$requestUri->isValid()) {
            throw new \Exception('Invalid RequestUri.');
        }
        $options = $this->applyAuth($options);
        try {
            $response = $this->httpClient->request($method, $requestUri->output(), $options);
        } catch (BadResponseException $e) {
            return $e->getResponse();
        }
        return $response;
    }
}