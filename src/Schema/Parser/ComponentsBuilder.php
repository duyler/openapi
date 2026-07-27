<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Callbacks;
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
use Duyler\OpenApi\Schema\Parser\Internal\ComponentTreeBuilder;
use Duyler\OpenApi\Schema\Parser\Internal\ResponseTreeBuilder;

use Closure;

use function is_array;

final readonly class ComponentsBuilder
{
    private ComponentTreeBuilder $treeBuilder;
    private ResponseTreeBuilder $responseTreeBuilder;

    public function __construct(private OpenApiBuildContext $context)
    {
        $this->treeBuilder = new ComponentTreeBuilder($context);
        $this->responseTreeBuilder = new ResponseTreeBuilder($context, $this->treeBuilder);
    }

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
        return $this->treeBuilder->buildRequestBody($data);
    }

    public function buildContent(array $data): Content
    {
        return $this->treeBuilder->buildContent($data);
    }

    public function buildMediaType(array $data): MediaType
    {
        return $this->treeBuilder->buildMediaType($data);
    }

    public function buildEncoding(array $data): Encoding
    {
        return $this->treeBuilder->buildEncoding($data);
    }

    public function buildEncodingMap(array $data): array
    {
        return $this->treeBuilder->buildEncodingMap($data);
    }

    public function buildPrefixEncoding(array $data): array
    {
        return $this->treeBuilder->buildPrefixEncoding($data);
    }

    public function buildResponses(array $data): Responses
    {
        return $this->responseTreeBuilder->buildResponses($data);
    }

    public function buildResponse(array $data): Response
    {
        return $this->responseTreeBuilder->buildResponse($data);
    }

    public function buildHeaders(array $data): Headers
    {
        return $this->treeBuilder->buildHeaders($data);
    }

    public function buildHeader(array $data): Header
    {
        return $this->treeBuilder->buildHeader($data);
    }

    public function buildLinks(array $data): Links
    {
        return $this->responseTreeBuilder->buildLinks($data);
    }

    public function buildLink(array $data): Link
    {
        return $this->responseTreeBuilder->buildLink($data);
    }

    public function buildExample(array $data): Example
    {
        return $this->treeBuilder->buildExample($data);
    }

    public function buildCallbacksMap(array $data): Callbacks
    {
        return $this->responseTreeBuilder->buildCallbacksMap($data);
    }

    /** @return array<string, Schema> */
    public function buildSchemas(array $data): array
    {
        $schemaBuilder = $this->context->schemaBuilder;

        return $this->mapByName($data, static fn(mixed $schema): Schema => $schemaBuilder->buildSchema($schema));
    }

    /** @return array<string, Response> */
    public function buildResponsesComponents(array $data): array
    {
        return $this->mapByName($data, fn(array $response): Response => $this->responseTreeBuilder->buildResponse($response));
    }

    /** @return array<string, Parameter> */
    public function buildParametersComponents(array $data): array
    {
        $pathItemBuilder = $this->context->pathItemBuilder;

        return $this->mapByName($data, static fn(array $parameter): Parameter => $pathItemBuilder->buildParameter($parameter));
    }

    /** @return array<string, Example> */
    public function buildExamplesComponents(array $data): array
    {
        return $this->mapByName($data, fn(array $example): Example => $this->treeBuilder->buildExample($example));
    }

    /** @return array<string, RequestBody> */
    public function buildRequestBodiesComponents(array $data): array
    {
        return $this->mapByName($data, fn(array $body): RequestBody => $this->treeBuilder->buildRequestBody($body));
    }

    /** @return array<string, Header> */
    public function buildHeadersComponents(array $data): array
    {
        return $this->mapByName($data, fn(array $header): Header => $this->treeBuilder->buildHeader($header));
    }

    /** @return array<string, SecurityScheme> */
    public function buildSecuritySchemesComponents(array $data): array
    {
        $securitySchemeBuilder = $this->context->securitySchemeBuilder;

        return $this->mapByName($data, static fn(array $scheme): SecurityScheme => $securitySchemeBuilder->buildSecurityScheme($scheme));
    }

    /** @return array<string, Link> */
    public function buildLinksComponents(array $data): array
    {
        return $this->mapByName($data, fn(array $link): Link => $this->responseTreeBuilder->buildLink($link));
    }

    /** @return array<string, Callbacks> */
    public function buildCallbacksComponents(array $data): array
    {
        return $this->mapByName($data, fn(array $callback): Callbacks => $this->responseTreeBuilder->buildCallbacksMap($callback));
    }

    /** @return array<string, PathItem> */
    public function buildPathItemsComponents(array $data): array
    {
        $pathItemBuilder = $this->context->pathItemBuilder;

        return $this->mapByName($data, static fn(array $pathItem): PathItem => $pathItemBuilder->buildPathItem($pathItem));
    }

    /** @return array<string, MediaType> */
    public function buildMediaTypesComponents(array $data): array
    {
        return $this->mapByName($data, fn(array $mediaType): MediaType => $this->treeBuilder->buildMediaType($mediaType));
    }

    /**
     * @template T
     *
     * @param Closure(mixed): T $builder
     *
     * @return array<string, T>
     */
    private function mapByName(array $data, Closure $builder): array
    {
        $result = [];

        foreach ($data as $name => $item) {
            $result[$name] = $builder($item);
        }

        return $result;
    }
}
