<?php

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * Class AbstractResource
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class AbstractResource {
    /** @var string common across most resources. */
    public $Status;

    /** @var string common across all resources. */
    public $CreatedDateTime;

    /** @var string common across all resources. */
    public $LastModifiedDateTime;

    /**
     * @var array collection of link objects (attributes).
     */
    protected $links = array();

    /**
     * @param Link $link
     */
    public function addLink(Link $link) {
        $this->links[] = $link;
    }
}