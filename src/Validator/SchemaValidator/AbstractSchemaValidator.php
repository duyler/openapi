<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Registry\ValidatorRegistryInterface;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

abstract readonly class AbstractSchemaValidator implements SchemaValidatorInterface
{
    protected readonly LoggerInterface $logger;

    public function __construct(
        protected readonly ValidatorPool $pool,
        protected readonly FormatRegistry $formatRegistry,
        protected readonly bool $strictFormats = false,
        ?LoggerInterface $logger = null,
        protected readonly bool $reportDeprecated = false,
        protected readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ?ValidatorRegistryInterface $registry = null,
        private readonly RegexValidator $regexValidator = new RegexValidator(),
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    protected function getDataPath(?ValidationContext $context): string
    {
        if (null === $context) {
            return '/';
        }

        return $context->breadcrumbs->currentPath();
    }

    protected function regexValidator(): RegexValidator
    {
        return $this->regexValidator;
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
            regexValidator: $this->regexValidator,
        );
    }
}
