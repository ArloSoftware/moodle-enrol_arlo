<?php

namespace enrol_arlo\Arlo\AuthAPI;

use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyAccess\PropertyAccess;

use enrol_arlo\Arlo\AuthAPI\Resource\AbstractCollection;
use enrol_arlo\Arlo\AuthAPI\Resource\AbstractResource;

class XmlDeserializer {
    //private $rootNodeName;
    private $rootClassInstance;
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
        if (is_null($this->propertyInformer)) {
            $reflectionExtractor = new ReflectionExtractor();
            $listExtractors = array($reflectionExtractor);
            $accessExtractors = array($reflectionExtractor);
            $this->propertyInformer = new PropertyInfoExtractor(
                $listExtractors,
                array(),
                array(),
                $accessExtractors
            );
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

        $rootNode = $dom->firstChild;
        if (!$rootNode->hasChildNodes()) {
            throw new \Exception('Root node has no children.');
        }

        $rootClassName = $this->resourceClassPath . $rootNode->nodeName;
        if (!class_exists($rootClassName)) {
            throw new \Exception('Root class ' . $rootNode->nodeName . ' does not exist.');
        }

        $rootClassInstance = new $rootClassName();
        $this->parseRootNode($rootNode, $rootClassInstance);
        print_object($rootClassInstance);

    }
    private function parseRootNode(\DOMNode $node, $classInstance) {
        foreach ($node->childNodes as $node) {
            // Handle Link.
            if ($node->nodeName === 'Link') {
                $this->parseLinkNode($node, $classInstance);
            }
        }
    }
    private function parseLinkNode(\DOMNode $node, $classInstance) {
        $propertyInfo = $this->getPropertyInformer();
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        if (!$node->hasChildNodes()) {
            $linkClassName = $this->resourceClassPath . $node->nodeName;
            if (!class_exists($linkClassName)) {
                throw new \Exception('Class ' . $node->nodeName . ' does not exist.');
            }
            $link = new $linkClassName();
            if ($node->hasAttributes()) {
                $properties = $propertyInfo->getProperties(get_class($link));
                foreach ($node->attributes as $attribute) {
                    $attributeName = $attribute->name;
                    $attributeValue = (string) $attribute->value;
                    if (in_array($attributeName, $properties)) {
                        if ($propertyInfo->isWritable($linkClassName, $attributeName)) {
                            $propertyAccessor->setValue($link, $attributeName, $attributeValue);
                        }
                    }
                }
                // Add Link attributes.
                $adder = 'addLink';
                if (method_exists($classInstance, $adder)) {
                    $classInstance->{$adder}($link);
                }
            }

        } else {

        }
    }
}
