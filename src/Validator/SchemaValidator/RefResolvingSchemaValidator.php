<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Override;

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
        if (null !== $schema->ref) {
            $schema = $this->refResolver->resolveSchemaWithOverride($schema, $this->document);
        }

        $this->inner->validate($data, $schema, $context);
    }
}
