<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;

interface SchemaValidatorInterface
{
    public function validate(array|int|string|float|bool|null $data, Schema $schema, ?ValidationContext $context = null): void;
}
