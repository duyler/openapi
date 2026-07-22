<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Registry\ValidatorRegistryInterface;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;

/**
 * @internal Legacy constructor-dependency bag for the stateless dispatcher
 *           ({@see SchemaValidator}) and its keyword validators. The
 *           canonical dependency bag is
 *           {@see SchemaValidatorDependencies},
 *           which carries the document + RefResolver needed for full
 *           `$ref` resolution; this legacy bag only gained optional
 *           `document` / `refResolver` fields in 0.6.0 so that the
 *           {@see RefResolvingSchemaValidator} adapter can wrap the
 *           legacy dispatcher without touching every nested-keyword
 *           validator constructor.
 *
 * @deprecated since 0.6.0: direct construction of this bag is retained
 *             only for the parameter-validator migration window. New
 *             code should use
 *             {@see SchemaValidatorDependencies}
 *             (which exposes the shared `RefResolver`, `StatelessValidatorRegistry`,
 *             and PSR-3 / PSR-14 wiring). This class will be removed in 2.0
 *             alongside {@see SchemaValidator}.
 */
final class ValidatorDependencies
{
    private ?SchemaValidatorInterface $rootSchemaValidator = null;

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
        public readonly ?OpenApiDocument $document = null,
        public readonly ?RefResolverInterface $refResolver = null,
    ) {}

    public function rootSchemaValidator(): SchemaValidatorInterface
    {
        if (null !== $this->rootSchemaValidator) {
            return $this->rootSchemaValidator;
        }

        $inner = new SchemaValidator(
            pool: $this->pool,
            formatRegistry: $this->formatRegistry,
            registry: $this->registry,
            strictFormats: $this->strictFormats,
            logger: $this->logger,
            reportDeprecated: $this->reportDeprecated,
            eventDispatcher: $this->eventDispatcher,
            regexValidator: $this->regexValidator,
            pregExecutor: $this->pregExecutor,
            document: $this->document,
            refResolver: $this->refResolver,
        );

        if (null !== $this->document && null !== $this->refResolver) {
            $inner = new RefResolvingSchemaValidator(
                inner: $inner,
                refResolver: $this->refResolver,
                document: $this->document,
            );
        }

        $this->rootSchemaValidator = $inner;

        return $inner;
    }
}
