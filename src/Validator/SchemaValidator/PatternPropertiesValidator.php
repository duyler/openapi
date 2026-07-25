<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Override;

use function is_array;
use function is_string;

final readonly class PatternPropertiesValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->patternProperties && [] !== $schema->patternProperties;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->patternProperties || [] === $schema->patternProperties) {
            return;
        }

        if (false === is_array($data)) {
            return;
        }

        /** @var array<string, Schema> $patternProperties */
        $patternProperties = $schema->patternProperties;
        $normalizedPatterns = $this->normalizePatterns($patternProperties);
        $this->applyPatterns($data, $patternProperties, $normalizedPatterns, $context);
    }

    /**
     * @param array<string, Schema> $patternProperties
     *
     * @return array<string, string>
     */
    private function normalizePatterns(array $patternProperties): array
    {
        $regexValidator = $this->regexValidator();
        /** @var array<string, string> $normalizedPatterns */
        $normalizedPatterns = [];

        foreach ($patternProperties as $pattern => $propertySchema) {
            if ('' === $pattern) {
                continue;
            }

            $normalized = $regexValidator->normalize($pattern);
            $normalizedPatterns[$pattern] = $normalized;

            $regexValidator->validate($normalized, "pattern property '{$pattern}'");
        }

        return $normalizedPatterns;
    }

    /**
     * @param array<array-key, mixed>           $data
     * @param array<string, Schema>             $patternProperties
     * @param array<string, string>             $normalizedPatterns
     */
    private function applyPatterns(array $data, array $patternProperties, array $normalizedPatterns, ?ValidationContext $context): void
    {
        $validator = $this->createSchemaValidator();
        $nullableAsType = $context?->nullableAsType ?? true;
        $pregExecutor = $this->pregExecutor();

        /** @var array<string, mixed> $data */
        foreach ($data as $propertyName => $propertyValue) {
            if (false === is_string($propertyName)) {
                continue;
            }

            foreach ($normalizedPatterns as $pattern => $normalizedPattern) {
                if ('' === $normalizedPattern) {
                    continue;
                }

                if (1 !== $pregExecutor->match($normalizedPattern, $propertyName)) {
                    continue;
                }

                $propertySchema = $patternProperties[$pattern];

                if (null === $context) {
                    $context = ValidationContext::create(pool: $this->pool(), nullableAsType: $nullableAsType);
                }

                $context->enterBreadcrumb($propertyName);

                try {
                    /** @var array-key|array<array-key, mixed> $propertyValue */
                    $validator->validate($propertyValue, $propertySchema, $context);
                    $context->markPropertyEvaluated($propertyName);
                } finally {
                    $context->leaveBreadcrumb();
                }
            }
        }
    }
}
