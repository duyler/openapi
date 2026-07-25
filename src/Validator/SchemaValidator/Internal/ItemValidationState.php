<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;

/** @internal */
final class ItemValidationState
{
    public function __construct(
        public Schema $itemsSchema,
        public SchemaValidatorInterface $validator,
        public bool $allowNull,
        public bool $nullableAsType,
        public ?ValidationContext $context,
    ) {}
}
