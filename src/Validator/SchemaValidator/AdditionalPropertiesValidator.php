<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

final readonly class AdditionalPropertiesValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
    ) {}
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

        if ([] === $additionalKeys) {
            return;
        }

        $dataPath = null !== $context ? $context->breadcrumbs->currentPath() : '/';

        if (false === $schema->additionalProperties) {
            throw new ValidationException(
                'Additional properties are not allowed: ' . implode(', ', $additionalKeys),
            );
        }

        if (true === $schema->additionalProperties) {
            return;
        }

        $validator = new SchemaValidator($this->pool);

        foreach ($additionalKeys as $key) {
            /** @var array-key|array<array-key, mixed> $value */
            $value = $data[$key];
            $keyContext = $context?->withBreadcrumb((string) $key) ?? ValidationContext::create($this->pool);
            $validator->validate($value, $schema->additionalProperties, $keyContext);
        }
    }
}
