<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

abstract readonly class AbstractSchemaValidator implements SchemaValidatorInterface
{
    protected readonly FormatRegistry $formatRegistry;
    protected readonly bool $strictFormats;
    protected readonly LoggerInterface $logger;

    public function __construct(
        protected readonly ValidatorPool $pool,
        ?FormatRegistry $formatRegistry = null,
        bool $strictFormats = false,
        ?LoggerInterface $logger = null,
    ) {
        $this->formatRegistry = $formatRegistry ?? BuiltinFormats::instance();
        $this->strictFormats = $strictFormats;
        $this->logger = $logger ?? new NullLogger();
    }

    protected function getDataPath(?ValidationContext $context): string
    {
        if (null === $context) {
            return '/';
        }

        return $context->breadcrumbs->currentPath();
    }

    protected function createSchemaValidator(): SchemaValidator
    {
        return new SchemaValidator(
            pool: $this->pool,
            formatRegistry: $this->formatRegistry,
            strictFormats: $this->strictFormats,
            logger: $this->logger,
        );
    }
}
