<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\UnevaluatedPropertyError;
use Override;

use function array_filter;
use function assert;
use function is_array;
use function is_string;

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
                    $validator->validate($value, $schema->unevaluatedProperties, $context);
                } finally {
                    $context->leaveBreadcrumb();
                }
            }

            return;
        }

        if ($schema->unevaluatedProperties) {
            return;
        }

        if ([] !== $stringUnevaluatedProperties) {
            $dataPath = $this->getDataPath($context);
            $propertyName = array_values($stringUnevaluatedProperties)[0];
            throw new UnevaluatedPropertyError(
                dataPath: $dataPath,
                schemaPath: '/unevaluatedProperties',
                propertyName: $propertyName,
            );
        }
    }

    /**
     * Returns the list of object property names evaluated by this
     * schema's own properties / patternProperties / additionalProperties
     * keywords plus every property registered by an in-place applicator
     * (allOf / anyOf / oneOf / if / then / else / $ref / contains) via
     * ValidationContext annotation-state (R3-SPEC-001 / R3-SPEC-004).
     *
     * Annotation-state is only consulted when a non-null context is
     * supplied; the legacy {@see SchemaValidator}
     * path passes null and falls back to static analysis only.
     *
     * @param array<array-key, mixed> $data
     *
     * @return list<string>
     */
    private function getEvaluatedProperties(Schema $schema, array $data, ?ValidationContext $context): array
    {
        if (true === $schema->additionalProperties || $schema->additionalProperties instanceof Schema) {
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
            foreach (array_keys($data) as $propertyName) {
                if (false === is_string($propertyName)) {
                    continue;
                }

                foreach (array_keys($schema->patternProperties) as $pattern) {
                    if ('' === $pattern) {
                        continue;
                    }

                    $normalizedPattern = $this->regexValidator()->normalize($pattern);
                    assert('' !== $normalizedPattern);
                    if (1 === $this->pregExecutor()->match($normalizedPattern, $propertyName)) {
                        $evaluated[] = $propertyName;
                    }
                }
            }
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
}
