<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\MaximumError;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\MultipleOfKeywordError;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

use function is_float;
use function is_int;

final readonly class NumericRangeValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
    ) {}

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (false === is_int($data) && false === is_float($data)) {
            return;
        }

        $dataPath = null !== $context ? $context->breadcrumbs->currentPath() : '/';

        if (null !== $schema->minimum && $data < $schema->minimum) {
            throw new MinimumError(
                minimum: $schema->minimum,
                actual: $data,
                dataPath: $dataPath,
                schemaPath: '/minimum',
            );
        }

        if (null !== $schema->exclusiveMinimum && $data <= $schema->exclusiveMinimum) {
            throw new MinimumError(
                minimum: $schema->exclusiveMinimum,
                actual: $data,
                dataPath: $dataPath,
                schemaPath: '/exclusiveMinimum',
            );
        }

        if (null !== $schema->maximum && $data > $schema->maximum) {
            throw new MaximumError(
                maximum: $schema->maximum,
                actual: $data,
                dataPath: $dataPath,
                schemaPath: '/maximum',
            );
        }

        if (null !== $schema->exclusiveMaximum && $data >= $schema->exclusiveMaximum) {
            throw new MaximumError(
                maximum: $schema->exclusiveMaximum,
                actual: $data,
                dataPath: $dataPath,
                schemaPath: '/exclusiveMaximum',
            );
        }

        if (null !== $schema->multipleOf) {
            if (0.0 === $schema->multipleOf) {
                return;
            }

            $remainder = fmod($data, $schema->multipleOf);

            if (false === $this->isMultipleOfValid($remainder, $schema->multipleOf)) {
                throw new MultipleOfKeywordError(
                    multipleOf: $schema->multipleOf,
                    value: $data,
                    dataPath: $dataPath,
                    schemaPath: '/multipleOf',
                );
            }
        }
    }

    private function isMultipleOfValid(float $remainder, float $multipleOf): bool
    {
        $epsilon = 1e-10;

        return abs($remainder) < $epsilon || abs($remainder - $multipleOf) < $epsilon;
    }
}
