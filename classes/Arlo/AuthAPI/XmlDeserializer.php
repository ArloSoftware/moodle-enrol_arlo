<?php

namespace enrol_arlo\Arlo\AuthAPI;

use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use enrol_arlo\Arlo\AuthAPI\Resource\AbstractCollection;
use enrol_arlo\Arlo\AuthAPI\Resource\AbstractResource;

class XmlDeserializer {
    private $rootNodeName;
    private $rootClass;
    private $resourceClassPath;

    private $propertyInformer;

    /**
     * XmlDeserializer constructor.
     *
     * @param null $resourceClassPath
     * @param null $loadOptions A bit field of LIBXML_* constants
     */
    public function __construct($resourceClassPath = null, $loadOptions = null) {
        $this->resourceClassPath = null !== $resourceClassPath ? $resourceClassPath : '';
        $this->loadOptions = null !== $loadOptions ? $loadOptions : LIBXML_NONET | LIBXML_NOBLANKS;
    }

    /**
     * Return a property info extractor singleton. Will use to check what
     * resource class properties are assessible or writable.
     *
     * @return PropertyInfoExtractor
     */
    public function getPropertyInformer() {
        if (!is_null($this->propertyInformer)) {
            $reflectionExtractor = new ReflectionExtractor();
            $listExtractors = array($reflectionExtractor);
            $this->propertyInformer = new PropertyInfoExtractor($listExtractors);
        }
        return $this->propertyInformer;
    }


    private function setValue() {} // TODO setter for public properties, class setter methods.
    private function addValue() {} // TODO adder for class adder methods.

    public function deserialize($data) {
        if ('' === trim($data)) {
            throw new \Exception('Invalid XML data, it can not be empty.'); // @todo custom exception class
        }

        $internalErrors = libxml_use_internal_errors(true);
        $disableEntities = libxml_disable_entity_loader(true);
        libxml_clear_errors();

        $dom = new \DOMDocument();
        $dom->loadXML($data, $this->loadOptions);

        libxml_use_internal_errors($internalErrors);
        libxml_disable_entity_loader($disableEntities);

        if ($error = libxml_get_last_error()) {
            libxml_clear_errors();
            throw new \Exception($error->message);
        }

        $this->parseRoot($dom);
    }
    private function parseRoot(\DOMDocument $dom) {}
    private function parseLinkNode(\DOMNode $node, $parentClass) {}
}
