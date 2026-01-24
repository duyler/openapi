<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

use function is_array;

final readonly class ContainsValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
    ) {}

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->contains) {
            return;
        }

        if (false === is_array($data) || false === array_is_list($data)) {
            return;
        }

        $validator = new SchemaValidator($this->pool);
        $hasMatch = false;

        foreach ($data as $item) {
            try {
                /** @var array-key|array<array-key, mixed> $item */
                $validator->validate($item, $schema->contains, $context);
                $hasMatch = true;
                break;
            } catch (ValidationException|AbstractValidationError) {
                continue;
            }
        }

        if (false === $hasMatch) {
            throw new ValidationException(
                'Array does not contain an item matching the contains schema',
            );
        }
    }
}
