<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\ValidatorPool;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * @psalm-import-type StatelessValidatorList from SchemaValidatorDependencies
 */
final readonly class StatelessValidatorRegistry
{
    /** @var StatelessValidatorList */
    private readonly array $validators;

    public function __construct(
        ValidatorPool $pool,
        FormatRegistry $formatRegistry,
        bool $strictFormats = false,
        bool $reportDeprecated = false,
        LoggerInterface $logger = new NullLogger(),
        ?EventDispatcherInterface $eventDispatcher = null,
        RegexValidator $regexValidator = new RegexValidator(),
        PregExecutor $pregExecutor = new PregExecutor(),
        ?OpenApiDocument $document = null,
        ?RefResolverInterface $refResolver = null,
    ) {
        $dependencies = new SchemaValidatorDependencies(
            pool: $pool,
            refResolver: $refResolver ?? new RefResolver(),
            statelessValidators: $this,
            logger: $logger,
            eventDispatcher: $eventDispatcher,
            formatRegistry: $formatRegistry,
            strictFormats: $strictFormats,
            reportDeprecated: $reportDeprecated,
            regexValidator: $regexValidator,
            pregExecutor: $pregExecutor,
            document: null === $refResolver ? null : $document,
        );

        $this->validators = $dependencies->createStatelessList();
    }

    /**
     * @return StatelessValidatorList
     */
    public function getValidators(): array
    {
        return $this->validators;
    }
}
