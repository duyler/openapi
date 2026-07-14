<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AdditionalPropertyError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Override;

use function assert;
use function count;
use function is_array;
use function sprintf;
use function is_string;

final readonly class AdditionalPropertiesValidator extends AbstractSchemaValidator
{
    private const int MAX_ADDITIONAL_PROPERTY_ERRORS = 100;

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
            $errors = [];
            $truncated = 0;

            foreach ($additionalKeys as $key) {
                if (count($errors) >= self::MAX_ADDITIONAL_PROPERTY_ERRORS) {
                    $truncated = count($additionalKeys) - self::MAX_ADDITIONAL_PROPERTY_ERRORS;

                    break;
                }

                $errors[] = new AdditionalPropertyError(
                    dataPath: $dataPath,
                    schemaPath: '/additionalProperties',
                    propertyName: (string) $key,
                );
            }

            if (0 !== $truncated) {
                $errors[] = new AdditionalPropertyError(
                    dataPath: $dataPath,
                    schemaPath: '/additionalProperties',
                    propertyName: sprintf('... and %d more additional properties', $truncated),
                );
            }

            throw new ValidationException(
                sprintf(
                    'Additional properties are not allowed: %s',
                    implode(', ', array_map(
                        static function (AdditionalPropertyError $error): string {
                            $name = $error->params()['propertyName'];
                            assert(is_string($name));

                            return $name;
                        },
                        $errors,
                    )),
                ),
                errors: $errors,
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
