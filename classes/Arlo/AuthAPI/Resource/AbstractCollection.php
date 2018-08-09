<?php

namespace enrol_arlo\Arlo\AuthAPI\Resource;

/**
 * Class AbstractCollection
 * @package enrol_arlo\Arlo\AuthAPI\Resource
 */
class AbstractCollection implements \IteratorAggregate {
    protected $hasNext = false;
    /**
     * @var array collection of resource objects.
     */
    protected $collection = array();
    /**
     * @var array collection of link objects (attributes).
     */
    protected $links = array();
    /**
     * @return ArrayIterator
     */
    public function getIterator() {
        return new \ArrayIterator($this->collection);
    }

    /**
     * Add link (attributes) to links collection. When encounter "next" paging link
     * set the hasNext flag.
     *
     * @param Link $link
     */
    public function addLink(Link $link) {
        $this->links[] = $link;
        if (isset($link->rel) && strtolower($link->rel) === 'next') {
            $this->hasNext = true;
        }
    }

    /**
     * Any items or empty?
     *
     * @return bool
     */
    public function hasCollection() {
        return 0 !== count($this->collection);
    }

    /**
     * Count of resource items.
     *
     * @return int
     */
    public function count() {
        return count($this->collection);
    }

    /**
     * @return bool
     */
    public function hasNext() {
        return $this->hasNext;
    }
}