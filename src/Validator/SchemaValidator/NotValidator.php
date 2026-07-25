<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\NotValidationError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use Override;

use function is_bool;

final readonly class NotValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->not;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->not) {
            return;
        }

        if (false === $schema->not) {
            return;
        }

        if ($schema->not instanceof Schema && $this->matchesNotSchema($data, $schema->not, $context)) {
            throw $this->buildNotValidationError($context);
        }

        if (is_bool($schema->not) && $schema->not) {
            throw $this->buildNotValidationError($context);
        }
    }

    private function matchesNotSchema(mixed $data, Schema $notSchema, ?ValidationContext $context): bool
    {
        $nullableAsType = $context?->nullableAsType ?? true;
        $validator = $this->createSchemaValidator();
        $childContext = null !== $context ? $context->forkForBranch() : null;

        try {
            $allowNull = $nullableAsType && ($notSchema->nullable
                || SchemaValueNormalizer::doesTypeIncludeNull($notSchema->type));
            $normalizedData = SchemaValueNormalizer::normalize($data, $allowNull);
            $validator->validate($normalizedData, $notSchema, $childContext);
        } catch (InvalidDataTypeException|ValidationException|AbstractValidationError) {
            return false;
        }

        return true;
    }

    private function buildNotValidationError(?ValidationContext $context): ValidationException
    {
        return new ValidationException(
            'Data must NOT match the "not" schema',
            errors: [
                new NotValidationError(
                    dataPath: $this->getDataPath($context),
                    schemaPath: '/not',
                ),
            ],
        );
    }
}
