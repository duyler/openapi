<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Override;

use function assert;
use function is_array;

final readonly class AdditionalPropertiesValidator extends AbstractSchemaValidator
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (false === is_array($data) || array_is_list($data)) {
            return;
        }

        if (null === $schema->additionalProperties) {
            return;
        }

        $definedProperties = array_keys($schema->properties ?? []);
        $additionalKeys = array_diff(array_keys($data), $definedProperties);

        $patternProperties = $schema->patternProperties ?? [];
        if ([] !== $patternProperties && [] !== $additionalKeys) {
            $additionalKeys = array_values(array_filter($additionalKeys, function (int|string $key) use ($patternProperties): bool {
                foreach (array_keys($patternProperties) as $pattern) {
                    if ('' === $pattern) {
                        continue;
                    }

                    $normalizedPattern = $this->regexValidator()->normalize($pattern);
                    assert('' !== $normalizedPattern);
                    if (1 === preg_match($normalizedPattern, (string) $key)) {
                        return false;
                    }
                }

                return true;
            }));
        }

        if ([] === $additionalKeys) {
            return;
        }

        $dataPath = $this->getDataPath($context);

        if (false === $schema->additionalProperties) {
            throw new ValidationException(
                'Additional properties are not allowed: ' . implode(', ', $additionalKeys),
            );
        }

        if ($schema->additionalProperties instanceof Schema) {
            $nullableAsType = $context?->nullableAsType ?? true;
            $validator = $this->createSchemaValidator();

            foreach ($additionalKeys as $key) {
                /** @var array-key|array<array-key, mixed> $value */
                $value = $data[$key];

                if (null === $context) {
                    $context = ValidationContext::create(pool: $this->pool, nullableAsType: $nullableAsType);
                }

                $context->enterBreadcrumb((string) $key);

                try {
                    $validator->validate($value, $schema->additionalProperties, $context);
                } finally {
                    $context->leaveBreadcrumb();
                }
            }
        }
    }
}
