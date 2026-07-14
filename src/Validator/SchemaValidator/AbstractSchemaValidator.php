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

use function implode;
use function is_array;

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
