<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Registry\ValidatorRegistryInterface;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ValidatorDependencies
{
    private ?SchemaValidator $rootSchemaValidator = null;

    public function __construct(
        public readonly ValidatorPool $pool,
        public readonly FormatRegistry $formatRegistry,
        public readonly bool $strictFormats = false,
        public readonly LoggerInterface $logger = new NullLogger(),
        public readonly bool $reportDeprecated = false,
        public readonly ?EventDispatcherInterface $eventDispatcher = null,
        public readonly ?ValidatorRegistryInterface $registry = null,
        public readonly RegexValidator $regexValidator = new RegexValidator(),
        public readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}

    public function rootSchemaValidator(): SchemaValidator
    {
        return $this->rootSchemaValidator ??= new SchemaValidator(
            pool: $this->pool,
            formatRegistry: $this->formatRegistry,
            registry: $this->registry,
            strictFormats: $this->strictFormats,
            logger: $this->logger,
            reportDeprecated: $this->reportDeprecated,
            eventDispatcher: $this->eventDispatcher,
            regexValidator: $this->regexValidator,
            pregExecutor: $this->pregExecutor,
        );
    }
}
