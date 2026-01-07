<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

final readonly class PatternPropertiesValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
    ) {}
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->patternProperties || [] === $schema->patternProperties) {
            return;
        }

        if (false === is_array($data)) {
            return;
        }

        foreach ($data as $propertyName => $propertyValue) {
            if (false === is_string($propertyName)) {
                continue;
            }

            foreach ($schema->patternProperties as $pattern => $propertySchema) {
                if ('' === $pattern) {
                    continue;
                }

                if (preg_match($pattern, $propertyName)) {
                    /** @var array-key|array<array-key, mixed> $propertyValue */
                    $validator = new SchemaValidator($this->pool);
                    $propertyContext = $context?->withBreadcrumb($propertyName) ?? ValidationContext::create($this->pool);
                    $validator->validate($propertyValue, $propertySchema, $propertyContext);
                }
            }
        }
    }
}
