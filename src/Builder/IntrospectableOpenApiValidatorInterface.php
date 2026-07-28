<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Builder;

use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\ValidatorPool;

/**
 * Extended validator interface exposing read-only introspection accessors
 * for diagnostic surfaces, middleware, and test fixtures.
 *
 * Extends {@see OpenApiValidatorInterface} with six accessors that return
 * the resolved builder configuration. Callers that only need the standard
 * validation surface can continue to type-hint {@see OpenApiValidatorInterface};
 * callers that need introspection should type-hint this interface.
 */
interface IntrospectableOpenApiValidatorInterface extends OpenApiValidatorInterface
{
    public function getPool(): ValidatorPool;

    public function isCoercion(): bool;

    public function isNullableAsType(): bool;

    public function getEmptyArrayStrategy(): EmptyArrayStrategy;

    public function getErrorFormatter(): ErrorFormatterInterface;

    public function getCache(): ?SchemaCache;
}
