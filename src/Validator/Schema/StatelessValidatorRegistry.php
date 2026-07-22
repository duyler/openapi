<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
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
        bool $strictFormats = false,
        bool $reportDeprecated = false,
        LoggerInterface $logger = new NullLogger(),
        ?EventDispatcherInterface $eventDispatcher = null,
        RegexValidator $regexValidator = new RegexValidator(),
        PregExecutor $pregExecutor = new PregExecutor(),
        ?OpenApiDocument $document = null,
        ?RefResolverInterface $refResolver = null,
    ) {
        $dependencies = new ValidatorDependencies(
            pool: $pool,
            formatRegistry: $formatRegistry,
            strictFormats: $strictFormats,
            logger: $logger,
            reportDeprecated: $reportDeprecated,
            eventDispatcher: $eventDispatcher,
            regexValidator: $regexValidator,
            pregExecutor: $pregExecutor,
            document: $document,
            refResolver: $refResolver,
        );

        $this->validators = new ValidatorFactory($dependencies)->createStatelessList();
    }

    /**
     * @return list<SchemaValidatorInterface>
     */
    public function getValidators(): array
    {
        return $this->validators;
    }
}
