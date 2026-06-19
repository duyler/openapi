<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Override;

use function assert;
use function is_array;
use function is_string;

final readonly class PatternPropertiesValidator extends AbstractSchemaValidator
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->patternProperties || [] === $schema->patternProperties) {
            return;
        }

        if (false === is_array($data)) {
            return;
        }

        foreach ($schema->patternProperties as $pattern => $propertySchema) {
            if ('' === $pattern) {
                continue;
            }

            $regexValidator = $this->regexValidator();
            $regexValidator->validate(
                $regexValidator->normalize($pattern),
                "pattern property '{$pattern}'",
            );
        }

        /** @var array<string, mixed> $data */
        foreach ($data as $propertyName => $propertyValue) {
            if (false === is_string($propertyName)) {
                continue;
            }

            foreach ($schema->patternProperties as $pattern => $propertySchema) {
                if ('' === $pattern) {
                    continue;
                }

                $normalizedPattern = $this->regexValidator()->normalize($pattern);
                assert('' !== $normalizedPattern);

                $result = preg_match($normalizedPattern, $propertyName);

                if (false !== $result && 1 === $result) {
                    /** @var array-key|array<array-key, mixed> $propertyValue */
                    $validator = $this->createSchemaValidator();
                    $nullableAsType = $context?->nullableAsType ?? true;

                    if (null === $context) {
                        $context = ValidationContext::create(pool: $this->pool, nullableAsType: $nullableAsType);
                    }

                    $context->enterBreadcrumb($propertyName);

                    try {
                        $validator->validate($propertyValue, $propertySchema, $context);
                    } finally {
                        $context->leaveBreadcrumb();
                    }
                }
            }
        }
    }
}
