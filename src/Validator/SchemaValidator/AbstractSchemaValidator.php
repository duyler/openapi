<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Registry\ValidatorRegistryInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

abstract readonly class AbstractSchemaValidator implements SchemaValidatorInterface
{
    protected readonly FormatRegistry $formatRegistry;
    protected readonly bool $strictFormats;
    protected readonly LoggerInterface $logger;
    protected readonly bool $reportDeprecated;
    protected readonly ?EventDispatcherInterface $eventDispatcher;

    public function __construct(
        protected readonly ValidatorPool $pool,
        FormatRegistry $formatRegistry,
        bool $strictFormats = false,
        ?LoggerInterface $logger = null,
        bool $reportDeprecated = false,
        ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ?ValidatorRegistryInterface $registry = null,
    ) {
        $this->formatRegistry = $formatRegistry;
        $this->strictFormats = $strictFormats;
        $this->logger = $logger ?? new NullLogger();
        $this->reportDeprecated = $reportDeprecated;
        $this->eventDispatcher = $eventDispatcher;
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
            registry: $this->registry,
            strictFormats: $this->strictFormats,
            logger: $this->logger,
            reportDeprecated: $this->reportDeprecated,
            eventDispatcher: $this->eventDispatcher,
        );
    }
}
