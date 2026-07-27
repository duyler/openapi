<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Coercion;

use Duyler\OpenApi\Validator\Coercion\Internal\BooleanCoercer;
use Duyler\OpenApi\Validator\Coercion\Internal\IntegerCoercer;
use Duyler\OpenApi\Validator\Coercion\Internal\NumberCoercer;
use Duyler\OpenApi\Validator\Coercion\Internal\StringCoercer;

abstract readonly class AbstractCoercer
{
    public function __construct(
        protected readonly BooleanCoercer $booleanCoercer = new BooleanCoercer(),
        protected readonly IntegerCoercer $integerCoercer = new IntegerCoercer(),
        protected readonly NumberCoercer $numberCoercer = new NumberCoercer(),
        protected readonly StringCoercer $stringCoercer = new StringCoercer(),
    ) {}

    protected function isValidType(mixed $value, string $type): bool
    {
        return $this->stringCoercer->isValidType($value, $type);
    }

    protected function coerceToBoolean(mixed $value): bool|int|string|float|array|null
    {
        return $this->booleanCoercer->coerce($value);
    }

    protected function coerceToBooleanStrict(mixed $value): bool|int|string|float|array|null
    {
        return $this->booleanCoercer->coerceStrict($value);
    }

    protected function coerceToInteger(mixed $value): int|string|float|bool|array|null
    {
        return $this->integerCoercer->coerce($value);
    }

    protected function coerceToIntegerStrict(mixed $value): int|string|float|bool|array|null
    {
        return $this->integerCoercer->coerceStrict($value);
    }

    protected function coerceToNumber(mixed $value): float|int|string|bool|array|null
    {
        return $this->numberCoercer->coerce($value);
    }

    protected function coerceToNumberStrict(mixed $value): float|int|string|bool|array|null
    {
        return $this->numberCoercer->coerceStrict($value);
    }

    protected function coerceToString(mixed $value): string|int|float|bool|array|null
    {
        return $this->stringCoercer->coerce($value);
    }
}
