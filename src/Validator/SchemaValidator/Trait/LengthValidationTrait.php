<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator\Trait;

trait LengthValidationTrait
{
    private function validateLength(
        int $actual,
        ?int $min,
        ?int $max,
        callable $minErrorFactory,
        callable $maxErrorFactory,
    ): void {
        if (null !== $min && $actual < $min) {
            throw $minErrorFactory($min, $actual);
        }

        if (null !== $max && $actual > $max) {
            throw $maxErrorFactory($max, $actual);
        }
    }
}
