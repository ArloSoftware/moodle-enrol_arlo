<?php

namespace enrol_arlo\Arlo\AuthAPI;

use enrol_arlo\Arlo\AuthAPI\Filter;

class RequestUri {
    /** @var int COLLECTION_TOP_MAXIMUM maximum records can be returned. */
    const COLLECTION_TOP_MAXIMUM = 100;

    /** @var string Uri host. */
    private $host = '';

    /** @var int|null Uri port. */
    private $port;

    /** @var string Uri path. */
    private $path = '';

    /** @var string Uri query string. */
    private $query = '';

    /** @var integer top number of records returned. */
    private $top;
    /** @var array expands array of expansion options. */
    private $expands = array();
    /** @var array filters array of filter options. */
    private $filters = array();
    /** @var string  orderBy set order of records in collection. */
    private $orderBy;

    /**
     * RequestUri constructor.
     * @param string $uri
     * @throws \Exception
     */
    public function __construct($uri = '') {
        if ($uri != '') {
            $parts = parse_url($uri);
            if ($parts === false) {
                throw new \Exception("Unable to parse URI: $uri");
            }
            $this->applyParts($parts);
        }
    }

    public function __toString() {
        return self::output(false);

    }

    /**
     * Composes a URI string from its various components.
     *
     * @param bool $encode rawurlencode
     * @return string
     */
    public function output($encode = true) {
        $uri = '';

        if ($this->scheme != '') {
            $uri .= $this->scheme . '://';
        }
        $uri .= $this->host;
        $uri .= $this->path;
        $uri .= $this->getQueryString($encode);
        return $uri;
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
        $this->filters[] = $filter;
    }

    /**
     * Apply uri parts to member variables.
     *
     * @param array $parts
     */
    private function applyParts(array $parts) {
        $this->scheme = isset($parts['scheme'])
            ? strtolower($parts['scheme'])
            : '';

        $this->host = isset($parts['host'])
            ? strtolower($parts['host'])
            : '';

        $this->path = isset($parts['path'])
            ? strtolower($parts['path'])
            : '';
    }

    /**
     * Use Arlo parameter options to build query part of uri.
     *
     * @param bool $encode
     * @return string
     */
    public function getQueryString($encode = true) {
        $params = array();
        if (isset($this->top)) {
            $params['top'] = $this->top;
        }
        if (isset($this->expands)) {
            $params['expand'] = implode(',', $this->expands);
        }
        if (isset($this->filters)) {
            $pieces = array();
            foreach ($this->filters as $filter) {
                $pieces[] = $filter->export();
            }
            $params['filter'] = implode(' or ', $pieces);
        }
        if (isset($this->orderBy)) {
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

}