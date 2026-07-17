<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Registry\ValidatorRegistryInterface;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

use function implode;
use function is_array;

abstract readonly class AbstractSchemaValidator implements SchemaValidatorInterface
{
    public function __construct(
        protected readonly ValidatorDependencies $dependencies,
    ) {}

    protected function getDataPath(?ValidationContext $context): string
    {
        if (null === $context) {
            return '/';
        }

        return $context->breadcrumbs->currentPath();
    }

    /**
     * Normalise the OpenAPI `type` value to a single string for inclusion in
     * `TypeMismatchError::expected`. Union types are joined with `|` to mirror
     * the convention already used by TypeValidator.
     *
     * @param string|list<string>|null $type
     */
    protected function formatSchemaType(array|string|null $type, string $default = 'scalar'): string
    {
        if (null === $type) {
            return $default;
        }

        if (is_array($type)) {
            return implode('|', $type);
        }

        return $type;
    }

    protected function pool(): ValidatorPool
    {
        return $this->dependencies->pool;
    }

    protected function formatRegistry(): FormatRegistry
    {
        return $this->dependencies->formatRegistry;
    }

    protected function strictFormats(): bool
    {
        return $this->dependencies->strictFormats;
    }

    protected function logger(): LoggerInterface
    {
        return $this->dependencies->logger;
    }

    protected function reportDeprecated(): bool
    {
        return $this->dependencies->reportDeprecated;
    }

    protected function eventDispatcher(): ?EventDispatcherInterface
    {
        return $this->dependencies->eventDispatcher;
    }

    protected function registry(): ?ValidatorRegistryInterface
    {
        return $this->dependencies->registry;
    }

    protected function regexValidator(): RegexValidator
    {
        return $this->dependencies->regexValidator;
    }

    protected function pregExecutor(): PregExecutor
    {
        return $this->dependencies->pregExecutor;
    }

    protected function createSchemaValidator(): SchemaValidator
    {
        return $this->dependencies->rootSchemaValidator();
    }
}
