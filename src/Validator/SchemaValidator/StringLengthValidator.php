<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\MaxLengthError;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\SchemaValidator\Trait\LengthValidationTrait;
use Override;

use function is_string;

readonly class StringLengthValidator extends AbstractSchemaValidator
{
    use LengthValidationTrait;

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (false === is_string($data)) {
            return;
        }

        $dataPath = $this->getDataPath($context);
        $length = mb_strlen($data);

        $this->validateLength(
            actual: $length,
            min: $schema->minLength,
            max: $schema->maxLength,
            minErrorFactory: static fn(int $min, int $actual) => new MinLengthError($min, $actual, $dataPath, '/minLength'),
            maxErrorFactory: static fn(int $max, int $actual) => new MaxLengthError($max, $actual, $dataPath, '/maxLength'),
        );
    }
}
