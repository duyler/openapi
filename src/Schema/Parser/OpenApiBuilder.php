<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Contact;
use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Encoding;
use Duyler\OpenApi\Schema\Model\Example;
use Duyler\OpenApi\Schema\Model\ExternalDocs;
use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Headers;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\License;
use Duyler\OpenApi\Schema\Model\Link;
use Duyler\OpenApi\Schema\Model\Links;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\OAuthFlow;
use Duyler\OpenApi\Schema\Model\OAuthFlows;
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
use Duyler\OpenApi\Schema\Model\Xml;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\SchemaParserInterface;
use Override;
use Throwable;

use function is_array;
use function is_string;
use function assert;
use function sprintf;

use function array_key_exists;

use function is_bool;

use function in_array;

use function gettype;

use const FILTER_VALIDATE_URL;

abstract class OpenApiBuilder implements SchemaParserInterface
{
    private const string VERSION_PATTERN = '/^3\.[0-2]\.[0-9]+$/';
    private const string DEPRECATION_VERSION = '3.2.0';

    protected string $documentVersion = '';
    protected DeprecationLogger $deprecationLogger;

    public function __construct(
        ?DeprecationLogger $deprecationLogger = null,
    ) {
        $this->deprecationLogger = $deprecationLogger ?? new DeprecationLogger();
    }

    #[Override]
    public function parse(string $content): OpenApiDocument
    {
        try {
            $data = $this->parseContent($content);

            if (false === is_array($data)) {
                throw new InvalidSchemaException(
                    sprintf(
                        'Invalid %s: expected object at root, got %s',
                        $this->getFormatName(),
                        gettype($data),
                    ),
                );
            }

            return $this->buildDocument($data);
        } catch (InvalidSchemaException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new InvalidSchemaException(
                sprintf(
                    'Failed to parse %s: %s',
                    $this->getFormatName(),
                    $e->getMessage(),
                ),
                0,
                $e,
            );
        }
    }

    /**
     * Parse raw content into array.
     *
     * @return mixed
     */
    abstract protected function parseContent(string $content): mixed;

    /**
     * Get the format name for error messages.
     */
    abstract protected function getFormatName(): string;

    protected function buildDocument(array $data): OpenApiDocument
    {
        $this->validateVersion($data);
        $this->documentVersion = (string) $data['openapi'];

        $self = TypeHelper::asStringOrNull($data['$self'] ?? null);
        $this->validateSelfUri($self);

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
            self: $self,
        );
    }

    protected function validateSelfUri(?string $self): void
    {
        if (null === $self) {
            return;
        }

        if (false === filter_var($self, FILTER_VALIDATE_URL)) {
            throw new InvalidSchemaException(
                sprintf('Invalid $self URI: %s', $self),
            );
        }
    }

    protected function validateVersion(array $data): void
    {
        if (false === isset($data['openapi'])) {
            throw new InvalidSchemaException('OpenAPI version is required');
        }

        $version = $data['openapi'];
        if (false === is_string($version) || 1 !== preg_match(self::VERSION_PATTERN, $version)) {
            throw new InvalidSchemaException(
                sprintf(
                    'Unsupported OpenAPI version: %s. Only 3.0.x, 3.1.x and 3.2.x are supported.',
                    (string) $version,
                ),
            );
        }
    }

    protected function shouldWarnDeprecation(): bool
    {
        return version_compare($this->documentVersion, self::DEPRECATION_VERSION, '>=');
    }

    protected function buildInfo(array $data): InfoObject
    {
        if (false === isset($data['title']) || false === isset($data['version'])) {
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

    protected function buildContact(array $data): Contact
    {
        return new Contact(
            name: TypeHelper::asStringOrNull($data['name'] ?? null),
            url: TypeHelper::asStringOrNull($data['url'] ?? null),
            email: TypeHelper::asStringOrNull($data['email'] ?? null),
        );
    }

    protected function buildLicense(array $data): License
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
    protected function buildServers(array $data): array
    {
        return array_map(fn(array $server) => new Server(
            url: TypeHelper::asString($server['url']),
            description: TypeHelper::asStringOrNull($server['description'] ?? null),
            variables: isset($server['variables']) && is_array($server['variables'])
                ? TypeHelper::asStringMixedMapOrNull(TypeHelper::asArray($server['variables']))
                : null,
            name: TypeHelper::asStringOrNull($server['name'] ?? null),
        ), array_values($data));
    }

    /**
     * @return list<Tag>
     */
    protected function buildTags(array $data): array
    {
        return array_map(fn(array $tag) => new Tag(
            name: TypeHelper::asString($tag['name']),
            description: TypeHelper::asStringOrNull($tag['description'] ?? null),
            externalDocs: isset($tag['externalDocs']) && is_array($tag['externalDocs'])
                ? $this->buildExternalDocs(TypeHelper::asArray($tag['externalDocs']))
                : null,
            summary: TypeHelper::asStringOrNull($tag['summary'] ?? null),
            parent: TypeHelper::asStringOrNull($tag['parent'] ?? null),
            kind: TypeHelper::asStringOrNull($tag['kind'] ?? null),
        ), array_values($data));
    }

    protected function buildPaths(array $data): Paths
    {
        /** @var array<string, array<string, mixed>> $data */
        $paths = [];

        foreach ($data as $path => $pathItem) {
            $paths[$path] = $this->buildPathItem(TypeHelper::asArray($pathItem));
        }

        /** @var array<string, PathItem> $paths */
        return new Paths($paths);
    }

    protected function buildPathItem(array $data): PathItem
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
            query: isset($data['query']) ? $this->buildOperation(TypeHelper::asArray($data['query'])) : null,
            additionalOperations: isset($data['additionalOperations']) && is_array($data['additionalOperations'])
                ? $this->buildAdditionalOperations(TypeHelper::asArray($data['additionalOperations']))
                : null,
            servers: isset($data['servers']) ? new Servers($this->buildServers(TypeHelper::asList($data['servers']))) : null,
            parameters: isset($data['parameters']) ? new Parameters($this->buildParameters(TypeHelper::asList($data['parameters']))) : null,
        );
    }

    /**
     * @return array<string, Operation>
     */
    protected function buildAdditionalOperations(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $operations = [];

        foreach ($data as $method => $operationData) {
            $operations[$method] = $this->buildOperation(TypeHelper::asArray($operationData));
        }

        return $operations;
    }

    /**
     * @return list<Parameter>
     */
    protected function buildParameters(array $data): array
    {
        return array_map($this->buildParameter(...), array_values($data));
    }

    protected function buildParameter(array $data): Parameter
    {
        if (isset($data['$ref'])) {
            return new Parameter(
                ref: TypeHelper::asString($data['$ref']),
                refSummary: TypeHelper::asStringOrNull($data['summary'] ?? null),
                refDescription: TypeHelper::asStringOrNull($data['description'] ?? null),
            );
        }

        if (false === isset($data['name']) || false === isset($data['in'])) {
            throw new InvalidSchemaException('Parameter must have name and in fields');
        }

        if ($this->shouldWarnDeprecation() && isset($data['allowEmptyValue']) && $data['allowEmptyValue']) {
            $this->deprecationLogger->warn(
                'allowEmptyValue',
                'Parameter Object',
                self::DEPRECATION_VERSION,
            );
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
            schema: isset($data['schema']) && (is_array($data['schema']) || is_bool($data['schema']))
                ? $this->buildSchema($data['schema'])
                : null,
            examples: isset($data['examples']) ? TypeHelper::asStringMixedMapOrNull($data['examples']) : null,
            example: isset($data['example']) && false === is_array($data['example'])
                ? (is_string($data['example']) ? $this->buildExample(['value' => $data['example']]) : null)
                : null,
            content: isset($data['content']) ? $this->buildContent(TypeHelper::asArray($data['content'])) : null,
        );
    }

    protected function buildSchema(mixed $data): Schema
    {
        if (is_bool($data)) {
            if ($data) {
                return new Schema();
            }

            return new Schema(not: new Schema());
        }

        if (false === is_array($data)) {
            throw new InvalidSchemaException(
                sprintf('Expected array or boolean for schema, got %s', gettype($data)),
            );
        }

        $this->checkSchemaDeprecations($data);

        return new Schema(
            ref: TypeHelper::asStringOrNull($data['$ref'] ?? null),
            refSummary: isset($data['$ref']) ? TypeHelper::asStringOrNull($data['summary'] ?? null) : null,
            refDescription: isset($data['$ref']) ? TypeHelper::asStringOrNull($data['description'] ?? null) : null,
            format: TypeHelper::asStringOrNull($data['format'] ?? null),
            title: TypeHelper::asStringOrNull($data['title'] ?? null),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            default: $data['default'] ?? null,
            hasDefault: array_key_exists('default', $data),
            deprecated: (bool) ($data['deprecated'] ?? false),
            readOnly: (bool) ($data['readOnly'] ?? false),
            writeOnly: (bool) ($data['writeOnly'] ?? false),
            type: $this->resolveType($data),
            nullable: (bool) ($data['nullable'] ?? false),
            const: $data['const'] ?? null,
            hasConst: array_key_exists('const', $data),
            multipleOf: TypeHelper::asFloatOrNull($data['multipleOf'] ?? null),
            maximum: TypeHelper::asFloatOrNull($data['maximum'] ?? null),
            exclusiveMaximum: $this->resolveExclusiveMaximum($data),
            minimum: TypeHelper::asFloatOrNull($data['minimum'] ?? null),
            exclusiveMinimum: $this->resolveExclusiveMinimum($data),
            maxLength: TypeHelper::asIntOrNull($data['maxLength'] ?? null),
            minLength: TypeHelper::asIntOrNull($data['minLength'] ?? null),
            pattern: TypeHelper::asStringOrNull($data['pattern'] ?? null),
            maxItems: TypeHelper::asIntOrNull($data['maxItems'] ?? null),
            minItems: TypeHelper::asIntOrNull($data['minItems'] ?? null),
            uniqueItems: TypeHelper::asBoolOrNull($data['uniqueItems'] ?? null),
            maxProperties: TypeHelper::asIntOrNull($data['maxProperties'] ?? null),
            minProperties: TypeHelper::asIntOrNull($data['minProperties'] ?? null),
            required: TypeHelper::asStringListOrNull($data['required'] ?? null),
            allOf: $this->buildSchemaList($data, 'allOf'),
            anyOf: $this->buildSchemaList($data, 'anyOf'),
            oneOf: $this->buildSchemaList($data, 'oneOf'),
            not: isset($data['not']) ? $this->buildSchema($data['not']) : null,
            discriminator: isset($data['discriminator']) ? $this->buildDiscriminator(TypeHelper::asArray($data['discriminator'])) : null,
            properties: isset($data['properties']) && is_array($data['properties'])
                ? $this->buildProperties(TypeHelper::asArray($data['properties']))
                : null,
            additionalProperties: $this->buildOptionalSchema($data, 'additionalProperties'),
            unevaluatedProperties: $this->buildOptionalSchema($data, 'unevaluatedProperties'),
            items: isset($data['items']) && (is_array($data['items']) || is_bool($data['items']))
                ? $this->buildSchema($data['items'])
                : null,
            prefixItems: $this->buildSchemaList($data, 'prefixItems'),
            contains: isset($data['contains']) ? $this->buildSchema($data['contains']) : null,
            minContains: TypeHelper::asIntOrNull($data['minContains'] ?? null),
            maxContains: TypeHelper::asIntOrNull($data['maxContains'] ?? null),
            patternProperties: isset($data['patternProperties']) && is_array($data['patternProperties'])
                ? $this->buildProperties(TypeHelper::asArray($data['patternProperties']))
                : null,
            propertyNames: isset($data['propertyNames']) ? $this->buildSchema($data['propertyNames']) : null,
            dependentSchemas: isset($data['dependentSchemas']) && is_array($data['dependentSchemas'])
                ? $this->buildProperties(TypeHelper::asArray($data['dependentSchemas']))
                : null,
            if: isset($data['if']) ? $this->buildSchema($data['if']) : null,
            then: isset($data['then']) ? $this->buildSchema($data['then']) : null,
            else: isset($data['else']) ? $this->buildSchema($data['else']) : null,
            unevaluatedItems: isset($data['unevaluatedItems']) ? $this->buildSchema($data['unevaluatedItems']) : null,
            example: $data['example'] ?? null,
            examples: isset($data['examples']) && is_array($data['examples']) ? TypeHelper::asStringMixedMapOrNull($data['examples']) : null,
            enum: TypeHelper::asEnumListOrNull($data['enum'] ?? null),
            contentEncoding: TypeHelper::asStringOrNull($data['contentEncoding'] ?? null),
            contentMediaType: TypeHelper::asStringOrNull($data['contentMediaType'] ?? null),
            contentSchema: $this->buildOptionalSchema($data, 'contentSchema'),
            jsonSchemaDialect: TypeHelper::asStringOrNull($data['$schema'] ?? null),
            xml: isset($data['xml']) && is_array($data['xml'])
                ? $this->buildXml(TypeHelper::asArray($data['xml']))
                : null,
        );
    }

    /**
     * @param array<array-key, mixed> $data
     */
    protected function checkSchemaDeprecations(array $data): void
    {
        if ($this->shouldWarnDeprecation() && isset($data['example'])) {
            $this->deprecationLogger->warn(
                'example',
                'Schema Object',
                self::DEPRECATION_VERSION,
                'examples in MediaType Object',
            );
        }

        if ($this->shouldWarnDeprecation() && isset($data['nullable']) && $data['nullable']) {
            $this->deprecationLogger->warn(
                'nullable',
                'Schema Object',
                self::DEPRECATION_VERSION,
                'type array with "null" (e.g., type: ["string", "null"])',
            );
        }

        if ($this->shouldWarnDeprecation() && isset($data['exclusiveMinimum']) && is_bool($data['exclusiveMinimum'])) {
            $this->deprecationLogger->warn(
                'exclusiveMinimum (bool)',
                'Schema Object',
                '3.1.0',
                'exclusiveMinimum as number',
            );
        }

        if ($this->shouldWarnDeprecation() && isset($data['exclusiveMaximum']) && is_bool($data['exclusiveMaximum'])) {
            $this->deprecationLogger->warn(
                'exclusiveMaximum (bool)',
                'Schema Object',
                '3.1.0',
                'exclusiveMaximum as number',
            );
        }
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return ?list<Schema>
     */
    protected function buildSchemaList(array $data, string $key): ?array
    {
        if (false === isset($data[$key])) {
            return null;
        }

        return array_values(array_map(
            fn(mixed $schemaData): Schema => $this->buildSchema($schemaData),
            TypeHelper::asArray($data[$key]),
        ));
    }

    /**
     * @param array<array-key, mixed> $data
     */
    protected function buildOptionalSchema(array $data, string $key): Schema|bool|null
    {
        if (false === isset($data[$key])) {
            return null;
        }

        if (is_array($data[$key])) {
            return $this->buildSchema(TypeHelper::asArray($data[$key]));
        }

        return (bool) $data[$key];
    }

    /**
     * @return array<string, Schema>
     */
    protected function buildProperties(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $properties = [];

        foreach ($data as $name => $schema) {
            $properties[$name] = $this->buildSchema($schema);
        }

        return $properties;
    }

    protected function buildDiscriminator(array $data): Discriminator
    {
        return new Discriminator(
            propertyName: TypeHelper::asStringOrNull($data['propertyName'] ?? null),
            mapping: TypeHelper::asStringMapOrNull($data['mapping'] ?? null),
            defaultMapping: TypeHelper::asStringOrNull($data['defaultMapping'] ?? null),
        );
    }

    protected function buildXml(array $data): Xml
    {
        if ($this->shouldWarnDeprecation() && isset($data['attribute'])) {
            $this->deprecationLogger->warn(
                'attribute',
                'XML Object',
                self::DEPRECATION_VERSION,
                'nodeType: "attribute"',
            );
        }

        if ($this->shouldWarnDeprecation() && isset($data['wrapped'])) {
            $this->deprecationLogger->warn(
                'wrapped',
                'XML Object',
                self::DEPRECATION_VERSION,
            );
        }

        $xml = new Xml(
            name: TypeHelper::asStringOrNull($data['name'] ?? null),
            namespace: TypeHelper::asStringOrNull($data['namespace'] ?? null),
            prefix: TypeHelper::asStringOrNull($data['prefix'] ?? null),
            attribute: TypeHelper::asBoolOrNull($data['attribute'] ?? null),
            wrapped: TypeHelper::asBoolOrNull($data['wrapped'] ?? null),
            nodeType: TypeHelper::asStringOrNull($data['nodeType'] ?? null),
        );

        $this->validateXml($xml);

        return $xml;
    }

    protected function validateXml(Xml $xml): void
    {
        if (null !== $xml->nodeType && !Xml::isValidNodeType($xml->nodeType)) {
            throw new InvalidSchemaException(
                sprintf(
                    'Invalid XML nodeType "%s". Must be one of: %s',
                    $xml->nodeType,
                    implode(', ', Xml::VALID_NODE_TYPES),
                ),
            );
        }
    }

    protected function buildOperation(array $data): Operation
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

    protected function buildRequestBody(array $data): RequestBody
    {
        return new RequestBody(
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            content: isset($data['content']) && is_array($data['content'])
                ? $this->buildContent(TypeHelper::asArray($data['content']))
                : null,
            required: (bool) ($data['required'] ?? false),
        );
    }

    protected function buildContent(array $data): Content
    {
        /** @var array<string, array<string, mixed>> $data */
        $mediaTypes = [];

        foreach ($data as $mediaType => $content) {
            $mediaTypes[$mediaType] = $this->buildMediaType(TypeHelper::asArray($content));
        }

        /** @var array<string, MediaType> $mediaTypes */
        return new Content($mediaTypes);
    }

    protected function buildMediaType(array $data): MediaType
    {
        if ($this->shouldWarnDeprecation() && isset($data['example'])) {
            $this->deprecationLogger->warn(
                'example',
                'MediaType Object',
                self::DEPRECATION_VERSION,
                'examples',
            );
        }

        return new MediaType(
            schema: isset($data['schema']) && (is_array($data['schema']) || is_bool($data['schema']))
                ? $this->buildSchema($data['schema'])
                : null,
            itemSchema: isset($data['itemSchema']) && (is_array($data['itemSchema']) || is_bool($data['itemSchema']))
                ? $this->buildSchema($data['itemSchema'])
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

    protected function buildEncoding(array $data): Encoding
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
    protected function buildEncodingMap(array $data): array
    {
        $encodings = [];

        foreach ($data as $name => $encoding) {
            assert(is_string($name));
            assert(is_array($encoding));
            $encodings[$name] = $this->buildEncoding(TypeHelper::asArray($encoding));
        }

        return $encodings;
    }

    /**
     * @return array<int, Encoding>
     */
    protected function buildPrefixEncoding(array $data): array
    {
        $encodings = [];

        foreach ($data as $encoding) {
            assert(is_array($encoding));
            $encodings[] = $this->buildEncoding(TypeHelper::asArray($encoding));
        }

        return $encodings;
    }

    protected function buildResponses(array $data): Responses
    {
        /** @var array<string, array<string, mixed>> $data */
        $responses = [];

        foreach ($data as $statusCode => $response) {
            $responses[$statusCode] = $this->buildResponse(TypeHelper::asArray($response));
        }

        /** @var array<string, Response> $responses */
        return new Responses($responses);
    }

    protected function buildResponse(array $data): Response
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

    protected function buildHeaders(array $data): Headers
    {
        /** @var array<string, array<string, mixed>> $data */
        $headers = [];

        foreach ($data as $headerName => $header) {
            $headers[$headerName] = $this->buildHeader(TypeHelper::asArray($header));
        }

        /** @var array<string, Header> $headers */
        return new Headers($headers);
    }

    protected function buildHeader(array $data): Header
    {
        if ($this->shouldWarnDeprecation() && isset($data['allowEmptyValue']) && $data['allowEmptyValue']) {
            $this->deprecationLogger->warn(
                'allowEmptyValue',
                'Header Object',
                self::DEPRECATION_VERSION,
            );
        }

        return new Header(
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            required: (bool) ($data['required'] ?? false),
            deprecated: (bool) ($data['deprecated'] ?? false),
            allowEmptyValue: (bool) ($data['allowEmptyValue'] ?? false),
            schema: isset($data['schema']) && (is_array($data['schema']) || is_bool($data['schema']))
                ? $this->buildSchema($data['schema'])
                : null,
            example: $data['example'] ?? null,
            examples: isset($data['examples']) && is_array($data['examples']) ? TypeHelper::asStringMixedMapOrNull($data['examples']) : null,
            content: isset($data['content']) && is_array($data['content'])
                ? $this->buildContent(TypeHelper::asArray($data['content']))
                : null,
        );
    }

    protected function buildLinks(array $data): Links
    {
        /** @var array<string, array<string, mixed>> $data */
        $links = [];

        foreach ($data as $linkName => $link) {
            $links[$linkName] = $this->buildLink(TypeHelper::asArray($link));
        }

        /** @var array<string, Link> $links */
        return new Links($links);
    }

    protected function buildLink(array $data): Link
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

    protected function buildExternalDocs(array $data): ExternalDocs
    {
        if (false === isset($data['url'])) {
            throw new InvalidSchemaException('External documentation must have url');
        }

        return new ExternalDocs(
            url: TypeHelper::asString($data['url']),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
        );
    }

    protected function buildWebhooks(array $data): Webhooks
    {
        /** @var array<string, array<string, mixed>> $data */
        $webhooks = [];

        foreach ($data as $webhookName => $webhook) {
            $webhooks[$webhookName] = $this->buildPathItem(TypeHelper::asArray($webhook));
        }

        /** @var array<string, PathItem> $webhooks */
        return new Webhooks($webhooks);
    }

    protected function buildCallbacks(array $data): Callbacks
    {
        /** @var array<string, array<string, array<string, mixed>>> $data */
        $callbacks = [];

        foreach ($data as $callbackName => $callback) {
            foreach ($callback as $expression => $pathItem) {
                $callbacks[$callbackName][$expression] = $this->buildPathItem(TypeHelper::asArray($pathItem));
            }
        }

        /** @var array<string, array<string, PathItem>> $callbacks */
        return new Callbacks($callbacks);
    }

    protected function buildComponents(array $data): Components
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

    /**
     * @return array<string, Schema>
     */
    protected function buildSchemas(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $schemas = [];

        foreach ($data as $name => $schema) {
            $schemas[$name] = $this->buildSchema($schema);
        }

        return $schemas;
    }

    /**
     * @return array<string, Response>
     */
    protected function buildResponsesComponents(array $data): array
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
    protected function buildParametersComponents(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $parameters = [];

        foreach ($data as $name => $parameter) {
            $parameters[$name] = $this->buildParameter(TypeHelper::asArray($parameter));
        }

        return $parameters;
    }

    /**
     * @return array<string, Example>
     */
    protected function buildExamplesComponents(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $examples = [];

        foreach ($data as $name => $example) {
            $examples[$name] = $this->buildExample(TypeHelper::asArray($example));
        }

        return $examples;
    }

    protected function buildExample(array $data): Example
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

    /**
     * @return array<string, RequestBody>
     */
    protected function buildRequestBodiesComponents(array $data): array
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
    protected function buildHeadersComponents(array $data): array
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
    protected function buildSecuritySchemesComponents(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $securitySchemes = [];

        foreach ($data as $name => $scheme) {
            $securitySchemes[$name] = $this->buildSecurityScheme(TypeHelper::asArray($scheme));
        }

        return $securitySchemes;
    }

    protected function buildSecurityScheme(array $data): SecurityScheme
    {
        if (false === isset($data['type'])) {
            throw new InvalidSchemaException('Security scheme must have type');
        }

        return new SecurityScheme(
            type: TypeHelper::asString($data['type']),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            name: TypeHelper::asStringOrNull($data['name'] ?? null),
            in: TypeHelper::asStringOrNull($data['in'] ?? null),
            scheme: TypeHelper::asStringOrNull($data['scheme'] ?? null),
            bearerFormat: TypeHelper::asStringOrNull($data['bearerFormat'] ?? null),
            flows: isset($data['flows']) && is_array($data['flows'])
                ? $this->buildOAuthFlows(TypeHelper::asArray($data['flows']))
                : null,
            openIdConnectUrl: TypeHelper::asStringOrNull($data['openIdConnectUrl'] ?? null),
            oauth2MetadataUrl: TypeHelper::asStringOrNull($data['oauth2MetadataUrl'] ?? null),
            authorizationUrl: TypeHelper::asStringOrNull($data['authorizationUrl'] ?? null),
            tokenUrl: TypeHelper::asStringOrNull($data['tokenUrl'] ?? null),
            refreshUrl: TypeHelper::asStringOrNull($data['refreshUrl'] ?? null),
            scopes: isset($data['scopes']) && is_array($data['scopes'])
                ? TypeHelper::asStringMap($data['scopes'])
                : null,
        );
    }

    protected function buildOAuthFlows(array $data): OAuthFlows
    {
        return new OAuthFlows(
            implicit: isset($data['implicit']) && is_array($data['implicit'])
                ? $this->buildOAuthFlow(TypeHelper::asArray($data['implicit']))
                : null,
            password: isset($data['password']) && is_array($data['password'])
                ? $this->buildOAuthFlow(TypeHelper::asArray($data['password']))
                : null,
            clientCredentials: isset($data['clientCredentials']) && is_array($data['clientCredentials'])
                ? $this->buildOAuthFlow(TypeHelper::asArray($data['clientCredentials']))
                : null,
            authorizationCode: isset($data['authorizationCode']) && is_array($data['authorizationCode'])
                ? $this->buildOAuthFlow(TypeHelper::asArray($data['authorizationCode']))
                : null,
            deviceCode: isset($data['deviceCode']) && is_array($data['deviceCode'])
                ? $this->buildOAuthFlow(TypeHelper::asArray($data['deviceCode']))
                : null,
        );
    }

    protected function buildOAuthFlow(array $data): OAuthFlow
    {
        return new OAuthFlow(
            authorizationUrl: TypeHelper::asStringOrNull($data['authorizationUrl'] ?? null),
            tokenUrl: TypeHelper::asStringOrNull($data['tokenUrl'] ?? null),
            refreshUrl: TypeHelper::asStringOrNull($data['refreshUrl'] ?? null),
            scopes: isset($data['scopes']) && is_array($data['scopes'])
                ? TypeHelper::asStringMap($data['scopes'])
                : null,
            deviceAuthorizationUrl: TypeHelper::asStringOrNull($data['deviceAuthorizationUrl'] ?? null),
            deprecated: TypeHelper::asBoolOrNull($data['deprecated'] ?? null),
        );
    }

    /**
     * @return array<string, Link>
     */
    protected function buildLinksComponents(array $data): array
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
    protected function buildCallbacksComponents(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $callbacks = [];

        foreach ($data as $name => $callback) {
            $callbacks[$name] = $this->buildCallbacks(TypeHelper::asArray($callback));
        }

        return $callbacks;
    }

    /**
     * @return array<string, PathItem>
     */
    protected function buildPathItemsComponents(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $pathItems = [];

        foreach ($data as $name => $pathItem) {
            $pathItems[$name] = $this->buildPathItem(TypeHelper::asArray($pathItem));
        }

        return $pathItems;
    }

    /**
     * @return array<string, MediaType>
     */
    protected function buildMediaTypesComponents(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $mediaTypes = [];

        foreach ($data as $name => $mediaType) {
            $mediaTypes[$name] = $this->buildMediaType(TypeHelper::asArray($mediaType));
        }

        return $mediaTypes;
    }

    protected function buildServer(array $data): Server
    {
        return new Server(
            url: TypeHelper::asString($data['url']),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            variables: isset($data['variables']) && is_array($data['variables'])
                ? TypeHelper::asStringMixedMapOrNull($data['variables'])
                : null,
            name: TypeHelper::asStringOrNull($data['name'] ?? null),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return string|list<string>|null
     */
    protected function resolveType(array $data): string|array|null
    {
        $type = TypeHelper::asTypeOrNull($data['type'] ?? null);

        if (false === $this->isVersion30() && true === ($data['nullable'] ?? false)) {
            $baseType = is_array($type) ? array_values($type) : (is_string($type) ? [$type] : []);

            if ([] === $baseType) {
                /** @var string|list<string>|null $type */
                return $type;
            }

            if (false === in_array('null', $baseType, true)) {
                $baseType[] = 'null';
            }

            /** @var list<string> $baseType */
            return $baseType;
        }

        /** @var string|list<string>|null $type */
        return $type;
    }

    protected function isVersion30(): bool
    {
        return version_compare($this->documentVersion, '3.1.0', '<');
    }

    /**
     * Resolve exclusiveMinimum with version-aware handling.
     *
     * OpenAPI 3.0: exclusiveMinimum is boolean (works with minimum).
     * OpenAPI 3.1+: exclusiveMinimum is number (standalone).
     */
    protected function resolveExclusiveMinimum(array $data): ?float
    {
        /** @var mixed $exclusiveMinimum */
        $exclusiveMinimum = $data['exclusiveMinimum'] ?? null;

        if ($this->isVersion30()) {
            if (is_bool($exclusiveMinimum) && $exclusiveMinimum) {
                return TypeHelper::asFloatOrNull($data['minimum'] ?? null);
            }

            return null;
        }

        if (is_bool($exclusiveMinimum)) {
            return null;
        }

        return TypeHelper::asFloatOrNull($exclusiveMinimum);
    }

    /**
     * Resolve exclusiveMaximum with version-aware handling.
     *
     * OpenAPI 3.0: exclusiveMaximum is boolean (works with maximum).
     * OpenAPI 3.1+: exclusiveMaximum is number (standalone).
     */
    protected function resolveExclusiveMaximum(array $data): ?float
    {
        /** @var mixed $exclusiveMaximum */
        $exclusiveMaximum = $data['exclusiveMaximum'] ?? null;

        if ($this->isVersion30()) {
            if (is_bool($exclusiveMaximum) && $exclusiveMaximum) {
                return TypeHelper::asFloatOrNull($data['maximum'] ?? null);
            }

            return null;
        }

        if (is_bool($exclusiveMaximum)) {
            return null;
        }

        return TypeHelper::asFloatOrNull($exclusiveMaximum);
    }
}
