<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Link;

use Duyler\OpenApi\Schema\Model\Link;
use Duyler\OpenApi\Validator\Link\Internal\ExpressionData;
use Duyler\OpenApi\Validator\Link\Internal\RuntimeExpressionEvaluator;

use function is_string;

final readonly class LinkResolver
{
    public function __construct(
        private readonly RuntimeExpressionEvaluator $runtimeExpressionEvaluator = new RuntimeExpressionEvaluator(),
    ) {}

    public function resolve(Link $link, LinkContext $context): ResolvedLink
    {
        $parameters = $this->resolveParameters($link->parameters, $context);

        return new ResolvedLink(
            parameters: $parameters,
            requestBody: $this->resolveValue($link->requestBody, $context),
            server: $link->server,
        );
    }

    /**
     * @param array<string, mixed>|null $parameters
     * @return array<string, mixed>
     */
    private function resolveParameters(?array $parameters, LinkContext $context): array
    {
        if (null === $parameters) {
            return [];
        }

        /** @var array<string, mixed> $resolved */
        $resolved = [];

        foreach ($parameters as $name => $expression) {
            $resolved[$name] = $this->resolveValue($expression, $context);
        }

        return $resolved;
    }

    private function resolveValue(mixed $expression, LinkContext $context): mixed
    {
        if (false === is_string($expression)) {
            return $expression;
        }

        return $this->runtimeExpressionEvaluator->resolve(
            $expression,
            new ExpressionData(
                url: $context->url,
                method: $context->method,
                statusCode: $context->statusCode,
                requestBody: $context->requestBody,
                requestHeaders: $context->requestHeaders,
                queryParams: $context->queryParams,
                pathParams: $context->pathParams,
                body: $context->body,
                headers: $context->headers,
            ),
        );
    }
}
