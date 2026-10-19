<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\Example;
use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Parser\OpenApiBuildContext;
use Duyler\OpenApi\Schema\Parser\TypeHelper;

use Closure;

use function is_array;
use function is_bool;
use function strtolower;

/** @internal */
final readonly class ComponentTreeBuilder
{
    private const string DEPRECATION_VERSION = '3.2.0';

    public function __construct(private OpenApiBuildContext $context) {}

    public function buildRequestBody(array $data): RequestBody
    {
        if (isset($data['$ref'])) {
            return new RequestBody(
                ref: TypeHelper::asString($data['$ref']),
                refSummary: TypeHelper::asStringOrNull($data['summary'] ?? null),
                refDescription: TypeHelper::asStringOrNull($data['description'] ?? null),
            );
        }

        return new RequestBody(
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            content: $this->nullable($data, 'content', $this->buildContent(...)),
            required: (bool) ($data['required'] ?? false),
        );
    }

    /** @param array<string, array<string, mixed>> $data */
    public function buildContent(array $data): Content
    {
        /** @var array<string, array<string, mixed>> $data */
        $mediaTypes = [];

        foreach ($data as $mediaType => $content) {
            $mediaTypes[strtolower($mediaType)] = $this->buildMediaType(TypeHelper::asArray($content));
        }

        return new Content($mediaTypes);
    }

    /** @param array<string, mixed> $data */
    public function buildMediaType(array $data): MediaType
    {
        $this->warnExampleDeprecation($data, 'MediaType Object', 'examples');

        return new MediaType(
            schema: $this->buildSchemaFromKey($data, 'schema'),
            itemSchema: $this->buildSchemaFromKey($data, 'itemSchema'),
            encoding: $this->nullable($data, 'encoding', $this->buildEncodingMap(...)),
            itemEncoding: $this->nullable($data, 'itemEncoding', $this->buildEncoding(...)),
            prefixEncoding: $this->nullable($data, 'prefixEncoding', $this->buildPrefixEncoding(...)),
            example: isset($data['example']) && false === is_array($data['example'])
                ? $this->buildExample(['value' => $data['example']])
                : null,
            examples: isset($data['examples']) && is_array($data['examples']) ? TypeHelper::asStringMixedMapOrNull($data['examples']) : null,
        );
    }

    /** @param array<string, mixed> $data */
    public function buildEncoding(array $data): Encoding
    {
        return new Encoding(
            contentType: TypeHelper::asStringOrNull($data['contentType'] ?? null),
            headers: $this->nullable($data, 'headers', $this->buildHeaders(...)),
            style: TypeHelper::asStringOrNull($data['style'] ?? null),
            explode: TypeHelper::asBoolOrNull($data['explode'] ?? null),
            allowReserved: TypeHelper::asBoolOrNull($data['allowReserved'] ?? null),
            encoding: $this->nullable($data, 'encoding', $this->buildEncodingMap(...)),
            prefixEncoding: $this->nullable($data, 'prefixEncoding', $this->buildPrefixEncoding(...)),
            itemEncoding: $this->nullable($data, 'itemEncoding', $this->buildEncoding(...)),
        );
    }

    /** @param array<string, array<string, mixed>> $data */
    public function buildEncodingMap(array $data): array
    {
        $encodings = [];

        foreach ($data as $name => $encoding) {
            $encodings[$name] = $this->buildEncoding(TypeHelper::asArray($encoding));
        }

        return $encodings;
    }

    /** @param list<array<string, mixed>> $data */
    public function buildPrefixEncoding(array $data): array
    {
        $encodings = [];

        foreach ($data as $encoding) {
            $encodings[] = $this->buildEncoding(TypeHelper::asArray($encoding));
        }

        return $encodings;
    }

    /** @param array<string, mixed> $data */
    public function buildExample(array $data): Example
    {
        return new Example(
            summary: TypeHelper::asStringOrNull($data['summary'] ?? null),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            value: $data['value'] ?? null,
            dataValue: $data['dataValue'] ?? null,
            serializedValue: $data['serializedValue'] ?? null,
            externalValue: TypeHelper::asStringOrNull($data['externalValue'] ?? null),
            serializedExample: TypeHelper::asStringOrNull($data['serializedExample'] ?? null),
        );
    }

    /** @param array<string, array<string, mixed>> $data */
    public function buildHeaders(array $data): Headers
    {
        $headers = [];

        foreach ($data as $headerName => $header) {
            $headers[$headerName] = $this->buildHeader(TypeHelper::asArray($header));
        }

        return new Headers($headers);
    }

    /** @param array<string, mixed> $data */
    public function buildHeader(array $data): Header
    {
        if ($this->context->shouldWarnDeprecation() && isset($data['allowEmptyValue']) && $data['allowEmptyValue']) {
            $this->context->deprecationLogger->warn('allowEmptyValue', 'Header Object', self::DEPRECATION_VERSION);
        }

        return new Header(
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            required: (bool) ($data['required'] ?? false),
            deprecated: (bool) ($data['deprecated'] ?? false),
            allowEmptyValue: (bool) ($data['allowEmptyValue'] ?? false),
            schema: $this->buildSchemaFromKey($data, 'schema'),
            example: $data['example'] ?? null,
            examples: isset($data['examples']) && is_array($data['examples']) ? TypeHelper::asStringMixedMapOrNull($data['examples']) : null,
            content: $this->nullable($data, 'content', $this->buildContent(...)),
        );
    }

    /** @param array<string, mixed> $data */
    public function buildContentOrNull(array $data): ?Content
    {
        return $this->nullable($data, 'content', $this->buildContent(...));
    }

    /** @param array<string, mixed> $data */
    public function buildHeadersOrNull(array $data): ?Headers
    {
        return $this->nullable($data, 'headers', $this->buildHeaders(...));
    }

    /** @param array<string, mixed> $data */
    public function buildRequestBodyOrNull(array $data): ?RequestBody
    {
        return $this->nullable($data, 'requestBody', $this->buildRequestBody(...));
    }

    private function buildSchemaFromKey(array $data, string $key): ?Schema
    {
        if (false === isset($data[$key])) {
            return null;
        }

        $value = $data[$key];

        return is_array($value) || is_bool($value)
            ? $this->context->schemaBuilder->buildSchema($value)
            : null;
    }

    /**
     * @template T
     *
     * @param Closure(array): T $builder
     *
     * @return T|null
     */
    private function nullable(array $data, string $key, Closure $builder): mixed
    {
        return isset($data[$key]) && is_array($data[$key])
            ? $builder(TypeHelper::asArray($data[$key]))
            : null;
    }

    /** @param array<string, mixed> $data */
    private function warnExampleDeprecation(array $data, string $object, string $alternative): void
    {
        if ($this->context->shouldWarnDeprecation() && isset($data['example'])) {
            $this->context->deprecationLogger->warn('example', $object, self::DEPRECATION_VERSION, $alternative);
        }
    }
}
