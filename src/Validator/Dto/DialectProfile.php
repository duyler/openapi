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

    private const string OPEN_API_32_VERSION = '3.2.0';

    public function __construct(
        public bool $allowTypeArray,
        public bool $refSiblingsApply,
        public bool $nullableRequiresType,
        public bool $readOnlyRequiredRelaxation = false,
        public bool $discriminatorIsHint = false,
    ) {}

    public static function forOpenApi31(): self
    {
        return new self(allowTypeArray: true, refSiblingsApply: true, nullableRequiresType: false);
    }

    public static function forOpenApi30(): self
    {
        return new self(
            allowTypeArray: false,
            refSiblingsApply: false,
            nullableRequiresType: true,
            readOnlyRequiredRelaxation: true,
        );
    }

    public static function forOpenApi32(): self
    {
        return new self(
            allowTypeArray: true,
            refSiblingsApply: true,
            nullableRequiresType: false,
            discriminatorIsHint: true,
        );
    }

    public static function fromOpenApiVersion(?string $openapi): self
    {
        return match (true) {
            null !== $openapi && version_compare($openapi, self::OPEN_API_32_VERSION, '>=') => self::forOpenApi32(),
            null !== $openapi && version_compare($openapi, self::OPEN_API_31_VERSION, '<') => self::forOpenApi30(),
            default => self::forOpenApi31(),
        };
    }

    public function withReadOnlyRequiredRelaxation(bool $enabled): self
    {
        return new self(
            allowTypeArray: $this->allowTypeArray,
            refSiblingsApply: $this->refSiblingsApply,
            nullableRequiresType: $this->nullableRequiresType,
            readOnlyRequiredRelaxation: $enabled,
            discriminatorIsHint: $this->discriminatorIsHint,
        );
    }

    public function withDiscriminatorIsHint(bool $enabled): self
    {
        return new self(
            allowTypeArray: $this->allowTypeArray,
            refSiblingsApply: $this->refSiblingsApply,
            nullableRequiresType: $this->nullableRequiresType,
            readOnlyRequiredRelaxation: $this->readOnlyRequiredRelaxation,
            discriminatorIsHint: $enabled,
        );
    }
}
