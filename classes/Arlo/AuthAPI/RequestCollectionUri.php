<?php

namespace enrol_arlo\Arlo\AuthAPI;

class RequestCollectionUri {
    public $top = 100;
    public $expands = array();
    public $datefilters = array();
    public $orderby;

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

    public function addDateFilter($path, $operator, \DateTime $date) {
        $operator = ucfirst(strtolower($operator));
        switch ($operator) {
            case 'Eq':
            case 'Ne':
            case 'Gt':
            case 'Ge':
            case 'Le':
            case 'Le':
                break;
            default:
                throw new \Exception('Operator ' . $operator . ' not supported');
        }
        $datefilter = new \stdClass();
        $datefilter->path = $path;
        $datefilter->operator = $operator;
        $datefilter->date = $date;
        $this->datefilters[$path] = $datefilter;
    }

    public function setOrderBy($orderby) {
        $this->orderby = $orderby;
    }

    public function get_query_string() {
        $params = array('top' => $this->top);

        if (isset($this->expands)) {
            $expand = implode(',', $this->expands);
            $params['expand'] = $expand;
        }

        if (isset($this->datefilters)) {
            $filters = array();
            foreach ($this->datefilters as $datefilter) {
               $filters[] = $datefilter->path . ' ' . $datefilter->operator . ' ' . $datefilter->date->format(DATE_ISO8601);
            }
            $params['filter'] = implode(' or ', $filters);
        }
        if (isset($this->orderby)) {
            $params['orderby'] = $this->orderby;
        }
        $pieces = array();
        foreach ($params as $key => $value) {
            if (isset($value) && $value !== '') {
                $pieces[] = rawurlencode($key)."=".rawurlencode($value);
            } else {
                $pieces[] = rawurlencode($key);
            }
        }
        return implode('&', $pieces);
    }
}