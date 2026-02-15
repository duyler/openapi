<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\MaxPropertiesError;
use Duyler\OpenApi\Validator\Exception\MinPropertiesError;
use Duyler\OpenApi\Validator\SchemaValidator\Trait\LengthValidationTrait;
use Override;

use function count;
use function is_array;

readonly class ObjectLengthValidator extends AbstractSchemaValidator
{
    use LengthValidationTrait;

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (false === is_array($data) || ([] !== $data && array_is_list($data))) {
            return;
        }

        $dataPath = $this->getDataPath($context);
        /** @var array<array-key, mixed> $data */
        $count = count($data);

        $this->validateLength(
            actual: $count,
            min: $schema->minProperties,
            max: $schema->maxProperties,
            minErrorFactory: static fn(int $min, int $actual) => new MinPropertiesError($min, $actual, $dataPath, '/minProperties'),
            maxErrorFactory: static fn(int $max, int $actual) => new MaxPropertiesError($max, $actual, $dataPath, '/maxProperties'),
        );
    }
}
