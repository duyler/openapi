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
use function is_bool;

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

        if (is_bool($schema->contains) && $schema->contains) {
            $this->validateBooleanTrueContains($data, $schema, $context, $dataPath);

            return;
        }

        if (false === $schema->contains) {
            // contains: false forbids any matching item; treat as 0 matches
            // and apply the standard min/max bounds.
            $this->enforceContainsBounds(0, $schema, $dataPath);

            return;
        }

        /** @var Schema $containsSchema */
        $containsSchema = $schema->contains;
        $matchCount = $this->countSchemaMatches($data, $containsSchema, $context, $dataPath, $schema->maxContains);
        $this->enforceContainsBounds($matchCount, $schema, $dataPath);
    }

    private function validateBooleanTrueContains(array $data, Schema $schema, ?ValidationContext $context, string $dataPath): void
    {
        $this->enforceContainsBounds(count($data), $schema, $dataPath);

        $containsContext = $context ?? ValidationContext::create(pool: $this->pool());

        foreach (array_keys($data) as $index) {
            /** @var int $index */
            $containsContext->markItemEvaluated($index);
        }
    }

    /**
     * @param array<int, mixed> $data
     */
    private function countSchemaMatches(array $data, Schema $containsSchema, ?ValidationContext $context, string $dataPath, ?int $maxContains = null): int
    {
        $validator = $this->createSchemaValidator();
        $containsContext = $context ?? ValidationContext::create(pool: $this->pool());

        $matchCount = 0;

        foreach ($data as $index => $item) {
            if (self::MAX_CONTAINS_VALIDATIONS <= $matchCount) {
                throw new TooManyContainsValidationsError(
                    max: self::MAX_CONTAINS_VALIDATIONS,
                    dataPath: $dataPath,
                );
            }

            try {
                /** @var array-key|array<array-key, mixed> $item */
                $validator->validate($item, $containsSchema, $containsContext);
                ++$matchCount;
                /** @var int $index */
                $context?->markItemEvaluated($index);

                if (null !== $maxContains && $matchCount > $maxContains) {
                    break;
                }
            } catch (ValidationException|AbstractValidationError) {
                continue;
            }
        }

        return $matchCount;
    }

    private function enforceContainsBounds(int $matchCount, Schema $schema, string $dataPath): void
    {
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
