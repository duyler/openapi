<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Registry;

use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;

/**
 * @internal Legacy registry contract, retained for the {@see DefaultValidatorRegistry} that backs
 *           the recursion dispatcher in {@see SchemaValidator}. Will be removed together with the
 *           legacy dispatcher in task 14b.
 */
interface ValidatorRegistryInterface
{
    public function getValidator(string $type): SchemaValidatorInterface;

    public function getAllValidators(): iterable;
}
