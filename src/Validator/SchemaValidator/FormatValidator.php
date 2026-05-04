<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

use function is_array;
use function is_string;

readonly class FormatValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
        private readonly FormatRegistry $formatRegistry,
    ) {}

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->format || null === $schema->type) {
            return;
        }

        // For union types, get the first non-null string type for format validation
        if (is_array($schema->type)) {
            $type = array_find($schema->type, fn($t) => is_string($t));
            if (!is_string($type)) {
                return;
            }
        } else {
            $type = $schema->type;
        }

        $formatValidator = $this->formatRegistry->getValidator($type, $schema->format);

        if (null === $formatValidator) {
            return;
        }

        $formatValidator->validate($data);
    }
}
