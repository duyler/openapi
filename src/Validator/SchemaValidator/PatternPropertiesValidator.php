<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
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

            RegexValidator::validate(
                RegexValidator::normalize($pattern),
                "pattern property '{$pattern}'",
            );
        }

        foreach ($data as $propertyName => $propertyValue) {
            if (false === is_string($propertyName)) {
                continue;
            }

            foreach ($schema->patternProperties as $pattern => $propertySchema) {
                if ('' === $pattern) {
                    continue;
                }

                $normalizedPattern = RegexValidator::normalize($pattern);
                assert($normalizedPattern !== '');

                $result = preg_match($normalizedPattern, $propertyName);

                if (false !== $result && 1 === $result) {
                    /** @var array-key|array<array-key, mixed> $propertyValue */
                    $validator = new SchemaValidator($this->pool);
                    $nullableAsType = $context?->nullableAsType ?? true;
                    $propertyContext = $context?->withBreadcrumb($propertyName) ?? ValidationContext::create($this->pool, $nullableAsType);
                    $validator->validate($propertyValue, $propertySchema, $propertyContext);
                }
            }
        }
    }
}
