<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Builder\Dto;

use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

final readonly class BuilderConfig
{
    public function __construct(
        public readonly ?string $specPath = null,
        public readonly ?string $specContent = null,
        public readonly ?string $specType = null,
        public readonly ?ValidatorPool $pool = null,
        public readonly ?SchemaCache $cache = null,
        public readonly ?LoggerInterface $logger = null,
        public readonly ?FormatRegistry $formatRegistry = null,
        public readonly ?bool $coercion = null,
        public readonly ?bool $nullableAsType = null,
        public readonly ?EmptyArrayStrategy $emptyArrayStrategy = null,
        public readonly ?ErrorFormatterInterface $errorFormatter = null,
        public readonly ?EventDispatcherInterface $eventDispatcher = null,
        public readonly ?bool $securityValidation = null,
        public readonly ?bool $serverPathResolution = null,
        public readonly ?bool $strictFormats = null,
        public readonly ?bool $reportDeprecated = null,
    ) {}

    public function merge(self $overrides): self
    {
        return new self(
            specPath: $overrides->specPath ?? $this->specPath,
            specContent: $overrides->specContent ?? $this->specContent,
            specType: $overrides->specType ?? $this->specType,
            pool: $overrides->pool ?? $this->pool,
            cache: $overrides->cache ?? $this->cache,
            logger: $overrides->logger ?? $this->logger,
            formatRegistry: $overrides->formatRegistry ?? $this->formatRegistry,
            coercion: $overrides->coercion ?? $this->coercion,
            nullableAsType: $overrides->nullableAsType ?? $this->nullableAsType,
            emptyArrayStrategy: $overrides->emptyArrayStrategy ?? $this->emptyArrayStrategy,
            errorFormatter: $overrides->errorFormatter ?? $this->errorFormatter,
            eventDispatcher: $overrides->eventDispatcher ?? $this->eventDispatcher,
            securityValidation: $overrides->securityValidation ?? $this->securityValidation,
            serverPathResolution: $overrides->serverPathResolution ?? $this->serverPathResolution,
            strictFormats: $overrides->strictFormats ?? $this->strictFormats,
            reportDeprecated: $overrides->reportDeprecated ?? $this->reportDeprecated,
        );
    }
}
