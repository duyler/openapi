<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

use SimpleXMLElement;

use function array_key_exists;
use function array_merge;
use function assert;
use function is_array;
use function trim;

/** @internal */
final readonly class XmlValueReader
{
    public const string XSI_NAMESPACE = 'http://www.w3.org/2001/XMLSchema-instance';

    /**
     * @return array<array-key, mixed>|string|null
     */
    public static function read(SimpleXMLElement $xml): array|string|null
    {
        if (self::isXsiNil($xml)) {
            return null;
        }

        /** @var array<int|string, mixed> $result */
        $result = array_merge(
            self::collectAttributes($xml),
            self::collectChildElements($xml),
        );

        if ([] === $result) {
            return (string) $xml;
        }

        $text = self::textValue($xml);

        if ('' !== $text) {
            $result['#text'] = $text;
        }

        return $result;
    }

    public static function textValue(SimpleXMLElement $element): string
    {
        return trim((string) $element);
    }

    /**
     * @return list<SimpleXMLElement>
     */
    public static function childElements(SimpleXMLElement $xml): array
    {
        $children = $xml->children();
        assert($children instanceof SimpleXMLElement);

        $result = [];

        /**
         * @var SimpleXMLElement $child
         */
        foreach ($children as $child) {
            $result[] = $child;
        }

        foreach ($xml->getNamespaces(true) as $prefix => $namespace) {
            if ('' === $prefix) {
                continue;
            }

            $namespaced = $xml->children($namespace);
            assert($namespaced instanceof SimpleXMLElement);

            /**
             * @var SimpleXMLElement $child
             */
            foreach ($namespaced as $child) {
                $result[] = $child;
            }
        }

        return $result;
    }

    public static function isXsiNil(SimpleXMLElement $element): bool
    {
        $attributes = $element->attributes(self::XSI_NAMESPACE, false);
        assert($attributes instanceof SimpleXMLElement);

        $nil = (string) ($attributes['nil'] ?? '');

        return 'true' === $nil || '1' === $nil;
    }

    /**
     * @return array<int|string, mixed>
     */
    private static function collectAttributes(SimpleXMLElement $xml): array
    {
        $result = [];
        $attributes = $xml->attributes();
        assert($attributes instanceof SimpleXMLElement);

        /**
         * @var string $name
         * @var SimpleXMLElement $value
         */
        foreach ($attributes as $name => $value) {
            $result['@' . $name] = (string) $value;
        }

        $namespaces = $xml->getNamespaces(true);

        foreach ($namespaces as $prefix => $namespace) {
            if ('' === $prefix) {
                continue;
            }

            $nsAttributes = $xml->attributes($namespace);
            assert($nsAttributes instanceof SimpleXMLElement);

            /**
             * @var string $name
             * @var SimpleXMLElement $value
             */
            foreach ($nsAttributes as $name => $value) {
                $result['@' . $prefix . ':' . $name] = (string) $value;
            }
        }

        return $result;
    }

    /**
     * @return array<int|string, mixed>
     */
    private static function collectChildElements(SimpleXMLElement $xml): array
    {
        $result = [];
        $children = $xml->children();
        assert($children instanceof SimpleXMLElement);

        /**
         * @var string $name
         * @var SimpleXMLElement $child
         */
        foreach ($children as $name => $child) {
            $result = self::mergeChild($result, $name, self::elementToValue($child));
        }

        $namespaces = $xml->getNamespaces(true);

        foreach ($namespaces as $prefix => $namespace) {
            if ('' === $prefix) {
                continue;
            }

            $nsChildren = $xml->children($namespace);
            assert($nsChildren instanceof SimpleXMLElement);

            /**
             * @var string $name
             * @var SimpleXMLElement $child
             */
            foreach ($nsChildren as $name => $child) {
                $result = self::mergeChild($result, $prefix . ':' . $name, self::elementToValue($child));
            }
        }

        return $result;
    }

    private static function elementToValue(SimpleXMLElement $element): array|string|null
    {
        if (self::isXsiNil($element)) {
            return null;
        }

        $attributes = $element->attributes();
        assert($attributes instanceof SimpleXMLElement);
        $hasAttributes = 0 !== $attributes->count();

        $namespaces = $element->getNamespaces(true);

        foreach ($namespaces as $namespace) {
            $nsAttributes = $element->attributes($namespace);
            assert($nsAttributes instanceof SimpleXMLElement);

            if (0 !== $nsAttributes->count()) {
                $hasAttributes = true;

                break;
            }
        }

        $children = $element->children();
        assert($children instanceof SimpleXMLElement);
        $hasChildren = 0 !== $children->count();

        if (false === $hasChildren) {
            foreach ($namespaces as $namespace) {
                $nsChildren = $element->children($namespace);
                assert($nsChildren instanceof SimpleXMLElement);

                if (0 !== $nsChildren->count()) {
                    $hasChildren = true;

                    break;
                }
            }
        }

        if (false === $hasAttributes && false === $hasChildren) {
            return (string) $element;
        }

        return self::read($element);
    }

    /**
     * @param array<array-key, mixed> $result
     * @return array<array-key, mixed>
     */
    private static function mergeChild(array $result, string $key, array|string|null $value): array
    {
        if (false === array_key_exists($key, $result)) {
            $result[$key] = $value;

            return $result;
        }

        /** @var array<array-key, mixed>|string|null $existing */
        $existing = $result[$key];

        if (false === is_array($existing) || false === array_key_exists(0, $existing)) {
            $existing = [$existing];
        }

        $existing[] = $value;
        $result[$key] = $existing;

        return $result;
    }
}
