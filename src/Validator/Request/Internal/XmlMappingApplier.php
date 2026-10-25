<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

use Duyler\OpenApi\Event\ValidationWarningEvent;
use Duyler\OpenApi\Schema\Model\Enum\XmlNodeType;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use Psr\EventDispatcher\EventDispatcherInterface;
use SimpleXMLElement;

use function array_merge;
use function assert;
use function count;
use function in_array;
use function is_array;
use function sprintf;

/** @internal */
final readonly class XmlMappingApplier
{
    public const string XSI_NAMESPACE = XmlValueReader::XSI_NAMESPACE;

    public function __construct(
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    /**
     * @return array<array-key, mixed>|string|null
     */
    public function mapElement(SimpleXMLElement $element, Schema $schema): array|string|null
    {
        if (XmlValueReader::isXsiNil($element)) {
            return null;
        }

        return match ($this->nodeType($schema)) {
            XmlNodeType::Attribute->value,
            XmlNodeType::Text->value,
            XmlNodeType::Cdata->value => XmlValueReader::textValue($element),
            default => $this->mapComposite($element, $schema),
        };
    }

    /**
     * @return array<array-key, mixed>|string|null
     */
    private function mapComposite(SimpleXMLElement $element, Schema $schema): array|string|null
    {
        $properties = $schema->properties ?? [];

        if ([] !== $properties) {
            return $this->mapProperties($element, $properties);
        }

        if (null !== $schema->items || null !== $schema->prefixItems) {
            return $this->mapArrayNode($element, $schema, null);
        }

        return $this->mapLooseValue($element, $schema);
    }

    /**
     * @return array<array-key, mixed>|string|null
     */
    private function mapLooseValue(SimpleXMLElement $element, Schema $schema): array|string|null
    {
        $read = XmlValueReader::read($element);

        if ('' !== $read) {
            return $read;
        }

        if ($this->allowsCompositeValue($schema)) {
            return [];
        }

        return $read;
    }

    private function allowsCompositeValue(Schema $schema): bool
    {
        $type = $schema->type;

        if (null === $type) {
            return true;
        }

        if (is_array($type)) {
            return in_array('object', $type, true) || in_array('array', $type, true);
        }

        return 'object' === $type || 'array' === $type;
    }

    /**
     * @param array<string, Schema> $properties
     * @return array<array-key, mixed>
     */
    private function mapProperties(SimpleXMLElement $element, array $properties): array
    {
        $mapped = [];

        foreach ($properties as $name => $propertySchema) {
            $mapped = match ($this->nodeType($propertySchema)) {
                XmlNodeType::Attribute->value => $this->mapAttribute($mapped, $element, $name, $propertySchema),
                XmlNodeType::Text->value,
                XmlNodeType::Cdata->value => $this->mapTextProperty($mapped, $element, $name),
                XmlNodeType::None->value => $this->mapInline($mapped, $element, $name, $propertySchema),
                default => $this->mapElementProperty($mapped, $element, $name, $propertySchema),
            };
        }

        return $mapped;
    }

    /**
     * @param array<array-key, mixed> $mapped
     * @return array<array-key, mixed>
     */
    private function mapAttribute(array $mapped, SimpleXMLElement $element, string $name, Schema $schema): array
    {
        $found = $this->findAttribute($element, $name, $schema);

        if (null !== $found) {
            $mapped[$name] = $found;

            return $mapped;
        }

        if (SchemaValueNormalizer::doesTypeIncludeNull($schema->type)) {
            $mapped[$name] = null;
        }

        return $mapped;
    }

    /**
     * @param array<array-key, mixed> $mapped
     * @return array<array-key, mixed>
     */
    private function mapTextProperty(array $mapped, SimpleXMLElement $element, string $name): array
    {
        $mapped[$name] = XmlValueReader::textValue($element);

        return $mapped;
    }

    /**
     * @param array<array-key, mixed> $mapped
     * @return array<array-key, mixed>
     */
    private function mapInline(array $mapped, SimpleXMLElement $element, string $name, Schema $schema): array
    {
        $properties = $schema->properties ?? [];

        if ([] !== $properties) {
            return array_merge($mapped, $this->mapProperties($element, $properties));
        }

        if (null !== $schema->items || null !== $schema->prefixItems) {
            return $this->mapInlineArray($mapped, $element, $name, $schema);
        }

        return $mapped;
    }

    /**
     * @param array<array-key, mixed> $mapped
     * @return array<array-key, mixed>
     */
    private function mapInlineArray(array $mapped, SimpleXMLElement $element, string $name, Schema $schema): array
    {
        $items = $this->mapArrayNode($element, $schema, $name);

        if ([] === $items && [] !== $this->matchedChildren($element, $name, null)) {
            $this->dispatchAmbiguousMappingWarning($name, $name);

            return $this->passThroughChildren($mapped, $element, $name, $name, null);
        }

        $mapped[$name] = $items;

        return $mapped;
    }

    /**
     * @param array<array-key, mixed> $mapped
     * @return array<array-key, mixed>
     */
    private function mapElementProperty(array $mapped, SimpleXMLElement $element, string $name, Schema $schema): array
    {
        $xml = $schema->xml;
        $nodeName = $xml?->name ?? $name;

        if ($this->isArraySchema($schema)) {
            return $this->mapWrappedArray($mapped, $element, $name, $schema, $nodeName);
        }

        $children = $this->matchedChildren($element, $nodeName, $xml?->namespace);

        if ([] === $children) {
            return $mapped;
        }

        $mapped[$name] = $this->mapChildrenValue($children, $schema);

        return $mapped;
    }

    /**
     * @param array<array-key, mixed> $mapped
     * @return array<array-key, mixed>
     */
    private function mapWrappedArray(
        array $mapped,
        SimpleXMLElement $element,
        string $name,
        Schema $schema,
        string $wrapperName,
    ): array {
        $wrappers = $this->matchedChildren($element, $wrapperName, $schema->xml?->namespace);

        if (1 === count($wrappers)) {
            $mapped[$name] = $this->mapArrayNode($wrappers[0], $schema, $name);

            return $mapped;
        }

        $this->dispatchAmbiguousMappingWarning($name, $name);

        $itemSchema = $schema->items;
        $itemName = $itemSchema instanceof Schema ? $itemSchema->xml?->name ?? $name : $name;
        $itemNamespace = $itemSchema instanceof Schema ? $itemSchema->xml?->namespace : null;

        return $this->passThroughChildren($mapped, $element, $itemName, $name, $itemNamespace);
    }

    /**
     * @param list<SimpleXMLElement> $children
     * @return array<array-key, mixed>|string|null
     */
    private function mapChildrenValue(array $children, Schema $schema): array|string|null
    {
        if (1 === count($children)) {
            return $this->mapElement($children[0], $schema);
        }

        $values = [];

        foreach ($children as $child) {
            $values[] = $this->mapElement($child, $schema);
        }

        return $values;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function mapArrayNode(SimpleXMLElement $node, Schema $arraySchema, ?string $propertyName): array
    {
        $prefixItems = $arraySchema->prefixItems;

        if (null !== $prefixItems && [] !== $prefixItems) {
            return $this->mapPrefixItems($node, $prefixItems);
        }

        $itemSchema = $arraySchema->items;
        $itemXml = $itemSchema instanceof Schema ? $itemSchema->xml : null;
        $itemName = $itemXml?->name ?? $propertyName;

        if (null === $itemName) {
            return $this->looseChildren($node);
        }

        $children = $this->matchedChildren($node, $itemName, $itemXml?->namespace);
        $values = [];

        foreach ($children as $child) {
            $values[] = $itemSchema instanceof Schema
                ? $this->mapElement($child, $itemSchema)
                : XmlValueReader::read($child);
        }

        return $values;
    }

    /**
     * @param list<Schema> $prefixItems
     * @return array<array-key, mixed>
     */
    private function mapPrefixItems(SimpleXMLElement $node, array $prefixItems): array
    {
        $values = [];

        foreach ($prefixItems as $itemSchema) {
            $itemName = $itemSchema->xml?->name;

            if (null === $itemName) {
                $nodeName = $node->getName();

                $this->dispatchAmbiguousMappingWarning($nodeName, $nodeName);

                $read = XmlValueReader::read($node);

                return is_array($read) ? $read : [];
            }

            $children = $this->matchedChildren($node, $itemName, $itemSchema->xml?->namespace);

            foreach ($children as $child) {
                $values[] = $this->mapElement($child, $itemSchema);
            }
        }

        return $values;
    }

    /**
     * @param array<array-key, mixed> $mapped
     * @return array<array-key, mixed>
     */
    private function passThroughChildren(
        array $mapped,
        SimpleXMLElement $element,
        string $matchName,
        string $key,
        ?string $namespace,
    ): array {
        $children = $this->matchedChildren($element, $matchName, $namespace);

        if ([] === $children) {
            return $mapped;
        }

        $values = [];

        foreach ($children as $child) {
            $values[] = XmlValueReader::read($child);
        }

        $mapped[$key] = 1 === count($values) ? $values[0] : $values;

        return $mapped;
    }

    /**
     * @return list<array<array-key, mixed>|string|null>
     */
    private function looseChildren(SimpleXMLElement $node): array
    {
        $values = [];

        foreach (XmlValueReader::childElements($node) as $child) {
            $values[] = XmlValueReader::read($child);
        }

        return $values;
    }

    private function findAttribute(SimpleXMLElement $element, string $name, Schema $schema): ?string
    {
        $xml = $schema->xml;
        $attributeName = $xml?->name ?? $name;
        $namespace = $xml?->namespace;

        $attributes = null === $namespace
            ? $element->attributes()
            : $element->attributes($namespace, false);

        assert($attributes instanceof SimpleXMLElement);

        /**
         * @var string $localName
         * @var SimpleXMLElement $value
         */
        foreach ($attributes as $localName => $value) {
            if ($localName === $attributeName) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @return list<SimpleXMLElement>
     */
    private function matchedChildren(SimpleXMLElement $element, string $name, ?string $namespace): array
    {
        $children = null === $namespace
            ? $element->children()
            : $element->children($namespace, false);

        assert($children instanceof SimpleXMLElement);

        $matched = [];

        /**
         * @var string $localName
         * @var SimpleXMLElement $child
         */
        foreach ($children as $localName => $child) {
            if ($localName === $name) {
                $matched[] = $child;
            }
        }

        return $matched;
    }

    private function nodeType(Schema $schema): string
    {
        $xml = $schema->xml;

        if (null === $xml) {
            return $this->defaultNodeType($schema);
        }

        if (null !== $xml->nodeType) {
            return $xml->nodeType;
        }

        if (true === $xml->attribute) {
            return XmlNodeType::Attribute->value;
        }

        return $this->defaultNodeType($schema);
    }

    private function defaultNodeType(Schema $schema): string
    {
        if (null !== $schema->ref || null !== $schema->dynamicRef) {
            return XmlNodeType::None->value;
        }

        if ($this->isArraySchema($schema)) {
            return true === $schema->xml?->wrapped
                ? XmlNodeType::Element->value
                : XmlNodeType::None->value;
        }

        return XmlNodeType::Element->value;
    }

    private function isArraySchema(Schema $schema): bool
    {
        $type = $schema->type;

        if (is_array($type)) {
            return in_array('array', $type, true);
        }

        if ('array' === $type) {
            return true;
        }

        return null !== $schema->items || null !== $schema->prefixItems;
    }

    private function dispatchAmbiguousMappingWarning(string $propertyName, string $propertyPath): void
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        $this->eventDispatcher->dispatch(new ValidationWarningEvent(
            propertyPath: $propertyPath,
            propertyName: $propertyName,
            message: sprintf(
                'XML mapping for "%s" is ambiguous; the node is passed through without XML Object mapping',
                $propertyName,
            ),
        ));
    }
}
