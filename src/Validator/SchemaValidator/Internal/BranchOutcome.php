<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator\Internal;

use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\ValidationException;

/** @internal */
final readonly class BranchOutcome
{
    /**
     * @param list<ValidationException>      $errors
     * @param list<AbstractValidationError>  $abstractErrors
     */
    public function __construct(
        public bool $matched,
        public array $errors,
        public array $abstractErrors,
    ) {}
}
