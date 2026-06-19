<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\BodyParser;

use Duyler\OpenApi\Validator\LibxmlSecuredContext;
use SimpleXMLElement;
use ValueError;

use function array_key_exists;
use function assert;
use function is_array;

use const LIBXML_NOERROR;
use const LIBXML_NONET;
use const LIBXML_NOWARNING;

final readonly class XmlBodyParser
{
    private const int PARSE_OPTIONS = LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING;

    /**
     * @return array<array-key, mixed>|string|null
     */
    public function parse(string $body): array|string|null
    {
        if ('' === trim($body)) {
            return '';
        }

        try {
            return LibxmlSecuredContext::run(static function () use ($body): array|string|null {
                $xml = simplexml_load_string($body, options: self::PARSE_OPTIONS);

                if (false === $xml) {
                    return $body;
                }

                return self::xmlToArray($xml);
            });
        } catch (ValueError) {
            return $body;
        }
    }

    private static function xmlToArray(SimpleXMLElement $xml): array|string|null
    {
        /** @var array<int|string, mixed> $result */
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

        $children = $xml->children();
        assert($children instanceof SimpleXMLElement);

        /**
         * @var string $name
         * @var SimpleXMLElement $child
         */
        foreach ($children as $name => $child) {
            $childValue = self::elementToValue($child);

            if (false === array_key_exists($name, $result)) {
                $result[$name] = $childValue;
                continue;
            }

            /** @var array<array-key, mixed>|string|null $existing */
            $existing = $result[$name];

            if (false === is_array($existing) || false === array_key_exists(0, $existing)) {
                $existing = [$existing];
            }

            $existing[] = $childValue;
            $result[$name] = $existing;
        }

        if ([] === $result) {
            $text = (string) $xml;

            return '' === $text ? null : $text;
        }

        $text = trim((string) $xml);
        if ('' !== $text) {
            $result['#text'] = $text;
        }

        return $result;
    }

    private static function elementToValue(SimpleXMLElement $element): array|string|null
    {
        $attributes = $element->attributes();
        assert($attributes instanceof SimpleXMLElement);
        $hasAttributes = 0 !== $attributes->count();

        $children = $element->children();
        assert($children instanceof SimpleXMLElement);
        $hasChildren = 0 !== $children->count();

        if (false === $hasAttributes && false === $hasChildren) {
            $text = (string) $element;

            return '' === $text ? null : $text;
        }

        return self::xmlToArray($element);
    }
}
