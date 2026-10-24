<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidEncodingConfigurationException;
use Duyler\OpenApi\Validator\JsonDepthLimit;
use JsonException;

use function in_array;
use function is_array;
use function json_decode;
use function str_ends_with;
use function substr;

use const JSON_THROW_ON_ERROR;

/** @internal */
final readonly class OrderedMultipartPlanner
{
    /**
     * @param list<array{headers: string, content: string}> $parts
     */
    public function plan(array $parts, MediaType $mediaType, Schema $schema): OrderedMultipartPlan
    {
        $this->assertConfiguration($mediaType, $schema);

        return new OrderedMultipartPlan(
            parts: $parts,
            prefixEncoding: $mediaType->prefixEncoding,
            itemEncoding: $mediaType->itemEncoding,
            instance: $this->instanceValues($parts, $mediaType, $schema),
        );
    }

    public function apply(OrderedMultipartPlan $plan, MultipartEncodingApplier $applier): void
    {
        $plan->applyTo($applier);
    }

    private function assertConfiguration(MediaType $mediaType, Schema $schema): void
    {
        if (null !== $mediaType->encoding) {
            throw InvalidEncodingConfigurationException::mixedModes();
        }

        if (false === $this->hasArrayModel($mediaType, $schema)) {
            throw InvalidEncodingConfigurationException::missingArrayModel();
        }
    }

    private function hasArrayModel(MediaType $mediaType, Schema $schema): bool
    {
        if (null !== $mediaType->itemSchema) {
            return true;
        }

        $type = $schema->type;

        if (is_array($type)) {
            return in_array('array', $type, true);
        }

        if ('array' === $type) {
            return true;
        }

        return null !== $schema->prefixItems || null !== $schema->items;
    }

    /**
     * @param list<array{headers: string, content: string}> $parts
     * @return list<mixed>
     */
    private function instanceValues(array $parts, MediaType $mediaType, Schema $schema): array
    {
        $prefixItems = $schema->prefixItems ?? [];

        $values = [];

        foreach ($parts as $index => $part) {
            $partSchema = $prefixItems[$index] ?? $schema->items;
            $encoding = $mediaType->prefixEncoding[$index] ?? $mediaType->itemEncoding;

            $values[] = $this->instanceValue(
                content: $part['content'],
                partHeaders: PartHeaders::map($part['headers']),
                partSchema: $partSchema instanceof Schema ? $partSchema : null,
                encoding: $encoding,
            );
        }

        return $values;
    }

    /**
     * @param array<string, string> $partHeaders
     */
    private function instanceValue(string $content, array $partHeaders, ?Schema $partSchema, ?Encoding $encoding): array|int|string|float|bool
    {
        $content = $this->partContent($content);

        $effectiveType = PartContentType::effective(
            headerValue: $partHeaders['content-type'] ?? null,
            encoding: $encoding,
            schema: $partSchema,
        );

        if (false === PartContentType::isJson($effectiveType)) {
            return $content;
        }

        return $this->decodeJson($content) ?? $content;
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
