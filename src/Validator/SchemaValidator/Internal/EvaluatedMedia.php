<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator\Internal;

/** @internal */
final readonly class EvaluatedMedia
{
    public function __construct(
        public readonly bool $isValid,
        public readonly string|array|int|float|bool|null $content,
    ) {}
}
