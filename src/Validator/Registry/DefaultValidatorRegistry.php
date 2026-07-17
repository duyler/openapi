<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Registry;

use Duyler\OpenApi\Validator\Exception\UnknownValidatorException;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorFactory;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function array_key_exists;

final readonly class DefaultValidatorRegistry implements ValidatorRegistryInterface
{
    /** @var array<class-string<SchemaValidatorInterface>, SchemaValidatorInterface> */
    private readonly array $validators;

    public function __construct(
        private readonly ValidatorPool $pool,
        public readonly FormatRegistry $formatRegistry,
        private readonly bool $strictFormats = false,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly bool $reportDeprecated = false,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly RegexValidator $regexValidator = new RegexValidator(),
    ) {
        $this->validators = new ValidatorFactory(
            $this->pool,
            $this->formatRegistry,
            $this->strictFormats,
            $this->logger,
            $this->reportDeprecated,
            $this->eventDispatcher,
            $this,
            $this->regexValidator,
        )->createAll();
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
