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

final readonly class AdditionalPropertiesValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    private const int MAX_ADDITIONAL_PROPERTY_ERRORS = 100;

    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->additionalProperties;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (false === is_array($data) || array_is_list($data)) {
            return;
        }

        if (null === $schema->additionalProperties) {
            return;
        }

        $additionalKeys = $this->computeAdditionalKeys($data, $schema);

        if ([] === $additionalKeys) {
            return;
        }

        $dataPath = $this->getDataPath($context);

        if (false === $schema->additionalProperties) {
            $this->rejectAdditionalKeys($additionalKeys, $dataPath);

            return;
        }

        if ($schema->additionalProperties instanceof Schema) {
            $this->validateAdditionalSchemas($data, $schema->additionalProperties, $additionalKeys, $context);

            return;
        }

        if (null !== $context) {
            foreach ($additionalKeys as $key) {
                $context->markPropertyEvaluated((string) $key);
            }
        }
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<int|string>
     */
    private function computeAdditionalKeys(array $data, Schema $schema): array
    {
        $definedProperties = array_keys($schema->properties ?? []);
        $additionalKeys = array_diff(array_keys($data), $definedProperties);

        $patternProperties = $schema->patternProperties ?? [];
        if ([] === $patternProperties || [] === $additionalKeys) {
            /** @var list<int|string> $additional */
            return array_values($additionalKeys);
        }

        /** @var array<string, non-empty-string> $normalizedPatterns */
        $normalizedPatterns = [];

        foreach (array_keys($patternProperties) as $pattern) {
            if ('' === $pattern) {
                continue;
            }

            $normalized = $this->regexValidator()->normalize($pattern);
            assert('' !== $normalized);
            $normalizedPatterns[$pattern] = $normalized;
        }

        $pregExecutor = $this->pregExecutor();

        /** @var list<int|string> $filtered */
        $filtered = array_values(array_filter($additionalKeys, static function (int|string $key) use ($normalizedPatterns, $pregExecutor): bool {
            $keyString = (string) $key;

            foreach ($normalizedPatterns as $normalizedPattern) {
                if (1 === $pregExecutor->match($normalizedPattern, $keyString)) {
                    return false;
                }
            }

            return true;
        }));

        return $filtered;
    }

    /**
     * @param list<int|string> $additionalKeys
     */
    private function rejectAdditionalKeys(array $additionalKeys, string $dataPath): void
    {
        $errors = [];
        $propertyNames = [];
        $truncated = 0;

        foreach ($additionalKeys as $key) {
            if (count($errors) >= self::MAX_ADDITIONAL_PROPERTY_ERRORS) {
                $truncated = count($additionalKeys) - self::MAX_ADDITIONAL_PROPERTY_ERRORS;

                break;
            }

            $keyString = (string) $key;
            $propertyNames[] = $keyString;
            $errors[] = new AdditionalPropertyError(
                dataPath: $dataPath,
                schemaPath: '/additionalProperties',
                propertyName: $keyString,
            );
        }

        if (0 !== $truncated) {
            $truncationName = sprintf('... and %d more additional properties', $truncated);
            $propertyNames[] = $truncationName;
            $errors[] = new AdditionalPropertyError(
                dataPath: $dataPath,
                schemaPath: '/additionalProperties',
                propertyName: $truncationName,
            );
        }

        throw new ValidationException(
            sprintf('Additional properties are not allowed: %s', implode(', ', $propertyNames)),
            errors: $errors,
        );
    }

    /**
     * @param array<array-key, mixed>            $data
     * @param list<int|string>                   $additionalKeys
     */
    private function validateAdditionalSchemas(array $data, Schema $additionalSchema, array $additionalKeys, ?ValidationContext $context): void
    {
        $nullableAsType = $context?->nullableAsType ?? true;
        $validator = $this->createSchemaValidator();

        foreach ($additionalKeys as $key) {
            /** @var array-key|array<array-key, mixed> $value */
            $value = $data[$key];
            $keyString = (string) $key;

            if (null === $context) {
                $context = ValidationContext::create(pool: $this->pool(), nullableAsType: $nullableAsType);
            }

            $context->enterBreadcrumb($keyString);

            try {
                $validator->validate($value, $additionalSchema, $context);
                $context->markPropertyEvaluated($keyString);
            } finally {
                $context->leaveBreadcrumb();
            }
        }
    }
}
