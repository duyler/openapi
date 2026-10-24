<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Header;
use Duyler\OpenApi\Schema\Model\Link;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\Internal\JsonPointer;

use function array_filter;
use function array_map;
use function sprintf;

/** @internal */
final readonly class DocumentSchemaCollector
{
    /** @return list<Schema> */
    public function collect(OpenApiDocument $document): array
    {
        return array_map(
            static fn(SchemaLocation $location): Schema => $location->schema,
            $this->collectWithLocations($document),
        );
    }

    /** @return list<SchemaLocation> */
    public function collectWithLocations(OpenApiDocument $document): array
    {
        $locations = [];

        foreach ($document->paths?->paths ?? [] as $path => $pathItem) {
            $locations = $this->withPathItem($pathItem, '#/paths/' . JsonPointer::escapeToken($path), $locations);
        }

        foreach ($document->webhooks?->webhooks ?? [] as $name => $pathItem) {
            $locations = $this->withPathItem($pathItem, '#/webhooks/' . JsonPointer::escapeToken($name), $locations);
        }

        $components = $document->components;

        if (null === $components) {
            return $locations;
        }

        foreach ($components->schemas ?? [] as $name => $schema) {
            $locations[] = new SchemaLocation($schema, '#/components/schemas/' . JsonPointer::escapeToken($name));
        }

        foreach ($components->responses ?? [] as $name => $response) {
            $locations = $this->withResponse($response, '#/components/responses/' . JsonPointer::escapeToken($name), $locations);
        }

        foreach ($components->parameters ?? [] as $name => $parameter) {
            $locations = $this->withParameter($parameter, '#/components/parameters/' . JsonPointer::escapeToken($name), $locations);
        }

        foreach ($components->requestBodies ?? [] as $name => $requestBody) {
            $locations = $this->withContent(
                $requestBody->content,
                '#/components/requestBodies/' . JsonPointer::escapeToken($name),
                $locations,
            );
        }

        foreach ($components->headers ?? [] as $name => $header) {
            $locations = $this->withHeader($header, '#/components/headers/' . JsonPointer::escapeToken($name), $locations);
        }

        foreach ($components->links ?? [] as $name => $link) {
            $locations = $this->withLink($link, '#/components/links/' . JsonPointer::escapeToken($name), $locations);
        }

        foreach ($components->callbacks ?? [] as $name => $callbacks) {
            $locations = $this->withCallbacks($callbacks, '#/components/callbacks/' . JsonPointer::escapeToken($name), $locations);
        }

        foreach ($components->pathItems ?? [] as $name => $pathItem) {
            $locations = $this->withPathItem($pathItem, '#/components/pathItems/' . JsonPointer::escapeToken($name), $locations);
        }

        foreach ($components->mediaTypes ?? [] as $name => $mediaType) {
            $locations = $this->withMediaType($mediaType, '#/components/mediaTypes/' . JsonPointer::escapeToken($name), $locations);
        }

        return $locations;
    }

    /**
     * @param list<SchemaLocation> $locations
     * @return list<SchemaLocation>
     */
    private function withPathItem(PathItem $pathItem, string $pointer, array $locations): array
    {
        foreach ($this->operations($pathItem) as $method => $operation) {
            $locations = $this->withOperation($operation, $pointer . '/' . JsonPointer::escapeToken($method), $locations);
        }

        foreach ($pathItem->parameters?->parameters ?? [] as $index => $parameter) {
            if ($parameter instanceof Parameter) {
                $locations = $this->withParameter(
                    $parameter,
                    sprintf('%s/parameters/%d', $pointer, $index),
                    $locations,
                );
            }
        }

        return $locations;
    }

    /** @return array<string, Operation> */
    private function operations(PathItem $pathItem): array
    {
        /** @var array<string, Operation|null> $candidates */
        $candidates = [
            'get' => $pathItem->get,
            'put' => $pathItem->put,
            'post' => $pathItem->post,
            'delete' => $pathItem->delete,
            'options' => $pathItem->options,
            'head' => $pathItem->head,
            'patch' => $pathItem->patch,
            'trace' => $pathItem->trace,
            'query' => $pathItem->query,
            ...($pathItem->additionalOperations ?? []),
        ];

        return array_filter(
            $candidates,
            static fn(?Operation $operation): bool => null !== $operation,
        );
    }

    /**
     * @param list<SchemaLocation> $locations
     * @return list<SchemaLocation>
     */
    private function withOperation(Operation $operation, string $pointer, array $locations): array
    {
        foreach ($operation->parameters?->parameters ?? [] as $index => $parameter) {
            if ($parameter instanceof Parameter) {
                $locations = $this->withParameter(
                    $parameter,
                    sprintf('%s/parameters/%d', $pointer, $index),
                    $locations,
                );
            }
        }

        if (null !== $operation->requestBody) {
            $locations = $this->withContent($operation->requestBody->content, $pointer . '/requestBody', $locations);
        }

        foreach ($operation->responses?->responses ?? [] as $status => $response) {
            $locations = $this->withResponse($response, $pointer . '/responses/' . JsonPointer::escapeToken($status), $locations);
        }

        if (null !== $operation->callbacks) {
            $locations = $this->withCallbacks($operation->callbacks, $pointer . '/callbacks', $locations);
        }

        return $locations;
    }

    /**
     * @param list<SchemaLocation> $locations
     * @return list<SchemaLocation>
     */
    private function withCallbacks(Callbacks $callbacks, string $pointer, array $locations): array
    {
        foreach ($callbacks->callbacks as $name => $expressions) {
            $callbackPointer = $pointer . '/' . JsonPointer::escapeToken($name);

            foreach ($expressions as $expression => $pathItem) {
                $locations = $this->withPathItem(
                    $pathItem,
                    $callbackPointer . '/' . JsonPointer::escapeToken($expression),
                    $locations,
                );
            }
        }

        return $locations;
    }

    /**
     * @param list<SchemaLocation> $locations
     * @return list<SchemaLocation>
     */
    private function withResponse(Response $response, string $pointer, array $locations): array
    {
        foreach ($response->headers?->headers ?? [] as $name => $header) {
            $locations = $this->withHeader($header, $pointer . '/headers/' . JsonPointer::escapeToken($name), $locations);
        }

        $locations = $this->withContent($response->content, $pointer, $locations);

        foreach ($response->links?->links ?? [] as $name => $link) {
            $locations = $this->withLink($link, $pointer . '/links/' . JsonPointer::escapeToken($name), $locations);
        }

        return $locations;
    }

    /**
     * @param list<SchemaLocation> $locations
     * @return list<SchemaLocation>
     */
    private function withParameter(Parameter $parameter, string $pointer, array $locations): array
    {
        if (null !== $parameter->schema) {
            $locations[] = new SchemaLocation($parameter->schema, $pointer . '/schema');
        }

        return $this->withContent($parameter->content, $pointer, $locations);
    }

    /**
     * @param list<SchemaLocation> $locations
     * @return list<SchemaLocation>
     */
    private function withHeader(Header $header, string $pointer, array $locations): array
    {
        if (null !== $header->schema) {
            $locations[] = new SchemaLocation($header->schema, $pointer . '/schema');
        }

        return $this->withContent($header->content, $pointer, $locations);
    }

    /**
     * @param list<SchemaLocation> $locations
     * @return list<SchemaLocation>
     */
    private function withLink(Link $link, string $pointer, array $locations): array
    {
        if (null === $link->requestBody) {
            return $locations;
        }

        return $this->withContent($link->requestBody->content, $pointer . '/requestBody', $locations);
    }

    /**
     * @param list<SchemaLocation> $locations
     * @return list<SchemaLocation>
     */
    private function withContent(?Content $content, string $pointer, array $locations): array
    {
        foreach ($content?->mediaTypes ?? [] as $type => $mediaType) {
            $locations = $this->withMediaType($mediaType, $pointer . '/content/' . JsonPointer::escapeToken($type), $locations);
        }

        return $locations;
    }

    /**
     * @param list<SchemaLocation> $locations
     * @return list<SchemaLocation>
     */
    private function withMediaType(MediaType $mediaType, string $pointer, array $locations): array
    {
        if (null !== $mediaType->schema) {
            $locations[] = new SchemaLocation($mediaType->schema, $pointer . '/schema');
        }

        if (null !== $mediaType->itemSchema) {
            $locations[] = new SchemaLocation($mediaType->itemSchema, $pointer . '/itemSchema');
        }

        return $locations;
    }
}
