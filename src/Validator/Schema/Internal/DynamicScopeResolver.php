<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Throwable;

use function count;

/** @internal */
final readonly class DynamicScopeResolver
{
    public function __construct(
        private readonly RefResolverInterface $refResolver,
        private readonly OpenApiDocument $document,
    ) {}

    public function enter(Schema $schema, ?ValidationContext $context): DynamicScopeFrame
    {
        $entersWrapperScope = null !== $schema->id && null !== $context;
        $enclosingBaseUri = null === $context
            ? null
            : $this->refResolver->scopeBaseUri($context->dynamicScope(), $this->document);

        if ($entersWrapperScope) {
            $context->enterDynamicScope($schema);
        }

        $enteredChainResources = [];

        try {
            [$schema, $companion, $chainResources] = null !== $schema->ref
                ? $this->refResolver->resolveSchemaWithCompanionAndChain($schema, $this->document, $enclosingBaseUri)
                : [$schema, null, []];

            if (null !== $context) {
                foreach ($chainResources as $chainResource) {
                    $context->enterDynamicScope($chainResource);
                    $enteredChainResources[] = $chainResource;
                }
            }
        } catch (Throwable $throwable) {
            $this->leave(new DynamicScopeFrame($schema, $enteredChainResources, $entersWrapperScope), $context);

            throw $throwable;
        }

        return new DynamicScopeFrame($schema, $enteredChainResources, $entersWrapperScope, $companion);
    }

    public function leave(DynamicScopeFrame $frame, ?ValidationContext $context): void
    {
        $chainScopeCount = count($frame->chainResources);

        for ($chainIndex = 0; $chainIndex < $chainScopeCount; ++$chainIndex) {
            $context?->leaveDynamicScope();
        }

        if ($frame->entersWrapperScope) {
            $context?->leaveDynamicScope();
        }
    }
}
