<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Schema\Internal\DynamicScopeResolver;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Override;

/** @internal */
final readonly class RefResolvingSchemaValidator implements SchemaValidatorInterface
{
    private readonly DynamicScopeResolver $scopeResolver;

    public function __construct(
        private SchemaValidatorInterface $inner,
        private RefResolverInterface $refResolver,
        private OpenApiDocument $document,
    ) {
        $this->scopeResolver = new DynamicScopeResolver($this->refResolver, $this->document);
    }

    #[Override]
    public function validate(array|int|string|float|bool|null $data, Schema $schema, ?ValidationContext $context = null): void
    {
        $frame = $this->scopeResolver->enter($schema, $context);

        try {
            $this->inner->validate($data, $frame->schema, $context);
            $this->validateCompanion($frame->companion, $data, $context);
        } finally {
            $this->scopeResolver->leave($frame, $context);
        }
    }

    private function validateCompanion(?Schema $companion, array|int|string|float|bool|null $data, ?ValidationContext $context): void
    {
        if (null === $companion || null === $context) {
            return;
        }

        $this->inner->validate($data, $companion, $context);
    }
}
