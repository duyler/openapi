<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\BodyParser;

use Duyler\OpenApi\Validator\Exception\BodyTooLargeException;
use Duyler\OpenApi\Validator\LibxmlSecuredContext;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use ValueError;

use function array_key_exists;
use function array_merge;
use function assert;
use function is_array;
use function strlen;

use const LIBXML_NOERROR;
use const LIBXML_NONET;
use const LIBXML_NOWARNING;

final readonly class XmlBodyParser
{
    private const int PARSE_OPTIONS = LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING;
    private const int DEFAULT_MAX_XML_BYTES = 1_048_576;

    public function __construct(
        private readonly int $maxXmlBytes = self::DEFAULT_MAX_XML_BYTES,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * @return array<array-key, mixed>|string|null
     */
    public function parse(string $body): array|string|null
    {
        $length = strlen($body);

        if ($this->maxXmlBytes < $length) {
            throw new BodyTooLargeException(
                actualBytes: $length,
                maxBytes: $this->maxXmlBytes,
            );
        }

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
        } catch (ValueError $e) {
            $this->logger?->debug('XML body parse failure (ValueError), falling back to raw body', [
                'body_length' => $length,
                'exception' => $e,
            ]);

            return $body;
        }
    }

    /**
     * @return array<array-key, mixed>|string|null
     */
    private static function xmlToArray(SimpleXMLElement $xml): array|string|null
    {
        /** @var array<int|string, mixed> $result */
        $result = array_merge(
            self::collectAttributes($xml),
            self::collectChildElements($xml),
        );

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
            $text = (string) $element;

            return '' === $text ? null : $text;
        }

        return self::xmlToArray($element);
    }

    /**
     * @param array<array-key, mixed> $result
     *
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
