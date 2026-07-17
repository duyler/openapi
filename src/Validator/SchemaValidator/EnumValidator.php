<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\Schema\JsonEquals;
use Override;

final readonly class EnumValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    private readonly EnumScalarCache $scalarCache;

    public function __construct(ValidatorDependencies $dependencies)
    {
        parent::__construct($dependencies);
        $this->scalarCache = new EnumScalarCache();
    }

    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->enum && [] !== $schema->enum;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->enum || [] === $schema->enum) {
            return;
        }

        if ($this->scalarCache->isScalarLookupEligible($schema, $data)) {
            if ($this->scalarCache->contains($schema, $data)) {
                return;
            }

            throw $this->enumError($data, $schema, $context);
        }

        /** @var mixed $allowedValue */
        foreach ($schema->enum as $allowedValue) {
            if (JsonEquals::equals($allowedValue, $data)) {
                return;
            }
        }

        throw $this->enumError($data, $schema, $context);
    }

    private function enumError(mixed $data, Schema $schema, ?ValidationContext $context): EnumError
    {
        return new EnumError(
            allowedValues: $schema->enum ?? [],
            actual: $data,
            dataPath: $this->getDataPath($context),
            schemaPath: '/enum',
        );
    }
}
