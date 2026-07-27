<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Link;
use Duyler\OpenApi\Schema\Model\Links;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Responses;
use Duyler\OpenApi\Schema\Parser\OpenApiBuildContext;
use Duyler\OpenApi\Schema\Parser\TypeHelper;

use Closure;

use function is_array;

/** @internal */
final readonly class ResponseTreeBuilder
{
    public function __construct(
        private OpenApiBuildContext $context,
        private ComponentTreeBuilder $treeBuilder,
    ) {}

    /** @param array<string, array<string, mixed>> $data */
    public function buildResponses(array $data): Responses
    {
        $responses = [];

        foreach ($data as $statusCode => $response) {
            $responses[$statusCode] = $this->buildResponse(TypeHelper::asArray($response));
        }

        return new Responses($responses);
    }

    /** @param array<string, mixed> $data */
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
            headers: $this->treeBuilder->buildHeadersOrNull($data),
            content: $this->treeBuilder->buildContentOrNull($data),
            links: $this->nullable($data, 'links', $this->buildLinks(...)),
        );
    }

    /** @param array<string, array<string, mixed>> $data */
    public function buildLinks(array $data): Links
    {
        $links = [];

        foreach ($data as $linkName => $link) {
            $links[$linkName] = $this->buildLink(TypeHelper::asArray($link));
        }

        return new Links($links);
    }

    /** @param array<string, mixed> $data */
    public function buildLink(array $data): Link
    {
        return new Link(
            operationRef: TypeHelper::asStringOrNull($data['operationRef'] ?? null),
            ref: TypeHelper::asStringOrNull($data['$ref'] ?? null),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            operationId: TypeHelper::asStringOrNull($data['operationId'] ?? null),
            parameters: isset($data['parameters']) && is_array($data['parameters']) ? TypeHelper::asStringMixedMapOrNull($data['parameters']) : null,
            requestBody: $this->treeBuilder->buildRequestBodyOrNull($data),
            server: isset($data['server']) && is_array($data['server'])
                ? $this->context->pathItemBuilder->buildServer(TypeHelper::asArray($data['server']))
                : null,
        );
    }

    /** @param array<string, array<string, array<string, mixed>>> $data */
    public function buildCallbacksMap(array $data): Callbacks
    {
        $callbacks = [];

        foreach ($data as $callbackName => $callback) {
            foreach ($callback as $expression => $pathItem) {
                $callbacks[$callbackName][$expression] = $this->context->pathItemBuilder->buildPathItem(TypeHelper::asArray($pathItem));
            }
        }

        return new Callbacks($callbacks);
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
}
