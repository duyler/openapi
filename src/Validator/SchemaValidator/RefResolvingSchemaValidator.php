<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Override;

use function count;

/** @internal */
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
        $entersScope = null !== $schema->id && null !== $context;
        $enclosingBaseUri = null === $context
            ? null
            : $this->refResolver->scopeBaseUri($context->dynamicScope(), $this->document);

        if ($entersScope) {
            $context->enterDynamicScope($schema);
        }

        try {
            [$schema, $chainResources] = null !== $schema->ref
                ? $this->refResolver->resolveSchemaWithOverrideAndChain($schema, $this->document, $enclosingBaseUri)
                : [$schema, []];

            $entersChainScopes = [];

            if (null !== $context) {
                foreach ($chainResources as $chainResource) {
                    $context->enterDynamicScope($chainResource);
                    $entersChainScopes[] = $chainResource;
                }
            }

            try {
                $this->inner->validate($data, $schema, $context);
            } finally {
                $chainScopeCount = count($entersChainScopes);

                for ($chainIndex = 0; $chainIndex < $chainScopeCount; ++$chainIndex) {
                    $context?->leaveDynamicScope();
                }
            }
        } finally {
            if ($entersScope) {
                $context?->leaveDynamicScope();
            }
        }
    }
}
