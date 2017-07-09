<?php

namespace enrol_arlo\Arlo\AuthAPI;

use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyAccess\PropertyAccess;

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
     * @var object $propertyInformer return a single PropertyInfo instance.
     */
    private $propertyInformer;

    /**
     * XmlDeserializer constructor.
     *
     * @param null $resourceClassPath
     * @param null $loadOptions A bit field of LIBXML_* constants
     */
    public function __construct($resourceClassPath = null, $ignoreMissingClasses = true, $loadOptions = null) {
        $this->resourceClassPath = null !== $resourceClassPath ? $resourceClassPath : '';
        $this->ignoreMissingClasses = ($ignoreMissingClasses) ? true : false;
        $this->loadOptions = null !== $loadOptions ? $loadOptions : LIBXML_NONET | LIBXML_NOBLANKS;
    }

    /**
     * Return a property info extractor singleton. Will use to check what
     * resource class properties are writable.
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
        $propertyInfo = $this->getPropertyInformer();
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
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
                $properties = $propertyInfo->getProperties($link);
                foreach ($node->attributes as $attribute) {
                    $attributeName = $attribute->name;
                    $attributeValue = (string)$attribute->value;
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
        }
        // Deal with Link that has expansions.
        if ($node->hasChildNodes()) {
            foreach($node->childNodes as $childNode) {
                $childClassName = $this->resourceClassPath . $childNode->nodeName;
                if (!class_exists($childClassName)) {
                    if (!$this->ignoreMissingClasses) {
                        throw new XMLDeserializerException('Class ' . $childNode->nodeName . ' does not exist.');
                    }
                    continue;
                }
                $childClassInstance = new $childClassName();
                $this->parseResourceNode($childNode, $childClassInstance);
                // Do we have a setter on the passed in class.
                $setter = 'set' . $childNode->nodeName;
                if (method_exists($classInstance, $setter)) {
                    $classInstance->{$setter}($childClassInstance);
                }
                // Do we have an adder on the passed in class.
                $adder = 'add' . $childNode->nodeName;
                if (method_exists($classInstance, $adder)) {
                    $classInstance->{$adder}($childClassInstance);
                }
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
        $propertyInfo = $this->getPropertyInformer();
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        foreach ($node->childNodes as $childNode) {
            if ($childNode->nodeName === 'Link') {
                $this->parseLinkNode($childNode, $classInstance);
            } else {
                if ($childNode->hasChildNodes()) {
                    // Single element.
                    if (1 === $childNode->childNodes->length) {
                        if ($propertyInfo->isWritable($classInstance, $childNode->nodeName)) {
                            $propertyAccessor->setValue($classInstance, $childNode->nodeName, $childNode->nodeValue);
                        }
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
                        // Do we have a setter on the passed in class.
                        $setter = 'set' . $childNode->nodeName;
                        if (method_exists($classInstance, $setter)) {
                            $classInstance->{$setter}($childClassInstance);
                        }
                    }
                }
            }
        }
    }
}
