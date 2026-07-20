<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\Example;
use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Schema\Model\Link;
use Duyler\OpenApi\Schema\Model\Links;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Responses;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\SecurityScheme;

use function is_array;
use function strtolower;
use function is_bool;

/**
 * Builds OpenAPI Components and the object types referenced from it:
 * Response / Header / Link / Callbacks / MediaType / RequestBody / Example /
 * Encoding / Content / Responses / Headers / Links.
 *
 * Depends on SchemaBuilder (schemas/media-types/headers embed schemas),
 * PathItemBuilder (pathItems / parameters / callbacks component maps), and
 * SecuritySchemeBuilder (securitySchemes component map). These siblings are
 * resolved lazily through {@see OpenApiBuildContext} so that circular
 * construction with PathItemBuilder is avoided.
 */
final readonly class ComponentsBuilder
{
    private const string DEPRECATION_VERSION = '3.2.0';

    public function __construct(private OpenApiBuildContext $context) {}

    public function buildComponents(array $data): Components
    {
        return new Components(
            schemas: isset($data['schemas']) && is_array($data['schemas'])
                ? $this->buildSchemas(TypeHelper::asArray($data['schemas']))
                : null,
            responses: isset($data['responses']) && is_array($data['responses'])
                ? $this->buildResponsesComponents(TypeHelper::asArray($data['responses']))
                : null,
            parameters: isset($data['parameters']) && is_array($data['parameters'])
                ? $this->buildParametersComponents(TypeHelper::asArray($data['parameters']))
                : null,
            examples: isset($data['examples']) && is_array($data['examples'])
                ? $this->buildExamplesComponents(TypeHelper::asArray($data['examples']))
                : null,
            requestBodies: isset($data['requestBodies']) && is_array($data['requestBodies'])
                ? $this->buildRequestBodiesComponents(TypeHelper::asArray($data['requestBodies']))
                : null,
            headers: isset($data['headers']) && is_array($data['headers'])
                ? $this->buildHeadersComponents(TypeHelper::asArray($data['headers']))
                : null,
            securitySchemes: isset($data['securitySchemes']) && is_array($data['securitySchemes'])
                ? $this->buildSecuritySchemesComponents(TypeHelper::asArray($data['securitySchemes']))
                : null,
            links: isset($data['links']) && is_array($data['links'])
                ? $this->buildLinksComponents(TypeHelper::asArray($data['links']))
                : null,
            callbacks: isset($data['callbacks']) && is_array($data['callbacks'])
                ? $this->buildCallbacksComponents(TypeHelper::asArray($data['callbacks']))
                : null,
            pathItems: isset($data['pathItems']) && is_array($data['pathItems'])
                ? $this->buildPathItemsComponents(TypeHelper::asArray($data['pathItems']))
                : null,
            mediaTypes: isset($data['mediaTypes']) && is_array($data['mediaTypes'])
                ? $this->buildMediaTypesComponents(TypeHelper::asArray($data['mediaTypes']))
                : null,
        );
    }

    public function buildRequestBody(array $data): RequestBody
    {
        return new RequestBody(
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            content: isset($data['content']) && is_array($data['content'])
                ? $this->buildContent(TypeHelper::asArray($data['content']))
                : null,
            required: (bool) ($data['required'] ?? false),
        );
    }

    public function buildContent(array $data): Content
    {
        /** @var array<string, array<string, mixed>> $data */
        $mediaTypes = [];

        foreach ($data as $mediaType => $content) {
            $mediaTypes[strtolower($mediaType)] = $this->buildMediaType(TypeHelper::asArray($content));
        }

        return new Content($mediaTypes);
    }

    public function buildMediaType(array $data): MediaType
    {
        if ($this->context->shouldWarnDeprecation() && isset($data['example'])) {
            $this->context->deprecationLogger->warn(
                'example',
                'MediaType Object',
                self::DEPRECATION_VERSION,
                'examples',
            );
        }

        $schemaBuilder = $this->context->schemaBuilder;

        return new MediaType(
            schema: isset($data['schema']) && (is_array($data['schema']) || is_bool($data['schema']))
                ? $schemaBuilder->buildSchema($data['schema'])
                : null,
            itemSchema: isset($data['itemSchema']) && (is_array($data['itemSchema']) || is_bool($data['itemSchema']))
                ? $schemaBuilder->buildSchema($data['itemSchema'])
                : null,
            encoding: isset($data['encoding']) && is_array($data['encoding'])
                ? $this->buildEncodingMap(TypeHelper::asArray($data['encoding']))
                : null,
            itemEncoding: isset($data['itemEncoding']) && is_array($data['itemEncoding'])
                ? $this->buildEncoding(TypeHelper::asArray($data['itemEncoding']))
                : null,
            prefixEncoding: isset($data['prefixEncoding']) && is_array($data['prefixEncoding'])
                ? $this->buildPrefixEncoding(TypeHelper::asArray($data['prefixEncoding']))
                : null,
            example: isset($data['example']) && false === is_array($data['example'])
                ? $this->buildExample(['value' => $data['example']])
                : null,
            examples: isset($data['examples']) && is_array($data['examples']) ? TypeHelper::asStringMixedMapOrNull($data['examples']) : null,
        );
    }

    public function buildEncoding(array $data): Encoding
    {
        return new Encoding(
            contentType: TypeHelper::asStringOrNull($data['contentType'] ?? null),
            headers: isset($data['headers']) && is_array($data['headers'])
                ? $this->buildHeaders(TypeHelper::asArray($data['headers']))
                : null,
            style: TypeHelper::asStringOrNull($data['style'] ?? null),
            explode: TypeHelper::asBoolOrNull($data['explode'] ?? null),
            allowReserved: TypeHelper::asBoolOrNull($data['allowReserved'] ?? null),
            encoding: isset($data['encoding']) && is_array($data['encoding'])
                ? $this->buildEncodingMap(TypeHelper::asArray($data['encoding']))
                : null,
            prefixEncoding: isset($data['prefixEncoding']) && is_array($data['prefixEncoding'])
                ? $this->buildPrefixEncoding(TypeHelper::asArray($data['prefixEncoding']))
                : null,
            itemEncoding: isset($data['itemEncoding']) && is_array($data['itemEncoding'])
                ? $this->buildEncoding(TypeHelper::asArray($data['itemEncoding']))
                : null,
        );
    }

    /**
     * @return array<string, Encoding>
     */
    public function buildEncodingMap(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $encodings = [];

        foreach ($data as $name => $encoding) {
            $encodings[$name] = $this->buildEncoding(TypeHelper::asArray($encoding));
        }

        return $encodings;
    }

    /**
     * @return array<int, Encoding>
     */
    public function buildPrefixEncoding(array $data): array
    {
        /** @var list<array<string, mixed>> $data */
        $encodings = [];

        foreach ($data as $encoding) {
            $encodings[] = $this->buildEncoding(TypeHelper::asArray($encoding));
        }

        return $encodings;
    }

    public function buildResponses(array $data): Responses
    {
        /** @var array<string, array<string, mixed>> $data */
        $responses = [];

        foreach ($data as $statusCode => $response) {
            $responses[$statusCode] = $this->buildResponse(TypeHelper::asArray($response));
        }

        return new Responses($responses);
    }

    public function buildResponse(array $data): Response
    {
        if (isset($data['$ref'])) {
            return new Response(
                ref: TypeHelper::asString($data['$ref']),
                refSummary: TypeHelper::asStringOrNull($data['summary'] ?? null),
                refDescription: TypeHelper::asStringOrNull($data['description'] ?? null),
            );
        }

        return new Response(
            summary: TypeHelper::asStringOrNull($data['summary'] ?? null),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            headers: isset($data['headers']) && is_array($data['headers'])
                ? $this->buildHeaders(TypeHelper::asArray($data['headers']))
                : null,
            content: isset($data['content']) && is_array($data['content'])
                ? $this->buildContent(TypeHelper::asArray($data['content']))
                : null,
            links: isset($data['links']) && is_array($data['links'])
                ? $this->buildLinks(TypeHelper::asArray($data['links']))
                : null,
        );
    }

    public function buildHeaders(array $data): Headers
    {
        /** @var array<string, array<string, mixed>> $data */
        $headers = [];

        foreach ($data as $headerName => $header) {
            $headers[$headerName] = $this->buildHeader(TypeHelper::asArray($header));
        }

        return new Headers($headers);
    }

    public function buildHeader(array $data): Header
    {
        if ($this->context->shouldWarnDeprecation() && isset($data['allowEmptyValue']) && $data['allowEmptyValue']) {
            $this->context->deprecationLogger->warn(
                'allowEmptyValue',
                'Header Object',
                self::DEPRECATION_VERSION,
            );
        }

        $schemaBuilder = $this->context->schemaBuilder;

        return new Header(
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            required: (bool) ($data['required'] ?? false),
            deprecated: (bool) ($data['deprecated'] ?? false),
            allowEmptyValue: (bool) ($data['allowEmptyValue'] ?? false),
            schema: isset($data['schema']) && (is_array($data['schema']) || is_bool($data['schema']))
                ? $schemaBuilder->buildSchema($data['schema'])
                : null,
            example: $data['example'] ?? null,
            examples: isset($data['examples']) && is_array($data['examples']) ? TypeHelper::asStringMixedMapOrNull($data['examples']) : null,
            content: isset($data['content']) && is_array($data['content'])
                ? $this->buildContent(TypeHelper::asArray($data['content']))
                : null,
        );
    }

    public function buildLinks(array $data): Links
    {
        /** @var array<string, array<string, mixed>> $data */
        $links = [];

        foreach ($data as $linkName => $link) {
            $links[$linkName] = $this->buildLink(TypeHelper::asArray($link));
        }

        return new Links($links);
    }

    public function buildLink(array $data): Link
    {
        return new Link(
            operationRef: TypeHelper::asStringOrNull($data['operationRef'] ?? null),
            ref: TypeHelper::asStringOrNull($data['$ref'] ?? null),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            operationId: TypeHelper::asStringOrNull($data['operationId'] ?? null),
            parameters: isset($data['parameters']) && is_array($data['parameters']) ? TypeHelper::asStringMixedMapOrNull($data['parameters']) : null,
            requestBody: isset($data['requestBody']) && is_array($data['requestBody'])
                ? $this->buildRequestBody(TypeHelper::asArray($data['requestBody']))
                : null,
            server: isset($data['server']) && is_array($data['server'])
                ? $this->context->pathItemBuilder->buildServer(TypeHelper::asArray($data['server']))
                : null,
        );
    }

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

    public function buildCallbacksMap(array $data): Callbacks
    {
        /** @var array<string, array<string, array<string, mixed>>> $data */
        $callbacks = [];

        foreach ($data as $callbackName => $callback) {
            foreach ($callback as $expression => $pathItem) {
                $callbacks[$callbackName][$expression] = $this->context->pathItemBuilder->buildPathItem(TypeHelper::asArray($pathItem));
            }
        }

        return new Callbacks($callbacks);
    }

    /**
     * @return array<string, Schema>
     */
    public function buildSchemas(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $schemas = [];
        $schemaBuilder = $this->context->schemaBuilder;

        foreach ($data as $name => $schema) {
            $schemas[$name] = $schemaBuilder->buildSchema($schema);
        }

        return $schemas;
    }

    /**
     * @return array<string, Response>
     */
    public function buildResponsesComponents(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $responses = [];

        foreach ($data as $name => $response) {
            $responses[$name] = $this->buildResponse(TypeHelper::asArray($response));
        }

        return $responses;
    }

    /**
     * @return array<string, Parameter>
     */
    public function buildParametersComponents(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $parameters = [];

        foreach ($data as $name => $parameter) {
            $parameters[$name] = $this->context->pathItemBuilder->buildParameter(TypeHelper::asArray($parameter));
        }

        return $parameters;
    }

    /**
     * @return array<string, Example>
     */
    public function buildExamplesComponents(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $examples = [];

        foreach ($data as $name => $example) {
            $examples[$name] = $this->buildExample(TypeHelper::asArray($example));
        }

        return $examples;
    }

    /**
     * @return array<string, RequestBody>
     */
    public function buildRequestBodiesComponents(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $requestBodies = [];

        foreach ($data as $name => $requestBody) {
            $requestBodies[$name] = $this->buildRequestBody(TypeHelper::asArray($requestBody));
        }

        return $requestBodies;
    }

    /**
     * @return array<string, Header>
     */
    public function buildHeadersComponents(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $headers = [];

        foreach ($data as $name => $header) {
            $headers[$name] = $this->buildHeader(TypeHelper::asArray($header));
        }

        return $headers;
    }

    /**
     * @return array<string, SecurityScheme>
     */
    public function buildSecuritySchemesComponents(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $securitySchemes = [];

        foreach ($data as $name => $scheme) {
            $securitySchemes[$name] = $this->context->securitySchemeBuilder->buildSecurityScheme(TypeHelper::asArray($scheme));
        }

        return $securitySchemes;
    }

    /**
     * @return array<string, Link>
     */
    public function buildLinksComponents(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $links = [];

        foreach ($data as $name => $link) {
            $links[$name] = $this->buildLink(TypeHelper::asArray($link));
        }

        return $links;
    }

    /**
     * @return array<string, Callbacks>
     */
    public function buildCallbacksComponents(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $callbacks = [];

        foreach ($data as $name => $callback) {
            $callbacks[$name] = $this->buildCallbacksMap(TypeHelper::asArray($callback));
        }

        return $callbacks;
    }

    /**
     * @return array<string, PathItem>
     */
    public function buildPathItemsComponents(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $pathItems = [];

        foreach ($data as $name => $pathItem) {
            $pathItems[$name] = $this->context->pathItemBuilder->buildPathItem(TypeHelper::asArray($pathItem));
        }

        return $pathItems;
    }

    /**
     * @return array<string, MediaType>
     */
    public function buildMediaTypesComponents(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $mediaTypes = [];

        foreach ($data as $name => $mediaType) {
            $mediaTypes[$name] = $this->buildMediaType(TypeHelper::asArray($mediaType));
        }

        return $mediaTypes;
    }
}
