<?php

namespace enrol_arlo\exception;

class invalidcontent_exception extends \moodle_exception {
    public function __construct($parameters, $debuginfo = null) {
        $errorcode = 'error_incorrectcontenttype';
        $module = 'enrol_arlo';
        parent::__construct($errorcode, $module, $link = '', $parameters, $debuginfo);
    }
}
