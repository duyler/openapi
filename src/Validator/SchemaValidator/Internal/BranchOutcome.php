<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator\Internal;

use Duyler\OpenApi\Validator\Exception\ValidationErrorInterface;

/** @internal */
final readonly class BranchOutcome
{
    /**
     * @param list<ValidationErrorInterface> $errors Canonical error list for the branch,
     *                                               empty only when the branch matched.
     */
    public function __construct(
        public bool $matched,
        public array $errors,
    ) {}
}
