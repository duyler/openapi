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

use function count;
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

        $dataPath = $this->getDataPath($context);

        // Boolean schema form per JSON Schema 2020-12 §4.3.2.
        // `contains: true` is satisfied by every item, so any non-empty array
        // matches and an empty array fails the default minContains=1.
        if (true === $schema->contains) {
            $matchCount = count($data);
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

            $containsContext = $context ?? ValidationContext::create(pool: $this->pool());

            foreach (array_keys($data) as $index) {
                /** @var int $index */
                $containsContext->markItemEvaluated($index);
            }

            return;
        }

        // `contains: false` never matches → MinContainsError / ContainsMatchError
        // for any non-empty array; empty array still respects minContains.
        if (false === $schema->contains) {
            $effectiveMinContains = $schema->minContains ?? 1;

            if (0 === $effectiveMinContains) {
                return;
            }

            if (1 === $effectiveMinContains) {
                throw new ContainsMatchError(
                    dataPath: $dataPath,
                    schemaPath: '/contains',
                );
            }

            throw new MinContainsError(
                minContains: $effectiveMinContains,
                actualCount: 0,
                dataPath: $dataPath,
                schemaPath: '/minContains',
            );
        }

        $validator = $this->createSchemaValidator();
        $containsContext = $context ?? ValidationContext::create(pool: $this->pool());

        $matchCount = 0;
        $effectiveMinContains = $schema->minContains ?? 1;

        foreach ($data as $index => $item) {
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
                /** @var int $index */
                $context?->markItemEvaluated($index);

                if (null !== $schema->maxContains && $matchCount > $schema->maxContains) {
                    break;
                }
            } catch (ValidationException|AbstractValidationError) {
                continue;
            }
        }

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
