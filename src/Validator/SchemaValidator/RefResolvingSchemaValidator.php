<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Override;

/**
 * Resolves `$ref` on the supplied schema before delegating to the inner
 * recursion validator. Used by {@see ValidatorDependencies::rootSchemaValidator()}
 * to ensure stateless nested-keyword validators (additionalProperties,
 * patternProperties, unevaluatedProperties, unevaluatedItems, prefixItems,
 * contains, propertyNames, dependentSchemas, not, if, then, else, items,
 * properties) — which all funnel through {@see AbstractSchemaValidator::createSchemaValidator()}
 * — never receive an opaque `{$ref: '#/...'}` stub. This closes the
 * R4-CORRECTNESS-001 / R4-SEC-001 silent bypass where stub schemas
 * produced no applicable validators and validation passed as no-op.
 *
 * The shared {@see RefResolverInterface} instance provides WeakMap-backed
 * caching and circular-reference detection so nested recursion terminates
 * through RefResolver's own cycle guard plus the surrounding
 * ValidationContext depth bound.
 *
 * @internal
 */
final readonly class RefResolvingSchemaValidator implements SchemaValidatorInterface
{
    public function __construct(
        private SchemaValidatorInterface $inner,
        private RefResolverInterface $refResolver,
        private OpenApiDocument $document,
    ) {}

    #[Override]
    public function validate(array|int|string|float|bool|null $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null !== $schema->ref) {
            $schema = $this->refResolver->resolveSchemaWithOverride($schema, $this->document);
        }

        $this->inner->validate($data, $schema, $context);
    }
}
