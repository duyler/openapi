<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

final readonly class NotValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
    ) {}

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->not) {
            return;
        }

        $validator = new SchemaValidator($this->pool);

        try {
            $normalizedData = SchemaValueNormalizer::normalize($data);
            $validator->validate($normalizedData, $schema->not, $context);
        } catch (InvalidDataTypeException|ValidationException|AbstractValidationError) {
            return;
        }

        throw new ValidationException(
            'Data must NOT match the "not" schema',
        );
    }
}
