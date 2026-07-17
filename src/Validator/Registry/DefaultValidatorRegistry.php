<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Registry;

use Duyler\OpenApi\Validator\Exception\UnknownValidatorException;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorFactory;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;

use function array_key_exists;

/**
 * @internal Legacy registry used only by {@see SchemaValidator} (the stateless recursion dispatcher).
 *           The canonical validator {@see SchemaValidatorWithContext}
 *           uses {@see StatelessValidatorRegistry} instead.
 */
final readonly class DefaultValidatorRegistry implements ValidatorRegistryInterface
{
    /** @var array<string, SchemaValidatorInterface> */
    private readonly array $validators;

    public function __construct(
        private readonly ValidatorPool $pool,
        public readonly FormatRegistry $formatRegistry,
        private readonly bool $strictFormats = false,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly bool $reportDeprecated = false,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly RegexValidator $regexValidator = new RegexValidator(),
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {
        $dependencies = new ValidatorDependencies(
            pool: $this->pool,
            formatRegistry: $this->formatRegistry,
            strictFormats: $this->strictFormats,
            logger: $this->logger,
            reportDeprecated: $this->reportDeprecated,
            eventDispatcher: $this->eventDispatcher,
            registry: $this,
            regexValidator: $this->regexValidator,
            pregExecutor: $this->pregExecutor,
        );

        $this->validators = new ValidatorFactory($dependencies)->createAll();
    }

    #[Override]
    public function getValidator(string $type): SchemaValidatorInterface
    {
        if (false === array_key_exists($type, $this->validators)) {
            throw new UnknownValidatorException($type);
        }

        return $this->validators[$type];
    }

    #[Override]
    public function getAllValidators(): iterable
    {
        return $this->validators;
    }
}
