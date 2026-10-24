<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MultipartNestingDepthException;
use Duyler\OpenApi\Validator\JsonDepthLimit;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use JsonException;

use function array_map;
use function count;
use function in_array;
use function is_array;
use function json_decode;
use function str_ends_with;
use function str_starts_with;
use function substr;

use const JSON_THROW_ON_ERROR;

/** @internal */
final readonly class MultipartInstanceResolver
{
    public const int MAX_NESTING_DEPTH = 1;

    public function __construct(
        private readonly MultipartBodyParser $multipartParser,
        private readonly XmlBodyParser $xmlParser,
        private readonly int $maxNestingDepth = self::MAX_NESTING_DEPTH,
    ) {}

    /**
     * @param list<array{headers: string, content: string}> $parts
     * @param array<int, Encoding>|null $prefixEncoding
     * @return list<mixed>
     */
    public function positionalValues(
        array $parts,
        ?array $prefixEncoding,
        ?Encoding $itemEncoding,
        ?Schema $schema,
    ): array {
        $nodes = $this->positionalNodes($parts, $schema, $prefixEncoding, $itemEncoding, 0);

        return array_map(static fn(MultipartPartNode $node): mixed => $node->value, $nodes);
    }

    /**
     * @param list<array{headers: string, content: string}> $parts
     * @param array<string, Encoding>|null $encoding
     * @return array<string, mixed>
     */
    public function namedValues(array $parts, ?array $encoding, Schema $schema): array
    {
        return $this->namedValuesForLevel($parts, $schema, $encoding, 0);
    }

    /**
     * @param list<array{headers: string, content: string}> $parts
     * @param array<int, Encoding>|null $prefixEncoding
     * @return list<MultipartPartNode>
     */
    private function positionalNodes(
        array $parts,
        ?Schema $schema,
        ?array $prefixEncoding,
        ?Encoding $itemEncoding,
        int $depth,
    ): array {
        $prefixItems = $schema?->prefixItems ?? [];
        $nodes = [];

        foreach ($parts as $index => $part) {
            $nodes[] = $this->partNode(
                part: $part,
                partSchema: $prefixItems[$index] ?? $this->itemsSchema($schema),
                partEncoding: $prefixEncoding[$index] ?? $itemEncoding,
                depth: $depth,
            );
        }

        return $nodes;
    }

    /**
     * @param list<array{headers: string, content: string}> $parts
     * @param array<string, Encoding>|null $encoding
     * @return array<string, string|bool|int|float|array|null>
     */
    private function namedValuesForLevel(array $parts, ?Schema $schema, ?array $encoding, int $depth): array
    {
        $values = [];

        foreach ($this->groupByName($parts) as $name => $entries) {
            $property = $schema?->properties[$name] ?? null;
            $partEncoding = $encoding[$name] ?? null;

            if ($this->collectsAsArray($property, count($entries))) {
                $values[$name] = $this->itemValues($entries, $this->itemsSchema($property), $partEncoding, $depth);

                continue;
            }

            $values[$name] = $this->partNode($entries[0], $property, $partEncoding, $depth)->value;
        }

        return $values;
    }

    /**
     * @param list<array{headers: string, content: string}> $parts
     * @return array<string, list<array{headers: string, content: string}>>
     */
    private function groupByName(array $parts): array
    {
        $grouped = [];

        foreach ($parts as $part) {
            $name = PartDisposition::name(PartHeaders::map($part['headers']));

            if (null === $name) {
                continue;
            }

            $grouped[$name][] = $part;
        }

        return $grouped;
    }

    /**
     * @param list<array{headers: string, content: string}> $entries
     * @return list<string|bool|int|float|array|null>
     */
    private function itemValues(array $entries, ?Schema $itemSchema, ?Encoding $partEncoding, int $depth): array
    {
        $values = [];

        foreach ($entries as $entry) {
            $values[] = $this->partNode($entry, $itemSchema, $partEncoding, $depth)->value;
        }

        return $values;
    }

    /**
     * @param array{headers: string, content: string} $part
     */
    private function partNode(array $part, ?Schema $partSchema, ?Encoding $partEncoding, int $depth): MultipartPartNode
    {
        $headers = PartHeaders::map($part['headers']);
        $content = $this->partContent($part['content']);
        $effectiveType = PartContentType::effective(
            headerValue: $headers['content-type'] ?? null,
            encoding: $partEncoding,
            schema: $partSchema,
        );

        if (str_starts_with($effectiveType, 'multipart/')) {
            return $this->nestedNode(
                headers: $headers,
                content: $content,
                contentType: $headers['content-type'] ?? $effectiveType,
                schema: $partSchema,
                encoding: $partEncoding,
                depth: $depth,
            );
        }

        return MultipartPartNode::leaf($headers, $content, $this->leafValue($content, $effectiveType, $partSchema));
    }

    /**
     * @param array<string, string> $headers
     */
    private function nestedNode(
        array $headers,
        string $content,
        string $contentType,
        ?Schema $schema,
        ?Encoding $encoding,
        int $depth,
    ): MultipartPartNode {
        $childDepth = $depth + 1;
        $this->assertDepthWithinLimit($childDepth);

        $parts = $this->multipartParser->parse($content, $contentType);

        if ($this->isNamedLevel($schema, $encoding)) {
            return MultipartPartNode::nested(
                headers: $headers,
                content: $content,
                children: [],
                value: $this->namedValuesForLevel($parts, $schema, $encoding?->encoding, $childDepth),
            );
        }

        $children = $this->isPositionalLevel($schema, $encoding)
            ? $this->positionalNodes($parts, $schema, $encoding?->prefixEncoding, $encoding?->itemEncoding, $childDepth)
            : $this->plainNodes($parts, $childDepth);

        return MultipartPartNode::nested(
            headers: $headers,
            content: $content,
            children: $children,
            value: array_map(static fn(MultipartPartNode $node): string|bool|int|float|array|null => $node->value, $children),
        );
    }

    /**
     * @param list<array{headers: string, content: string}> $parts
     * @return list<MultipartPartNode>
     */
    private function plainNodes(array $parts, int $depth): array
    {
        $nodes = [];

        foreach ($parts as $part) {
            $nodes[] = $this->partNode($part, null, null, $depth);
        }

        return $nodes;
    }

    private function leafValue(string $content, string $effectiveType, ?Schema $schema): string|bool|int|float|array|null
    {
        if (PartContentType::isJson($effectiveType)) {
            return $this->decodeJson($content) ?? $content;
        }

        if (PartContentType::isXml($effectiveType)) {
            return $this->xmlParser->parse($content);
        }

        if (PartContentType::isWildcard($effectiveType) && $this->expectsStructured($schema)) {
            return $this->decodeJson($content) ?? $content;
        }

        return $content;
    }

    private function expectsStructured(?Schema $schema): bool
    {
        if (null === $schema) {
            return false;
        }

        if (null !== $schema->properties && [] !== $schema->properties) {
            return true;
        }

        if (null !== $schema->items || null !== $schema->prefixItems) {
            return true;
        }

        $type = $schema->type;

        if (is_array($type)) {
            return in_array('object', $type, true) || in_array('array', $type, true);
        }

        return 'object' === $type || 'array' === $type;
    }

    private function isPositionalLevel(?Schema $schema, ?Encoding $encoding): bool
    {
        if (null !== $encoding?->prefixEncoding || null !== $encoding?->itemEncoding) {
            return true;
        }

        return null !== $schema?->prefixItems || null !== $schema?->items;
    }

    private function isNamedLevel(?Schema $schema, ?Encoding $encoding): bool
    {
        if (null !== $encoding && null !== $encoding->encoding && [] !== $encoding->encoding) {
            return true;
        }

        return null !== $schema && null !== $schema->properties && [] !== $schema->properties;
    }

    /**
     * Duplicate parts without an array schema always collect as an array so the TypeMismatch stays truthful.
     */
    private function collectsAsArray(?Schema $property, int $partCount): bool
    {
        if (null !== $property && $this->isArraySchema($property)) {
            return true;
        }

        return $partCount > 1;
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

    private function itemsSchema(?Schema $schema): ?Schema
    {
        $items = $schema?->items ?? null;

        return $items instanceof Schema ? $items : null;
    }

    /**
     * @param list<array{headers: string, content: string}> $parts
     */
    private function assertDepthWithinLimit(int $depth): void
    {
        if ($depth > $this->maxNestingDepth) {
            throw MultipartNestingDepthException::limitReached($this->maxNestingDepth);
        }
    }

    private function partContent(string $content): string
    {
        if (str_ends_with($content, "\r\n")) {
            return substr($content, 0, -2);
        }

        if (str_ends_with($content, "\n")) {
            return substr($content, 0, -1);
        }

        return $content;
    }

    private function decodeJson(string $content): array|int|string|float|bool|null
    {
        try {
            /** @var array|int|string|float|bool|null $decoded */
            $decoded = json_decode(
                $content,
                true,
                JsonDepthLimit::Untrusted->value,
                JSON_THROW_ON_ERROR,
            );

            return $decoded;
        } catch (JsonException) {
            return null;
        }
    }
}
