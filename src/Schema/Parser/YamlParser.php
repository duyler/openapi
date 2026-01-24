<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Contact;
use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Example;
use Duyler\OpenApi\Schema\Model\ExternalDocs;
use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\License;
use Duyler\OpenApi\Schema\Model\Link;
use Duyler\OpenApi\Schema\Model\Links;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Parameters;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\Paths;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Responses;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\SecurityRequirement;
use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Schema\Model\Server;
use Duyler\OpenApi\Schema\Model\Servers;
use Duyler\OpenApi\Schema\Model\Tag;
use Duyler\OpenApi\Schema\Model\Tags;
use Duyler\OpenApi\Schema\Model\Webhooks;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\SchemaParserInterface;
use Override;
use Symfony\Component\Yaml\Yaml;
use Throwable;

use function is_array;
use function is_string;

final readonly class YamlParser implements SchemaParserInterface
{
    #[Override]
    public function parse(string $content): OpenApiDocument
    {
        try {
            $data = $this->parseYaml($content);

            if (false === is_array($data)) {
                throw new InvalidSchemaException('Invalid YAML: expected array at root, got ' . get_debug_type($data));
            }

            return $this->buildDocument($data);
        } catch (InvalidSchemaException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new InvalidSchemaException('Failed to parse YAML: ' . $e->getMessage(), 0, $e);
        }
    }

    private function parseYaml(string $content): mixed
    {
        return Yaml::parse($content, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
    }

    private function buildDocument(array $data): OpenApiDocument
    {
        $this->validateVersion($data);

        return new OpenApiDocument(
            openapi: (string) $data['openapi'],
            info: $this->buildInfo(TypeHelper::asArray($data['info'])),
            jsonSchemaDialect: TypeHelper::asStringOrNull($data['jsonSchemaDialect'] ?? null),
            servers: isset($data['servers']) ? new Servers($this->buildServers(TypeHelper::asList($data['servers']))) : null,
            paths: isset($data['paths']) ? $this->buildPaths(TypeHelper::asArray($data['paths'])) : null,
            webhooks: isset($data['webhooks']) ? $this->buildWebhooks(TypeHelper::asArray($data['webhooks'])) : null,
            components: isset($data['components']) ? $this->buildComponents(TypeHelper::asArray($data['components'])) : null,
            security: isset($data['security']) ? new SecurityRequirement(TypeHelper::asSecurityListMapOrNull($data['security']) ?? []) : null,
            tags: isset($data['tags']) ? new Tags($this->buildTags(TypeHelper::asList($data['tags']))) : null,
            externalDocs: isset($data['externalDocs']) && is_array($data['externalDocs']) ? $this->buildExternalDocs(TypeHelper::asArray($data['externalDocs'])) : null,
        );
    }

    private function validateVersion(array $data): void
    {
        if (!isset($data['openapi'])) {
            throw new InvalidSchemaException('OpenAPI version is required');
        }

        $version = $data['openapi'];
        if (false === is_string($version) || !preg_match('/^3\.[01]\.[0-9]+$/', $version)) {
            throw new InvalidSchemaException('Unsupported OpenAPI version: ' . (string) $version . '. Only 3.0.x and 3.1.x are supported.');
        }
    }

    private function buildInfo(array $data): InfoObject
    {
        if (!isset($data['title']) || !isset($data['version'])) {
            throw new InvalidSchemaException('Info object must have title and version');
        }

        return new InfoObject(
            title: TypeHelper::asString($data['title']),
            version: TypeHelper::asString($data['version']),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            termsOfService: TypeHelper::asStringOrNull($data['termsOfService'] ?? null),
            contact: isset($data['contact']) ? $this->buildContact(TypeHelper::asArray($data['contact'])) : null,
            license: isset($data['license']) ? $this->buildLicense(TypeHelper::asArray($data['license'])) : null,
        );
    }

    private function buildContact(array $data): Contact
    {
        return new Contact(
            name: TypeHelper::asStringOrNull($data['name'] ?? null),
            url: TypeHelper::asStringOrNull($data['url'] ?? null),
            email: TypeHelper::asStringOrNull($data['email'] ?? null),
        );
    }

    private function buildLicense(array $data): License
    {
        return new License(
            name: TypeHelper::asString($data['name']),
            identifier: TypeHelper::asStringOrNull($data['identifier'] ?? null),
            url: TypeHelper::asStringOrNull($data['url'] ?? null),
        );
    }

    /**
     * @return list<Server>
     */
    private function buildServers(array $data): array
    {
        return array_map(fn(array $server) => new Server(
            url: TypeHelper::asString($server['url']),
            description: TypeHelper::asStringOrNull($server['description'] ?? null),
            variables: isset($server['variables']) && is_array($server['variables'])
                ? TypeHelper::asStringMixedMapOrNull(TypeHelper::asArray($server['variables']))
                : null,
        ), array_values($data));
    }

    /**
     * @return list<Tag>
     */
    private function buildTags(array $data): array
    {
        return array_map(fn(array $tag) => new Tag(
            name: TypeHelper::asString($tag['name']),
            description: TypeHelper::asStringOrNull($tag['description'] ?? null),
            externalDocs: isset($tag['externalDocs']) && is_array($tag['externalDocs'])
                ? $this->buildExternalDocs(TypeHelper::asArray($tag['externalDocs']))
                : null,
        ), array_values($data));
    }

    /**
     * @return Paths
     */
    private function buildPaths(array $data): Paths
    {
        $paths = [];

        foreach ($data as $path => $pathItem) {
            $paths[$path] = $this->buildPathItem(TypeHelper::asArray($pathItem));
        }

        /** @var array<string, PathItem> $paths */
        return new Paths($paths);
    }

    /**
     */
    private function buildPathItem(array $data): PathItem
    {
        return new PathItem(
            ref: TypeHelper::asStringOrNull($data['$ref'] ?? null),
            summary: TypeHelper::asStringOrNull($data['summary'] ?? null),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            get: isset($data['get']) ? $this->buildOperation(TypeHelper::asArray($data['get'])) : null,
            put: isset($data['put']) ? $this->buildOperation(TypeHelper::asArray($data['put'])) : null,
            post: isset($data['post']) ? $this->buildOperation(TypeHelper::asArray($data['post'])) : null,
            delete: isset($data['delete']) ? $this->buildOperation(TypeHelper::asArray($data['delete'])) : null,
            options: isset($data['options']) ? $this->buildOperation(TypeHelper::asArray($data['options'])) : null,
            head: isset($data['head']) ? $this->buildOperation(TypeHelper::asArray($data['head'])) : null,
            patch: isset($data['patch']) ? $this->buildOperation(TypeHelper::asArray($data['patch'])) : null,
            trace: isset($data['trace']) ? $this->buildOperation(TypeHelper::asArray($data['trace'])) : null,
            servers: isset($data['servers']) ? new Servers($this->buildServers(TypeHelper::asList($data['servers']))) : null,
            parameters: isset($data['parameters']) ? new Parameters($this->buildParameters(TypeHelper::asList($data['parameters']))) : null,
        );
    }

    /**
     * @return list<Parameter>
     */
    private function buildParameters(array $data): array
    {
        return array_map($this->buildParameter(...), array_values($data));
    }

    /**
     */
    private function buildParameter(array $data): Parameter
    {
        if (!isset($data['name']) || !isset($data['in'])) {
            throw new InvalidSchemaException('Parameter must have name and in fields');
        }

        return new Parameter(
            name: TypeHelper::asString($data['name']),
            in: TypeHelper::asString($data['in']),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            required: (bool) ($data['required'] ?? false),
            deprecated: (bool) ($data['deprecated'] ?? false),
            allowEmptyValue: (bool) ($data['allowEmptyValue'] ?? false),
            style: TypeHelper::asStringOrNull($data['style'] ?? null),
            explode: (bool) ($data['explode'] ?? false),
            allowReserved: (bool) ($data['allowReserved'] ?? false),
            schema: isset($data['schema']) ? $this->buildSchema(TypeHelper::asArray($data['schema'])) : null,
            examples: isset($data['examples']) ? TypeHelper::asStringMixedMapOrNull($data['examples']) : null,
            example: isset($data['example']) && false === is_array($data['example'])
                ? (is_string($data['example']) ? $this->buildExample(['value' => $data['example']]) : null)
                : null,
            content: isset($data['content']) ? $this->buildContent(TypeHelper::asArray($data['content'])) : null,
        );
    }

    /**
     */
    private function buildSchema(array $data): Schema
    {
        return new Schema(
            ref: TypeHelper::asStringOrNull($data['$ref'] ?? null),
            format: TypeHelper::asStringOrNull($data['format'] ?? null),
            title: TypeHelper::asStringOrNull($data['title'] ?? null),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            default: $data['default'] ?? null,
            deprecated: (bool) ($data['deprecated'] ?? false),
            type: TypeHelper::asStringOrNull($data['type'] ?? null),
            const: $data['const'] ?? null,
            multipleOf: TypeHelper::asFloatOrNull($data['multipleOf'] ?? null),
            maximum: TypeHelper::asFloatOrNull($data['maximum'] ?? null),
            exclusiveMaximum: TypeHelper::asFloatOrNull($data['exclusiveMaximum'] ?? null),
            minimum: TypeHelper::asFloatOrNull($data['minimum'] ?? null),
            exclusiveMinimum: TypeHelper::asFloatOrNull($data['exclusiveMinimum'] ?? null),
            maxLength: TypeHelper::asIntOrNull($data['maxLength'] ?? null),
            minLength: TypeHelper::asIntOrNull($data['minLength'] ?? null),
            pattern: TypeHelper::asStringOrNull($data['pattern'] ?? null),
            maxItems: TypeHelper::asIntOrNull($data['maxItems'] ?? null),
            minItems: TypeHelper::asIntOrNull($data['minItems'] ?? null),
            uniqueItems: TypeHelper::asBoolOrNull($data['uniqueItems'] ?? null),
            maxProperties: TypeHelper::asIntOrNull($data['maxProperties'] ?? null),
            minProperties: TypeHelper::asIntOrNull($data['minProperties'] ?? null),
            required: TypeHelper::asStringListOrNull($data['required'] ?? null),
            allOf: isset($data['allOf']) ? array_values(array_map(fn($s) => $this->buildSchema(TypeHelper::asArray($s)), TypeHelper::asArray($data['allOf']))) : null,
            anyOf: isset($data['anyOf']) ? array_values(array_map(fn($s) => $this->buildSchema(TypeHelper::asArray($s)), TypeHelper::asArray($data['anyOf']))) : null,
            oneOf: isset($data['oneOf']) ? array_values(array_map(fn($s) => $this->buildSchema(TypeHelper::asArray($s)), TypeHelper::asArray($data['oneOf']))) : null,
            not: isset($data['not']) ? $this->buildSchema(TypeHelper::asArray($data['not'])) : null,
            discriminator: isset($data['discriminator']) ? $this->buildDiscriminator(TypeHelper::asArray($data['discriminator'])) : null,
            properties: isset($data['properties']) && is_array($data['properties'])
                ? $this->buildProperties(TypeHelper::asArray($data['properties']))
                : null,
            additionalProperties: isset($data['additionalProperties']) && is_array($data['additionalProperties'])
                ? $this->buildSchema(TypeHelper::asArray($data['additionalProperties']))
                : (isset($data['additionalProperties']) ? (bool) $data['additionalProperties'] : null),
            unevaluatedProperties: TypeHelper::asBoolOrNull($data['unevaluatedProperties'] ?? null),
            items: isset($data['items']) && is_array($data['items']) ? $this->buildSchema(TypeHelper::asArray($data['items'])) : null,
            prefixItems: isset($data['prefixItems']) ? array_values(array_map(fn($s) => $this->buildSchema(TypeHelper::asArray($s)), TypeHelper::asArray($data['prefixItems']))) : null,
            contains: isset($data['contains']) ? $this->buildSchema(TypeHelper::asArray($data['contains'])) : null,
            minContains: TypeHelper::asIntOrNull($data['minContains'] ?? null),
            maxContains: TypeHelper::asIntOrNull($data['maxContains'] ?? null),
            patternProperties: isset($data['patternProperties']) && is_array($data['patternProperties'])
                ? $this->buildProperties(TypeHelper::asArray($data['patternProperties']))
                : null,
            propertyNames: isset($data['propertyNames']) ? $this->buildSchema(TypeHelper::asArray($data['propertyNames'])) : null,
            dependentSchemas: isset($data['dependentSchemas']) && is_array($data['dependentSchemas'])
                ? $this->buildProperties(TypeHelper::asArray($data['dependentSchemas']))
                : null,
            if: isset($data['if']) ? $this->buildSchema(TypeHelper::asArray($data['if'])) : null,
            then: isset($data['then']) ? $this->buildSchema(TypeHelper::asArray($data['then'])) : null,
            else: isset($data['else']) ? $this->buildSchema(TypeHelper::asArray($data['else'])) : null,
            unevaluatedItems: isset($data['unevaluatedItems']) ? $this->buildSchema(TypeHelper::asArray($data['unevaluatedItems'])) : null,
            example: $data['example'] ?? null,
            examples: isset($data['examples']) && is_array($data['examples']) ? TypeHelper::asStringMixedMapOrNull($data['examples']) : null,
            enum: TypeHelper::asEnumListOrNull($data['enum'] ?? null),
            contentEncoding: TypeHelper::asStringOrNull($data['contentEncoding'] ?? null),
            contentMediaType: TypeHelper::asStringOrNull($data['contentMediaType'] ?? null),
            contentSchema: TypeHelper::asStringOrNull($data['contentSchema'] ?? null),
            jsonSchemaDialect: TypeHelper::asStringOrNull($data['$schema'] ?? null),
        );
    }

    /**
     * @return array<string, Schema>
     */
    private function buildProperties(array $data): array
    {
        $properties = [];

        foreach ($data as $name => $schema) {
            if (is_string($name) && is_array($schema)) {
                $properties[$name] = $this->buildSchema(TypeHelper::asArray($schema));
            }
        }

        return $properties;
    }

    /**
     */
    private function buildDiscriminator(array $data): Discriminator
    {
        if (!isset($data['propertyName'])) {
            throw new InvalidSchemaException('Discriminator must have propertyName');
        }

        return new Discriminator(
            propertyName: TypeHelper::asString($data['propertyName']),
            mapping: TypeHelper::asStringMapOrNull($data['mapping'] ?? null),
        );
    }

    /**
     */
    private function buildOperation(array $data): Operation
    {
        return new Operation(
            tags: TypeHelper::asStringListOrNull($data['tags'] ?? null),
            summary: TypeHelper::asStringOrNull($data['summary'] ?? null),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            externalDocs: isset($data['externalDocs']) ? $this->buildExternalDocs(TypeHelper::asArray($data['externalDocs'])) : null,
            operationId: TypeHelper::asStringOrNull($data['operationId'] ?? null),
            parameters: isset($data['parameters']) ? new Parameters($this->buildParameters(TypeHelper::asList($data['parameters']))) : null,
            requestBody: isset($data['requestBody']) ? $this->buildRequestBody(TypeHelper::asArray($data['requestBody'])) : null,
            responses: isset($data['responses']) ? $this->buildResponses(TypeHelper::asArray($data['responses'])) : null,
            callbacks: isset($data['callbacks']) ? $this->buildCallbacks(TypeHelper::asArray($data['callbacks'])) : null,
            deprecated: (bool) ($data['deprecated'] ?? false),
            security: isset($data['security']) ? new SecurityRequirement(TypeHelper::asSecurityListMapOrNull($data['security']) ?? []) : null,
            servers: isset($data['servers']) ? new Servers($this->buildServers(TypeHelper::asList($data['servers']))) : null,
        );
    }

    /**
     */
    private function buildRequestBody(array $data): RequestBody
    {
        return new RequestBody(
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            content: isset($data['content']) && is_array($data['content'])
                ? $this->buildContent(TypeHelper::asArray($data['content']))
                : null,
            required: (bool) ($data['required'] ?? false),
        );
    }

    /**
     * @return Content
     */
    private function buildContent(array $data): Content
    {
        $mediaTypes = [];

        foreach ($data as $mediaType => $content) {
            if (is_array($content)) {
                $mediaTypes[$mediaType] = $this->buildMediaType(TypeHelper::asArray($content));
            }
        }

        /** @var array<string, MediaType> $mediaTypes */
        return new Content($mediaTypes);
    }

    /**
     */
    private function buildMediaType(array $data): MediaType
    {
        return new MediaType(
            schema: isset($data['schema']) && is_array($data['schema'])
                ? $this->buildSchema(TypeHelper::asArray($data['schema']))
                : null,
            encoding: TypeHelper::asStringOrNull($data['encoding'] ?? null),
            example: isset($data['example']) && false === is_array($data['example'])
                ? $this->buildExample(['value' => $data['example']])
                : null,
            examples: isset($data['examples']) && is_array($data['examples']) ? TypeHelper::asStringMixedMapOrNull($data['examples']) : null,
        );
    }

    /**
     * @return Responses
     */
    private function buildResponses(array $data): Responses
    {
        $responses = [];

        foreach ($data as $statusCode => $response) {
            if (is_array($response)) {
                $responses[$statusCode] = $this->buildResponse(TypeHelper::asArray($response));
            }
        }

        /** @var array<string, Response> $responses */
        return new Responses($responses);
    }

    /**
     */
    private function buildResponse(array $data): Response
    {
        return new Response(
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

    /**
     * @return Headers
     */
    private function buildHeaders(array $data): Headers
    {
        $headers = [];

        foreach ($data as $headerName => $header) {
            if (is_array($header)) {
                $headers[$headerName] = $this->buildHeader(TypeHelper::asArray($header));
            }
        }

        /** @var array<string, Header> $headers */
        return new Headers($headers);
    }

    /**
     */
    private function buildHeader(array $data): Header
    {
        return new Header(
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            required: (bool) ($data['required'] ?? false),
            deprecated: (bool) ($data['deprecated'] ?? false),
            allowEmptyValue: (bool) ($data['allowEmptyValue'] ?? false),
            schema: isset($data['schema']) && is_array($data['schema'])
                ? $this->buildSchema(TypeHelper::asArray($data['schema']))
                : null,
            example: $data['example'] ?? null,
            examples: isset($data['examples']) && is_array($data['examples']) ? TypeHelper::asStringMixedMapOrNull($data['examples']) : null,
            content: isset($data['content']) && is_array($data['content'])
                ? $this->buildContent(TypeHelper::asArray($data['content']))
                : null,
        );
    }

    /**
     * @return Links
     */
    private function buildLinks(array $data): Links
    {
        $links = [];

        foreach ($data as $linkName => $link) {
            if (is_array($link)) {
                $links[$linkName] = $this->buildLink(TypeHelper::asArray($link));
            }
        }

        /** @var array<string, Link> $links */
        return new Links($links);
    }

    /**
     */
    private function buildLink(array $data): Link
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
                ? $this->buildServer(TypeHelper::asArray($data['server']))
                : null,
        );
    }

    /**
     */
    private function buildExternalDocs(array $data): ExternalDocs
    {
        if (!isset($data['url'])) {
            throw new InvalidSchemaException('External documentation must have url');
        }

        return new ExternalDocs(
            url: TypeHelper::asString($data['url']),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
        );
    }

    /**
     * @return Webhooks
     */
    private function buildWebhooks(array $data): Webhooks
    {
        $webhooks = [];

        foreach ($data as $webhookName => $webhook) {
            if (is_array($webhook)) {
                $webhooks[$webhookName] = $this->buildPathItem(TypeHelper::asArray($webhook));
            }
        }

        /** @var array<string, PathItem> $webhooks */
        return new Webhooks($webhooks);
    }

    /**
     * @return Callbacks
     */
    private function buildCallbacks(array $data): Callbacks
    {
        $callbacks = [];

        foreach ($data as $callbackName => $callback) {
            if (is_array($callback)) {
                foreach ($callback as $expression => $pathItem) {
                    if (is_string($expression) && is_array($pathItem)) {
                        $callbacks[$callbackName][$expression] = $this->buildPathItem(TypeHelper::asArray($pathItem));
                    }
                }
            }
        }

        /** @var array<string, array<string, PathItem>> $callbacks */
        return new Callbacks($callbacks);
    }

    private function buildComponents(array $data): Components
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
        );
    }

    /**
     * @return array<string, Schema>
     */
    private function buildSchemas(array $data): array
    {
        $schemas = [];

        foreach ($data as $name => $schema) {
            if (is_string($name) && is_array($schema)) {
                $schemas[$name] = $this->buildSchema(TypeHelper::asArray($schema));
            }
        }

        return $schemas;
    }

    /**
     * @return array<string, Response>
     */
    private function buildResponsesComponents(array $data): array
    {
        $responses = [];

        foreach ($data as $name => $response) {
            if (is_string($name) && is_array($response)) {
                $responses[$name] = $this->buildResponse(TypeHelper::asArray($response));
            }
        }

        return $responses;
    }

    /**
     * @return array<string, Parameter>
     */
    private function buildParametersComponents(array $data): array
    {
        $parameters = [];

        foreach ($data as $name => $parameter) {
            if (is_string($name) && is_array($parameter)) {
                $parameters[$name] = $this->buildParameter(TypeHelper::asArray($parameter));
            }
        }

        return $parameters;
    }

    /**
     * @return array<string, Example>
     */
    private function buildExamplesComponents(array $data): array
    {
        $examples = [];

        foreach ($data as $name => $example) {
            if (is_string($name) && is_array($example)) {
                $examples[$name] = $this->buildExample(TypeHelper::asArray($example));
            }
        }

        return $examples;
    }

    /**
     */
    private function buildExample(array $data): Example
    {
        return new Example(
            summary: TypeHelper::asStringOrNull($data['summary'] ?? null),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            value: $data['value'] ?? null,
            externalValue: TypeHelper::asStringOrNull($data['externalValue'] ?? null),
        );
    }

    /**
     * @return array<string, RequestBody>
     */
    private function buildRequestBodiesComponents(array $data): array
    {
        $requestBodies = [];

        foreach ($data as $name => $requestBody) {
            if (is_string($name) && is_array($requestBody)) {
                $requestBodies[$name] = $this->buildRequestBody(TypeHelper::asArray($requestBody));
            }
        }

        return $requestBodies;
    }

    /**
     * @return array<string, Header>
     */
    private function buildHeadersComponents(array $data): array
    {
        $headers = [];

        foreach ($data as $name => $header) {
            if (is_string($name) && is_array($header)) {
                $headers[$name] = $this->buildHeader(TypeHelper::asArray($header));
            }
        }

        return $headers;
    }

    /**
     * @return array<string, SecurityScheme>
     */
    private function buildSecuritySchemesComponents(array $data): array
    {
        $securitySchemes = [];

        foreach ($data as $name => $scheme) {
            if (is_string($name) && is_array($scheme)) {
                $securitySchemes[$name] = $this->buildSecurityScheme(TypeHelper::asArray($scheme));
            }
        }

        return $securitySchemes;
    }

    /**
     */
    private function buildSecurityScheme(array $data): SecurityScheme
    {
        if (!isset($data['type'])) {
            throw new InvalidSchemaException('Security scheme must have type');
        }

        return new SecurityScheme(
            type: TypeHelper::asString($data['type']),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            name: TypeHelper::asStringOrNull($data['name'] ?? null),
            in: TypeHelper::asStringOrNull($data['in'] ?? null),
            scheme: TypeHelper::asStringOrNull($data['scheme'] ?? null),
            bearerFormat: TypeHelper::asStringOrNull($data['bearerFormat'] ?? null),
            flows: TypeHelper::asStringOrNull($data['flows'] ?? null),
            authorizationUrl: TypeHelper::asStringOrNull($data['authorizationUrl'] ?? null),
            tokenUrl: TypeHelper::asStringOrNull($data['tokenUrl'] ?? null),
            refreshUrl: TypeHelper::asStringOrNull($data['refreshUrl'] ?? null),
            scopes: isset($data['scopes']) ? TypeHelper::asArray($data['scopes']) : null,
        );
    }

    /**
     * @return array<string, Link>
     */
    private function buildLinksComponents(array $data): array
    {
        $links = [];

        foreach ($data as $name => $link) {
            if (is_string($name) && is_array($link)) {
                $links[$name] = $this->buildLink(TypeHelper::asArray($link));
            }
        }

        return $links;
    }

    /**
     * @return array<string, Callbacks>
     */
    private function buildCallbacksComponents(array $data): array
    {
        $callbacks = [];

        foreach ($data as $name => $callback) {
            if (is_string($name) && is_array($callback)) {
                $callbacks[$name] = $this->buildCallbacks(TypeHelper::asArray($callback));
            }
        }

        return $callbacks;
    }

    /**
     * @return array<string, PathItem>
     */
    private function buildPathItemsComponents(array $data): array
    {
        $pathItems = [];

        foreach ($data as $name => $pathItem) {
            if (is_string($name) && is_array($pathItem)) {
                $pathItems[$name] = $this->buildPathItem(TypeHelper::asArray($pathItem));
            }
        }

        return $pathItems;
    }

    /**
     */
    private function buildServer(array $data): Server
    {
        return new Server(
            url: TypeHelper::asString($data['url']),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            variables: isset($data['variables']) && is_array($data['variables'])
                ? TypeHelper::asStringMixedMapOrNull($data['variables'])
                : null,
        );
    }
}
