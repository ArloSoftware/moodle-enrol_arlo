<?php

namespace enrol_arlo\Arlo\AuthAPI;

use enrol_arlo\Arlo\AuthAPI\Resource\AbstractCollection;
use enrol_arlo\Arlo\AuthAPI\Resource\AbstractResource;
use enrol_arlo\Arlo\AuthAPI\Exception\XMLDeserializerException;

class XmlDeserializer {
    /**
     * @var null|string $resourceClassPath custom path to resource classes.
     */
    private $resourceClassPath;
    /**
     * @var bool
     */
    private $ignoreMissingClasses = true;

    /**
     * XmlDeserializer constructor.
     *
     * @param null $resourceClassPath
     * @param bool $ignoreMissingClasses
     * @param null $loadOptions
     */
    public function __construct($resourceClassPath = null, $ignoreMissingClasses = true, $loadOptions = null) {
        $this->resourceClassPath = null !== $resourceClassPath ? $resourceClassPath : '';
        $this->ignoreMissingClasses = ($ignoreMissingClasses) ? true : false;
        $this->loadOptions = null !== $loadOptions ? $loadOptions : LIBXML_NONET | LIBXML_NOBLANKS;
    }

    /**
     * Main public method to accept Xml and deserialize it.
     *
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function deserialize($data) {
        if ('' === trim($data)) {
            throw new XMLDeserializerException('Invalid XML data, it can not be empty.');
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
            throw new XMLDeserializerException('Root node has no children.');
        }

        $rootClassName = $this->resourceClassPath . $rootNode->nodeName;
        if (!class_exists($rootClassName)) {
            throw new XMLDeserializerException('Root class ' . $rootNode->nodeName . ' does not exist.');
        }

        $rootClassInstance = new $rootClassName();
        $this->parseRootNode($rootNode, $rootClassInstance);
        return $rootClassInstance;

    }

    /**
     * Basic method to direct collection or resource parser.
     *
     * @param \DOMNode $node
     * @param $classInstance
     */
    private function parseRootNode(\DOMNode $node, $classInstance) {
        // Root node is a collection of resources.
        if ($classInstance instanceof AbstractCollection) {
            $this->parseCollectionNode($node, $classInstance);
        }
        // Root node is a single resource.
        if ($classInstance instanceof AbstractResource) {
            $this->parseResourceNode($node, $classInstance);
        }
    }

    /**
     * Parse a link node.
     *
     * @param \DOMNode $node
     * @param $classInstance
     * @throws \Exception
     */
    private function parseLinkNode(\DOMNode $node, $classInstance) {
        // Make sure a Link.
        if ($node->nodeName !== 'Link') {
            throw new XMLDeserializerException('Not a Link');
        }
        // Deal with straight Link that has no expansions.
        if (!$node->hasChildNodes()) {
            $linkClassName = $this->resourceClassPath . $node->nodeName;
            if (!class_exists($linkClassName)) {
                throw new XMLDeserializerException('Class ' . $node->nodeName . ' does not exist.');
            }
            $link = new $linkClassName();
            if ($node->hasAttributes()) {
                foreach ($node->attributes as $attribute) {
                    $attributeName = $attribute->name;
                    $attributeValue = (string)$attribute->value;
                    $this->setValue($link, $attributeName, $attributeValue);
                }
            }
            // Add Link attributes.
            $this->setValue($classInstance, 'Link', $link);
        }
        // Deal with Link that has expansions.
        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $childNode) {
                $childClassName = $this->resourceClassPath . $childNode->nodeName;
                if (!class_exists($childClassName)) {
                    if (!$this->ignoreMissingClasses) {
                        throw new XMLDeserializerException('Class ' . $childNode->nodeName . ' does not exist.');
                    }
                    continue;
                }
                $childClassInstance = new $childClassName();
                $this->parseResourceNode($childNode, $childClassInstance);
                $this->setValue($classInstance, $childNode->nodeName, $childClassInstance);
            }
        }
    }

    /**
     * Parse a collection node, direct to link parser.
     *
     * @param \DOMNode $node
     * @param AbstractCollection $classInstance
     */
    private function parseCollectionNode(\DOMNode $node, AbstractCollection $classInstance) {
        foreach ($node->childNodes as $childNode) {
            if ($childNode->nodeName === 'Link') {
                $this->parseLinkNode($childNode, $classInstance);
            }
        }
    }

    /**
     * Parse a resource node.
     *
     * @param \DOMNode $node
     * @param AbstractResource $classInstance
     * @throws \Exception
     */
    private function parseResourceNode(\DOMNode $node, AbstractResource $classInstance) {
        foreach ($node->childNodes as $childNode) {
            if ($childNode->nodeName === 'Link') {
                $this->parseLinkNode($childNode, $classInstance);
            } else {
                if ($childNode->hasChildNodes()) {
                    // Single element.
                    if (1 === $childNode->childNodes->length) {
                        $this->setValue($classInstance, $childNode->nodeName, $childNode->nodeValue);
                        continue;
                    }
                    // Has multiple elements, parse as resource.
                    if (1 < $childNode->childNodes->length) {
                        // Make class name based on class path and tag name.
                        $childClassName = $this->resourceClassPath . $childNode->nodeName;
                        if (!class_exists($childClassName)) {
                            if (!$this->ignoreMissingClasses) {
                                throw new XMLDeserializerException('Class ' . $childNode->nodeName . ' does not exist.');
                            }
                            continue;
                        }
                        // Initiate class and parse.
                        $childClassInstance = new $childClassName();
                        $this->parseResourceNode($childNode, $childClassInstance);
                        $this->setValue($classInstance, $childNode->nodeName, $childClassInstance);
                    }
                }
            }
        }
    }

    /**
     * Will try set value by public property, setter or adder.
     *
     * @param $class
     * @param $property
     * @param $value
     */
    protected function setValue($class, $property, $value) {
        try {
            $reflectionClass = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            return;
        }
        $properties = [];
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            if ($reflectionProperty->isPublic()) {
                $properties[$reflectionProperty->name] = $reflectionProperty->name;
            }
        }
        if (in_array($property, $properties)) {
            $class->{$property} = $value;
            return;
        }
        $methods = [];
        foreach ($reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            if ($reflectionMethod->isStatic()) {
                continue;
            }
            $methods[$reflectionMethod->name] = $reflectionMethod->name;
        }
        $setter = 'set' . $property;
        if (in_array($setter, $methods)) {
            $class->{$setter}($value);
            return;
        }
        $adder = 'add' . $property;
        if (in_array($adder, $methods)) {
            $class->{$adder}($value);
            return;
        }
    }
}
