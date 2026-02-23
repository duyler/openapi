<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;

use Exception;

use function is_array;
use function assert;

readonly class OneOfValidatorWithContext
{
    public function __construct(
        private readonly ValidatorPool $pool,
        private readonly RefResolverInterface $refResolver,
        private readonly OpenApiDocument $document,
    ) {}

    public function validateWithContext(
        mixed $data,
        Schema $schema,
        ValidationContext $context,
        bool $useDiscriminator = true,
    ): void {
        $oneOf = $schema->oneOf;

        if (null === $oneOf) {
            return;
        }

        if ($useDiscriminator && null !== $schema->discriminator) {
            $this->validateWithDiscriminator($data, $schema, $context);
            return;
        }

        $this->validateWithoutDiscriminator($data, $oneOf, $context);
    }

    private function validateWithDiscriminator(mixed $data, Schema $schema, ValidationContext $context): void
    {
        if (null === $data) {
            assert(null !== $schema->oneOf);
            if ($this->hasNullableSchema($schema->oneOf) && $context->nullableAsType) {
                return;
            }
            throw new ValidationException(
                'Discriminator validation failed: data must be an object',
            );
        }

        if (false === is_array($data)) {
            throw new ValidationException(
                'Discriminator validation failed: data must be an object',
            );
        }

        $discriminatorValidator = new DiscriminatorValidator($this->refResolver, $this->pool);
        $dataPath = $context->breadcrumbs->currentPath();

        $discriminatorValidator->validate($data, $schema, $this->document, $dataPath);
    }

    /**
     * Check if any schema in oneOf is nullable
     *
     * @param array<int, Schema> $oneOf
     * @return bool
     */
    private function hasNullableSchema(array $oneOf): bool
    {
        return array_any($oneOf, fn($subSchema) => $subSchema->nullable);
    }

    private function validateWithoutDiscriminator(mixed $data, array $oneOf, ValidationContext $context): void
    {
        $validCount = 0;
        $errors = [];
        $abstractErrors = [];

        foreach ($oneOf as $subSchema) {
            if (false === $subSchema instanceof Schema) {
                continue;
            }

            try {
                $allowNull = $subSchema->nullable && $context->nullableAsType;
                $normalizedData = SchemaValueNormalizer::normalize($data, $allowNull);
                $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document, $context->nullableAsType);
                $validator->validateWithContext($normalizedData, $subSchema, $context);
                ++$validCount;
            } catch (Exception $e) {
                if ($e instanceof AbstractValidationError) {
                    $abstractErrors[] = $e;
                } else {
                    $errors[] = new ValidationException(
                        message: 'Invalid data for oneOf schema: ' . $e->getMessage(),
                        previous: $e,
                    );
                }
            }
        }

        if (0 === $validCount) {
            throw new ValidationException(
                'Exactly one of schemas must match, but none did',
                errors: $abstractErrors,
            );
        }

        if ($validCount > 1) {
            throw new ValidationException(
                'Data matches multiple schemas, but should match exactly one',
            );
        }
    }
}
