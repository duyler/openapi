<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Internal;

use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Parameters;
use Duyler\OpenApi\Schema\Model\PathItem;

/** @internal */
final readonly class PathItemParameterMerger
{
    public static function merge(PathItem $pathItem, Operation $operation): Operation
    {
        $pathLevel = $pathItem->parameters?->parameters ?? [];

        if ([] === $pathLevel) {
            return $operation;
        }

        $operationLevel = $operation->parameters?->parameters ?? [];

        $overridden = [];
        foreach ($operationLevel as $param) {
            $key = self::identity($param);
            if (null !== $key) {
                $overridden[$key] = true;
            }
        }

        $merged = $operationLevel;
        foreach ($pathLevel as $param) {
            $key = self::identity($param);
            if (null !== $key && isset($overridden[$key])) {
                continue;
            }

            $merged[] = $param;
        }

        if ($merged === $operationLevel) {
            return $operation;
        }

        return new Operation(
            tags: $operation->tags,
            summary: $operation->summary,
            description: $operation->description,
            externalDocs: $operation->externalDocs,
            operationId: $operation->operationId,
            parameters: new Parameters($merged),
            requestBody: $operation->requestBody,
            responses: $operation->responses,
            callbacks: $operation->callbacks,
            deprecated: $operation->deprecated,
            security: $operation->security,
            servers: $operation->servers,
        );
    }

    private static function identity(Parameter|string $parameter): ?string
    {
        if (false === $parameter instanceof Parameter) {
            return null;
        }

        if (null === $parameter->name || null === $parameter->in) {
            return null;
        }

        return $parameter->in . "\0" . $parameter->name;
    }
}
