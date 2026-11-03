<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;

/** @internal */
interface SchemaValidatorInterface
{
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void;
}
