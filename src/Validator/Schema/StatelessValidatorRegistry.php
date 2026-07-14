<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorFactory;
use Duyler\OpenApi\Validator\ValidatorPool;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class StatelessValidatorRegistry
{
    /** @var list<SchemaValidatorInterface> */
    private readonly array $validators;

    public function __construct(
        ValidatorPool $pool,
        FormatRegistry $formatRegistry,
        bool $reportDeprecated = false,
        LoggerInterface $logger = new NullLogger(),
        ?EventDispatcherInterface $eventDispatcher = null,
        RegexValidator $regexValidator = new RegexValidator(),
    ) {
        $this->validators = new ValidatorFactory(
            $pool,
            $formatRegistry,
            logger: $logger,
            reportDeprecated: $reportDeprecated,
            eventDispatcher: $eventDispatcher,
            regexValidator: $regexValidator,
        )->createStatelessList();
    }

    /**
     * @return list<SchemaValidatorInterface>
     */
    public function getValidators(): array
    {
        return $this->validators;
    }
}
