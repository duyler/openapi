<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Parameters;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\Paths;
use Duyler\OpenApi\Schema\Model\SecurityRequirement;
use Duyler\OpenApi\Schema\Model\Server;
use Duyler\OpenApi\Schema\Model\Servers;
use Duyler\OpenApi\Schema\Model\Webhooks;

use function array_map;
use function array_values;
use function is_array;
use function is_bool;
use function is_string;

/**
 * Builds OpenAPI PathItem / Operation / Parameter / Server / Paths / Webhooks
 * and the operation-level callbacks map.
 *
 * Depends on InfoBuilder (ExternalDocs on Operation), SchemaBuilder (schema
 * on Parameter) and ComponentsBuilder (RequestBody / Responses / Callbacks
 * inside Operation). These siblings are resolved lazily through
 * {@see OpenApiBuildContext} so that circular construction with
 * ComponentsBuilder is avoided.
 */
final readonly class PathItemBuilder
{
    private const string DEPRECATION_VERSION = '3.2.0';

    public function __construct(private OpenApiBuildContext $context) {}

    public function buildPaths(array $data): Paths
    {
        /** @var array<string, array<string, mixed>> $data */
        $paths = [];

        foreach ($data as $path => $pathItem) {
            $paths[$path] = $this->buildPathItem(TypeHelper::asArray($pathItem));
        }

        /** @var array<string, PathItem> $paths */
        return new Paths($paths);
    }

    public function buildPathItem(array $data): PathItem
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
                ? $this->buildAdditionalOperations($data['additionalOperations'])
                : null,
            servers: isset($data['servers']) ? new Servers($this->buildServers(TypeHelper::asList($data['servers']))) : null,
            parameters: isset($data['parameters']) ? new Parameters($this->buildParameters(TypeHelper::asList($data['parameters']))) : null,
        );
    }

    /**
     * @return array<string, Operation>
     */
    public function buildAdditionalOperations(array $data): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $operations = [];

        foreach ($data as $method => $operationData) {
            $operations[$method] = $this->buildOperation(TypeHelper::asArray($operationData));
        }

        return $operations;
    }

    public function buildOperation(array $data): Operation
    {
        $componentsBuilder = $this->context->componentsBuilder;
        $infoBuilder = $this->context->infoBuilder;

        return new Operation(
            tags: TypeHelper::asStringListOrNull($data['tags'] ?? null),
            summary: TypeHelper::asStringOrNull($data['summary'] ?? null),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            externalDocs: isset($data['externalDocs']) ? $infoBuilder->buildExternalDocs(TypeHelper::asArray($data['externalDocs'])) : null,
            operationId: TypeHelper::asStringOrNull($data['operationId'] ?? null),
            parameters: isset($data['parameters']) ? new Parameters($this->buildParameters(TypeHelper::asList($data['parameters']))) : null,
            requestBody: isset($data['requestBody']) ? $componentsBuilder->buildRequestBody(TypeHelper::asArray($data['requestBody'])) : null,
            responses: isset($data['responses']) ? $componentsBuilder->buildResponses(TypeHelper::asArray($data['responses'])) : null,
            callbacks: isset($data['callbacks']) ? $this->buildCallbacks(TypeHelper::asArray($data['callbacks'])) : null,
            deprecated: (bool) ($data['deprecated'] ?? false),
            security: isset($data['security']) ? new SecurityRequirement(TypeHelper::asSecurityListMapOrNull($data['security']) ?? []) : null,
            servers: isset($data['servers']) ? new Servers($this->buildServers(TypeHelper::asList($data['servers']))) : null,
        );
    }

    /**
     * @return list<Parameter>
     */
    public function buildParameters(array $data): array
    {
        return array_map($this->buildParameter(...), array_values($data));
    }

    public function buildParameter(array $data): Parameter
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

        if ($this->context->shouldWarnDeprecation() && isset($data['allowEmptyValue']) && $data['allowEmptyValue']) {
            $this->context->deprecationLogger->warn(
                'allowEmptyValue',
                'Parameter Object',
                self::DEPRECATION_VERSION,
            );
        }

        $componentsBuilder = $this->context->componentsBuilder;
        $schemaBuilder = $this->context->schemaBuilder;

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
                ? $schemaBuilder->buildSchema($data['schema'])
                : null,
            examples: isset($data['examples']) ? TypeHelper::asStringMixedMapOrNull($data['examples']) : null,
            example: isset($data['example']) && false === is_array($data['example'])
                ? (is_string($data['example']) ? $componentsBuilder->buildExample(['value' => $data['example']]) : null)
                : null,
            content: isset($data['content']) ? $componentsBuilder->buildContent(TypeHelper::asArray($data['content'])) : null,
        );
    }

    /**
     * @return list<Server>
     */
    public function buildServers(array $data): array
    {
        return array_map($this->buildServer(...), array_values($data));
    }

    public function buildServer(array $data): Server
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

    public function buildWebhooks(array $data): Webhooks
    {
        /** @var array<string, array<string, mixed>> $data */
        $webhooks = [];

        foreach ($data as $webhookName => $webhook) {
            $webhooks[$webhookName] = $this->buildPathItem(TypeHelper::asArray($webhook));
        }

        /** @var array<string, PathItem> $webhooks */
        return new Webhooks($webhooks);
    }

    public function buildCallbacks(array $data): Callbacks
    {
        return $this->context->componentsBuilder->buildCallbacksMap($data);
    }
}
