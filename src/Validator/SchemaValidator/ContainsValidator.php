<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\ContainsMatchError;
use Duyler\OpenApi\Validator\Exception\MaxContainsError;
use Duyler\OpenApi\Validator\Exception\MinContainsError;
use Duyler\OpenApi\Validator\Exception\TooManyContainsValidationsError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Override;

use function is_array;

final readonly class ContainsValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    private const int MAX_CONTAINS_VALIDATIONS = 10000;

    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->contains;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->contains) {
            return;
        }

        if (false === is_array($data) || false === array_is_list($data)) {
            return;
        }

        $nullableAsType = $context?->nullableAsType ?? true;
        $dataPath = $this->getDataPath($context);
        $validator = $this->createSchemaValidator();
        $containsContext = $context ?? ValidationContext::create(pool: $this->pool(), nullableAsType: $nullableAsType);

        $matchCount = 0;

        foreach ($data as $item) {
            if (self::MAX_CONTAINS_VALIDATIONS <= $matchCount) {
                throw new TooManyContainsValidationsError(
                    max: self::MAX_CONTAINS_VALIDATIONS,
                    dataPath: $dataPath,
                );
            }

            try {
                /** @var array-key|array<array-key, mixed> $item */
                $validator->validate($item, $schema->contains, $containsContext);
                ++$matchCount;

                if (null !== $schema->maxContains && $matchCount > $schema->maxContains) {
                    break;
                }
            } catch (ValidationException|AbstractValidationError) {
                continue;
            }
        }

        $effectiveMinContains = $schema->minContains ?? 1;

        if ($matchCount < $effectiveMinContains) {
            if (0 === $matchCount && 1 === $effectiveMinContains) {
                throw new ContainsMatchError(
                    dataPath: $dataPath,
                    schemaPath: '/contains',
                );
            }

            throw new MinContainsError(
                minContains: $effectiveMinContains,
                actualCount: $matchCount,
                dataPath: $dataPath,
                schemaPath: '/minContains',
            );
        }

        if (null !== $schema->maxContains && $matchCount > $schema->maxContains) {
            throw new MaxContainsError(
                maxContains: $schema->maxContains,
                minDetectedCount: $matchCount,
                dataPath: $dataPath,
                schemaPath: '/maxContains',
            );
        }
    }
}
