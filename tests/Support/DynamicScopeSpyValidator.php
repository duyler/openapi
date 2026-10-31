<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Support;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Override;

final class DynamicScopeSpyValidator implements SchemaValidatorInterface
{
    /** @var list<list<Schema>> */
    public array $observedScopes = [];

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        $this->observedScopes[] = null === $context ? [] : $context->dynamicScope();
    }
}
