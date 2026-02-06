<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Registry;

use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;

interface ValidatorRegistryInterface
{
    public function getValidator(string $type): SchemaValidatorInterface;

    public function getAllValidators(): iterable;
}
