<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Example;
use Duyler\OpenApi\Validator\TypeFormatter;

use function is_array;
use function is_scalar;
use function sprintf;

/** @internal */
final readonly class InlineExampleResolver
{
    public function resolve(mixed $value): ?Example
    {
        if (is_array($value)) {
            return null;
        }

        if (null === $value || is_scalar($value)) {
            return new Example(value: $value);
        }

        throw new InvalidSchemaException(sprintf(
            'Inline example must be a scalar or null, got %s.',
            TypeFormatter::format($value),
        ));
    }
}
