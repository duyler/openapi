<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\UnevaluatedPropertyError;
use Override;

use function array_diff;
use function array_filter;
use function array_keys;
use function array_unique;
use function array_values;
use function assert;
use function is_array;
use function is_string;
use function is_bool;

final readonly class UnevaluatedPropertiesValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->unevaluatedProperties;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->unevaluatedProperties) {
            return;
        }

        if (false === is_array($data)) {
            return;
        }

        $evaluatedProperties = $this->getEvaluatedProperties($schema, $data, $context);
        $unevaluatedProperties = array_diff(array_keys($data), $evaluatedProperties);
        /** @var array<array-key, string> $stringUnevaluatedProperties */
        $stringUnevaluatedProperties = array_filter($unevaluatedProperties, is_string(...));

        if ($schema->unevaluatedProperties instanceof Schema) {
            $this->validateUnevaluatedProperties($data, $schema->unevaluatedProperties, $stringUnevaluatedProperties, $context);

            return;
        }

        if ($schema->unevaluatedProperties) {
            return;
        }

        if ([] !== $stringUnevaluatedProperties) {
            $propertyName = array_values($stringUnevaluatedProperties)[0];
            throw new UnevaluatedPropertyError(
                dataPath: $this->getDataPath($context),
                schemaPath: '/unevaluatedProperties',
                propertyName: $propertyName,
            );
        }
    }

    /**
     * @param array<array-key, mixed>           $data
     * @param array<array-key, string>          $stringUnevaluatedProperties
     */
    private function validateUnevaluatedProperties(array $data, Schema $unevaluatedProperties, array $stringUnevaluatedProperties, ?ValidationContext $context): void
    {
        $validator = $this->createSchemaValidator();
        $nullableAsType = $context?->nullableAsType ?? true;

        foreach ($stringUnevaluatedProperties as $propertyName) {
            /** @var array-key|array<array-key, mixed> $value */
            $value = $data[$propertyName];

            if (null === $context) {
                $context = ValidationContext::create(pool: $this->pool(), nullableAsType: $nullableAsType);
            }

            $context->enterBreadcrumb($propertyName);

            try {
                $validator->validate($value, $unevaluatedProperties, $context);
            } finally {
                $context->leaveBreadcrumb();
            }
        }
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<string>
     */
    private function getEvaluatedProperties(Schema $schema, array $data, ?ValidationContext $context): array
    {
        if ((is_bool($schema->additionalProperties) && $schema->additionalProperties) || $schema->additionalProperties instanceof Schema) {
            /** @var list<string> $keys */
            $keys = array_keys($data);

            return $keys;
        }

        $evaluated = [];

        if (null !== $schema->properties) {
            /** @var list<string> $keys */
            $keys = array_keys($schema->properties);
            foreach ($keys as $propertyName) {
                $evaluated[] = $propertyName;
            }
        }

        if (null !== $schema->patternProperties && [] !== $schema->patternProperties) {
            $this->collectPatternMatched($schema, $data, $evaluated);
        }

        if (null !== $context) {
            foreach ($context->evaluatedPropertyNames() as $propertyName) {
                $evaluated[] = $propertyName;
            }
        }

        /** @var list<string> $unique */
        $unique = array_values(array_unique($evaluated));

        return $unique;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<string>            $evaluated
     */
    private function collectPatternMatched(Schema $schema, array $data, array &$evaluated): void
    {
        $patternProperties = $schema->patternProperties ?? [];
        if ([] === $patternProperties) {
            return;
        }

        $regexValidator = $this->regexValidator();
        $pregExecutor = $this->pregExecutor();

        foreach (array_keys($data) as $propertyName) {
            if (false === is_string($propertyName)) {
                continue;
            }

            foreach (array_keys($patternProperties) as $pattern) {
                if ('' === $pattern) {
                    continue;
                }

                $normalizedPattern = $regexValidator->normalize($pattern);
                assert('' !== $normalizedPattern);
                if (1 === $pregExecutor->match($normalizedPattern, $propertyName)) {
                    $evaluated[] = $propertyName;
                }
            }
        }
    }
}
