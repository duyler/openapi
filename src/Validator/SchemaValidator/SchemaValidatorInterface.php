<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;

/**
 * @internal Contract for individual JSON Schema keyword validators (Type, Format, Const, Enum, ...)
 *           and the legacy recursion dispatcher in {@see SchemaValidator}. Will be revisited in
 *           task 17 (P-058) when keyword validator recursion migrates to
 *           {@see SchemaValidatorWithContext}.
 */
interface SchemaValidatorInterface
{
    public function validate(array|int|string|float|bool|null $data, Schema $schema, ?ValidationContext $context = null): void;
}
