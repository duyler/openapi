<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;

abstract readonly class AbstractSchemaValidator implements SchemaValidatorInterface
{
    protected readonly FormatRegistry $formatRegistry;

    public function __construct(
        protected readonly ValidatorPool $pool,
        ?FormatRegistry $formatRegistry = null,
    ) {
        $this->formatRegistry = $formatRegistry ?? BuiltinFormats::instance();
    }

    protected function getDataPath(?ValidationContext $context): string
    {
        if (null === $context) {
            return '/';
        }

        return $context->breadcrumbs->currentPath();
    }
}
