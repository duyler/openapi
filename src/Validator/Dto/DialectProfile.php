<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Dto;

use function version_compare;

/**
 * Immutable OpenAPI dialect semantics profile computed once per document.
 *
 * Carries the schema-level differences between OpenAPI 3.0 and 3.1+ so that
 * validators never branch on version strings at runtime.
 */
final readonly class DialectProfile
{
    private const string OPEN_API_31_VERSION = '3.1.0';

    public function __construct(
        public bool $allowTypeArray,
        public bool $refSiblingsApply,
        public bool $nullableRequiresType,
    ) {}

    public static function forOpenApi31(): self
    {
        return new self(allowTypeArray: true, refSiblingsApply: true, nullableRequiresType: false);
    }

    public static function forOpenApi30(): self
    {
        return new self(allowTypeArray: false, refSiblingsApply: false, nullableRequiresType: true);
    }

    public static function fromOpenApiVersion(?string $openapi): self
    {
        return null !== $openapi && version_compare($openapi, self::OPEN_API_31_VERSION, '<')
            ? self::forOpenApi30()
            : self::forOpenApi31();
    }
}
