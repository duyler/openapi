<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\ContainsMatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Override;

use function is_array;

final readonly class ContainsValidator extends AbstractSchemaValidator
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->contains) {
            return;
        }

        if (false === is_array($data) || false === array_is_list($data)) {
            return;
        }

        $nullableAsType = $context?->nullableAsType ?? true;
        $validator = new SchemaValidator($this->pool);
        $containsContext = $context ?? ValidationContext::create($this->pool, $nullableAsType);
        $hasMatch = false;

        foreach ($data as $item) {
            try {
                /** @var array-key|array<array-key, mixed> $item */
                $validator->validate($item, $schema->contains, $containsContext);
                $hasMatch = true;
                break;
            } catch (ValidationException|AbstractValidationError) {
                continue;
            }
        }

        if (false === $hasMatch) {
            $dataPath = $this->getDataPath($context);
            throw new ContainsMatchError(
                dataPath: $dataPath,
                schemaPath: '/contains',
            );
        }
    }
}
