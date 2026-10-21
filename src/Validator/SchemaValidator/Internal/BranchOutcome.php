<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator\Internal;

use Duyler\OpenApi\Validator\Exception\ValidationErrorInterface;

/** @internal */
final readonly class BranchOutcome
{
    /**
     * @param list<ValidationErrorInterface> $errors
     */
    public function __construct(
        public bool $matched,
        public array $errors,
    ) {}
}
