<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Psr15;

use Closure;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;

final class ValidationMiddlewareBuilder extends OpenApiValidatorBuilder
{
    public function __construct(
        ?string $specPath = null,
        ?string $specContent = null,
        ?string $specType = null,
        ?ValidatorPool $pool = null,
        ?SchemaCache $cache = null,
        ?object $logger = null,
        ?FormatRegistry $formatRegistry = null,
        bool $coercion = false,
        bool $nullableAsType = false,
        ?ErrorFormatterInterface $errorFormatter = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        private readonly ?Closure $onRequestError = null,
        private readonly ?Closure $onResponseError = null,
    ) {
        parent::__construct(
            specPath: $specPath,
            specContent: $specContent,
            specType: $specType,
            pool: $pool,
            cache: $cache,
            logger: $logger,
            formatRegistry: $formatRegistry,
            coercion: $coercion,
            nullableAsType: $nullableAsType,
            errorFormatter: $errorFormatter,
            eventDispatcher: $eventDispatcher,
        );
    }

    public function onRequestError(Closure $handler): self
    {
        return new self(
            specPath: $this->specPath,
            specContent: $this->specContent,
            specType: $this->specType,
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            onRequestError: $handler,
            onResponseError: $this->onResponseError,
        );
    }

    public function onResponseError(Closure $handler): self
    {
        return new self(
            specPath: $this->specPath,
            specContent: $this->specContent,
            specType: $this->specType,
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            onRequestError: $this->onRequestError,
            onResponseError: $handler,
        );
    }

    #[Override]
    public function fromYamlFile(string $path): self
    {
        return new self(
            specPath: $path,
            specType: 'yaml',
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            onRequestError: $this->onRequestError,
            onResponseError: $this->onResponseError,
        );
    }

    #[Override]
    public function fromJsonFile(string $path): self
    {
        return new self(
            specPath: $path,
            specType: 'json',
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            onRequestError: $this->onRequestError,
            onResponseError: $this->onResponseError,
        );
    }

    #[Override]
    public function fromYamlString(string $content): self
    {
        return new self(
            specContent: $content,
            specType: 'yaml',
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            onRequestError: $this->onRequestError,
            onResponseError: $this->onResponseError,
        );
    }

    #[Override]
    public function fromJsonString(string $content): self
    {
        return new self(
            specContent: $content,
            specType: 'json',
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            onRequestError: $this->onRequestError,
            onResponseError: $this->onResponseError,
        );
    }

    #[Override]
    public function withValidatorPool(ValidatorPool $pool): self
    {
        return new self(
            specPath: $this->specPath,
            specContent: $this->specContent,
            specType: $this->specType,
            pool: $pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            onRequestError: $this->onRequestError,
            onResponseError: $this->onResponseError,
        );
    }

    #[Override]
    public function withCache(SchemaCache $cache): self
    {
        return new self(
            specPath: $this->specPath,
            specContent: $this->specContent,
            specType: $this->specType,
            pool: $this->pool,
            cache: $cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            onRequestError: $this->onRequestError,
            onResponseError: $this->onResponseError,
        );
    }

    #[Override]
    public function withLogger(object $logger): self
    {
        return new self(
            specPath: $this->specPath,
            specContent: $this->specContent,
            specType: $this->specType,
            pool: $this->pool,
            cache: $this->cache,
            logger: $logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            onRequestError: $this->onRequestError,
            onResponseError: $this->onResponseError,
        );
    }

    #[Override]
    public function withErrorFormatter(ErrorFormatterInterface $formatter): self
    {
        return new self(
            specPath: $this->specPath,
            specContent: $this->specContent,
            specType: $this->specType,
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $formatter,
            eventDispatcher: $this->eventDispatcher,
            onRequestError: $this->onRequestError,
            onResponseError: $this->onResponseError,
        );
    }

    #[Override]
    public function enableCoercion(): self
    {
        return new self(
            specPath: $this->specPath,
            specContent: $this->specContent,
            specType: $this->specType,
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: true,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            onRequestError: $this->onRequestError,
            onResponseError: $this->onResponseError,
        );
    }

    #[Override]
    public function enableNullableAsType(): self
    {
        return new self(
            specPath: $this->specPath,
            specContent: $this->specContent,
            specType: $this->specType,
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: true,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            onRequestError: $this->onRequestError,
            onResponseError: $this->onResponseError,
        );
    }

    #[Override]
    public function withEventDispatcher(EventDispatcherInterface $dispatcher): self
    {
        return new self(
            specPath: $this->specPath,
            specContent: $this->specContent,
            specType: $this->specType,
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $dispatcher,
            onRequestError: $this->onRequestError,
            onResponseError: $this->onResponseError,
        );
    }

    #[Override]
    public function withFormat(string $type, string $format, FormatValidatorInterface $validator): self
    {
        $registry = $this->formatRegistry ?? BuiltinFormats::create();
        $registry = $registry->registerFormat($type, $format, $validator);

        return new self(
            specPath: $this->specPath,
            specContent: $this->specContent,
            specType: $this->specType,
            pool: $this->pool,
            cache: $this->cache,
            logger: $this->logger,
            formatRegistry: $registry,
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            errorFormatter: $this->errorFormatter,
            eventDispatcher: $this->eventDispatcher,
            onRequestError: $this->onRequestError,
            onResponseError: $this->onResponseError,
        );
    }

    public function buildMiddleware(): ValidationMiddleware
    {
        $validator = $this->build();

        return new ValidationMiddleware(
            validator: $validator,
            onRequestError: $this->onRequestError,
            onResponseError: $this->onResponseError,
        );
    }
}
