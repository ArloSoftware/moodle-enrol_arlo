<?php

namespace enrol_arlo\Arlo\AuthAPI;

class RequestUri {
    /** @var string Arlo API entry point. */
    const API_ENTRY_POINT = '/api/2012-02-01/auth/resources/';
    /** @var int COLLECTION_TOP_MAXIMUM maximum records can be returned. */
    const COLLECTION_TOP_MAXIMUM = 250;
    /** @var string Uri host. */
    private $host = '';
    /** @var string Uri dummyhost. */
    private $dummyhost = 'localhost';
    /** @var string Uri path. */
    private $path = '';
    /** @var string resourcePath relative resource path to entry point path.  */
    private $resourcePath = '';
    /** @var integer top number of records returned. */
    private $top;
    /** @var array expands array of expansion options. */
    private $expands = array();
    /** @var array filters array of filter options. */
    private $filters = array();

    private $filterBy;
    /** @var string orderBy set order of records in collection. */
    private $orderBy;

    public function __toString() {
        return self::output(false);

    }

    /**
     * Add expand option.
     *
     * @param $expand
     */
    public function addExpand($expand) {
        $pieces = explode('/', trim($expand));
        $value = null;
        while ($pieces) {
            if (is_null($value)) {
                $value = array_shift($pieces);
            } else {
                $value .= '/' . array_shift($pieces);
            }
            $this->expands[$value] = $value;
        }
    }

    /**
     * Add a filter option.
     *
     * TODO: Set key based on filter resource maybe.
     *
     * @param \enrol_arlo\Arlo\AuthAPI\Filter $filter
     */
    public function addFilter(Filter $filter) {
        $this->filters[$filter->getResourceField()] = $filter;
    }

    /**
     * Add string filter option. Quick workaround to issue #68.
     *
     * @param $filterBy
     */
    public function setFilterBy($filterBy) {
        $this->filterBy = $filterBy;
    }

    public static function createFromUri($uri) {
        if (empty($uri)) {
            throw new \Exception('URI must is empty.');
        }
        if (!is_string($uri)) {
            throw new \Exception('URI must be string.');
        }
        $requestUri = new RequestUri();
        $parts = parse_url($uri);
        if ($parts === false) {
            throw new \Exception("Unable to parse: $uri");
        }
        if (!isset($parts['scheme'])) {
            throw new \Exception("Scheme not present.");
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme != 'https') {
            throw new \Exception("Scheme must be https.");
        }
        if (!isset($parts['host'])) {
            throw new \Exception("Host not present.");
        }
        $host = $parts['host'];
        $requestUri->setHost($host);
        if (isset($parts['path'])) {
            $path = strtolower($parts['path']);
            $requestUri->path = $path;
            $strPos = strpos($path, self::API_ENTRY_POINT);
            if ($strPos === 0) {
                // Get and set resourcePath from path.
                $resourcePath = substr($path, strlen(self::API_ENTRY_POINT), strlen($path));
                $requestUri->setResourcePath($resourcePath);
            }
        }
        return $requestUri;
    }

    /**
     * Use Arlo parameter options to build query part of uri.
     *
     * @param bool $encode
     * @return string
     */
    public function getQueryString($encode = true) {
        $params = array();
        if (!empty($this->top)) {
            $params['top'] = $this->top;
        }
        if (!empty($this->expands)) {
            $params['expand'] = implode(',', $this->expands);
        }
        if (!empty($this->filters)) {
            $pieces = array();
            foreach ($this->filters as $filter) {
                $pieces[] = $filter->export();
            }
            $params['filter'] = implode(' or ', $pieces);
        }
        if (!empty($this->filterBy)) {
            $params['filter'] = $this->filterBy;
        }
        if (!empty($this->orderBy)) {
            $params['orderby'] = $this->orderBy;
        }
        $pieces = array();
        foreach ($params as $key => $value) {
            if (isset($value) && $value !== '') {
                if ($encode) {
                    $pieces[] = rawurlencode($key) . '=' . rawurlencode($value);
                } else {
                    $pieces[] = $key . '=' . $value;
                }
            } else {
                if ($encode) {
                    $pieces[] = rawurlencode($key);
                } else {
                    $pieces[] = $key;
                }
            }
        }
        if (empty($pieces)) {
            return '';
        }
        return '?' . implode('&', $pieces);
    }

    /**
     * Return Host.
     *
     * @return string
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * Return resourcePath.
     *
     * @return string
     */
    public function getResourcePath() {
        return $this->resourcePath;
    }

    /**
     * Return array of expands.
     *
     * @return array
     */
    public function getExpands() {
        return $this->expands;
    }

    /**
     * Return array of filters.
     *
     * @return array
     */
    public function getFilters() {
        return $this->filters;
    }

    /**
     * Return order by string.
     *
     * @return string
     */
    public function getOrderBy() {
        return $this->orderBy;
    }

    /**
     * Return service root.
     *
     * Example:
     *          https://domain/api/2012-02-01/auth/resources/
     *
     * @return string
     * @throws \Exception
     */
    public function getServiceRoot() {
        if (!$this->isValid()) {
            throw new \Exception('Invalid service root.');
        }
        return 'https://' . $this->host . self::API_ENTRY_POINT;
    }

    /**
     * Is Host set.
     *
     * @return bool
     */
    public function isValid() {
        if ($this->host != '') {
            return true;
        }
        return false;
    }

    /**
     * Composes a URI string from its various components.
     *
     * @param bool $encode rawurlencode
     * @return string
     */
    public function output($encode = true) {
        $uri = 'https://';
        $uri .= ($this->host != '') ? $this->host : $this->dummyhost;
        $uri .= self::API_ENTRY_POINT;
        $uri .= $this->resourcePath;
        $uri .= $this->getQueryString($encode);
        return $uri;
    }

    /**
     * Set host based on FQDN.
     *
     * @param $host
     * @throws \Exception
     */
    public function setHost($host) {
        if (!is_string($host)) {
            throw new \Exception('Must be a string');
        }
        // Clean host.
        $cleanedhost = preg_replace('/[^\.\d\w-]/', '', $host);
        if ($cleanedhost == '') {
            throw new \Exception("Invalid: $host");
        }
        $parts = parse_url($cleanedhost);
        if ($parts === false) {
            throw new \Exception("Unable to parse: $cleanedhost");
        }
        if (isset($parts['scheme'])) {
            throw new \Exception("Invalid: $host");
        }
        $this->host = $cleanedhost;
    }

    /**
     * Order option.
     *
     * @param $orderBy
     * @throws \Exception
     */
    public function setOrderBy($orderBy) {
        $this->orderBy = $orderBy;
        if (!is_string($orderBy)){
            throw new \Exception('Must be a string');
        }
    }

    /**
     * Paging option.
     *
     * @param $top
     * @throws \Exception
     */
    public function setPagingTop($top) {
        if (!is_int($top)) {
            throw new \Exception('Must be a integer');
        }
        if ($top > self::COLLECTION_TOP_MAXIMUM) {
            $top = self::COLLECTION_TOP_MAXIMUM;
        }
        $this->top = $top;
    }

    /**
     * Set a relative resource path.
     *
     * Example:
     *          contacts/1/
     *
     * @param $resourcePath
     * @throws \Exception
     */
    public function setResourcePath($resourcePath) {
        if (strpos($resourcePath, '/') === 0) {
            throw new \Exception('Cannot set resourcePath with a leading forward slash.');
        }
        // TODO potential check against resource types.
        $this->resourcePath = $resourcePath;
    }

}