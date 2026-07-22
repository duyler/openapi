<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Registry\DefaultValidatorRegistry;
use Duyler\OpenApi\Validator\Registry\ValidatorRegistryInterface;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use WeakMap;

use function array_filter;
use function array_values;

/**
 * @internal Legacy stateless JSON Schema dispatcher. Retained only as the recursion engine
 *           invoked by {@see AbstractSchemaValidator::createSchemaValidator()} and by parameter
 *           validators (Path/Query/Headers/Cookie) pending task 14b migration. The canonical
 *           top-level validator is {@see SchemaValidatorWithContext}.
 *
 *           Annotation tracking (R3-SPEC-001..004): when this dispatcher is
 *           used as the recursion engine inside composition validators
 *           ({@see AllOfValidator}, {@see AnyOfValidator}, {@see NotValidator},
 *           {@see IfThenElseValidator}, {@see ContainsValidator}) it forwards
 *           the supplied {@see ValidationContext} to nested stateless
 *           validators so annotation-state propagation works correctly
 *           across nested allOf/anyOf/if-then-else/$ref. When invoked
 *           directly as a top-level validator (without an externally
 *           supplied context) it creates a fresh context per validate()
 *           call, so annotation tracking still works for direct callers.
 *           The context-aware path through {@see SchemaValidatorWithContext}
 *           remains the canonical entry point for request/response
 *           validation because it also routes discriminator oneOf and
 *           $ref-pre-resolved composition that this legacy dispatcher
 *           cannot handle.
 *
 * @deprecated since 0.6.0: this dispatcher does not resolve `$ref` on its
 *             own. When constructed with `document` + `refResolver`, it is
 *             transparently wrapped by {@see RefResolvingSchemaValidator}
 *             so nested-keyword `$ref` resolution still works, but direct
 *             callers should migrate to {@see SchemaValidatorWithContext}
 *             (returned by `OpenApiValidatorBuilder::build()` and by
 *             `Dto\SchemaValidatorDependencies::rootSchemaValidator()`).
 *             This class will be removed in 2.0 alongside the
 *             parameter-validator migration.
 */
final class SchemaValidator implements SchemaValidatorInterface
{
    private readonly ValidatorRegistryInterface $effectiveRegistry;

    /** @var WeakMap<Schema, list<SchemaValidatorInterface>> */
    private WeakMap $applicableValidators;

    public function __construct(
        private readonly ValidatorPool $pool,
        public readonly FormatRegistry $formatRegistry,
        ?ValidatorRegistryInterface $registry = null,
        private readonly bool $strictFormats = false,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly bool $reportDeprecated = false,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly RegexValidator $regexValidator = new RegexValidator(),
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
        ?OpenApiDocument $document = null,
        ?RefResolverInterface $refResolver = null,
    ) {
        $this->effectiveRegistry = $registry ?? new DefaultValidatorRegistry(
            $this->pool,
            $this->formatRegistry,
            $this->strictFormats,
            $this->logger,
            $this->reportDeprecated,
            $this->eventDispatcher,
            regexValidator: $this->regexValidator,
            pregExecutor: $this->pregExecutor,
            document: $document,
            refResolver: $refResolver,
        );

        /** @var WeakMap<Schema, list<SchemaValidatorInterface>> $applicableValidators */
        $applicableValidators = new WeakMap();
        $this->applicableValidators = $applicableValidators;
    }

    #[Override]
    public function validate(array|int|string|float|bool|null $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $context) {
            $context = ValidationContext::create(pool: $this->pool);
        }

        $context->incrementDepth();

        try {
            if (isset($this->applicableValidators[$schema])) {
                /** @var list<SchemaValidatorInterface> $validators */
                $validators = $this->applicableValidators[$schema];
            } else {
                $validators = $this->computeApplicableValidators($schema);
                $this->applicableValidators[$schema] = $validators;
            }

            foreach ($validators as $validator) {
                $validator->validate($data, $schema, $context);
            }
        } finally {
            $context->decrementDepth();
        }
    }

    /**
     * @return list<SchemaValidatorInterface>
     */
    private function computeApplicableValidators(Schema $schema): array
    {
        $all = $this->effectiveRegistry->getAllValidators();

        /** @var list<SchemaValidatorInterface> $filtered */
        $filtered = array_values(array_filter(
            $all,
            static function (SchemaValidatorInterface $v) use ($schema): bool {
                return !$v instanceof KeywordApplicable || $v->isApplicable($schema);
            },
        ));

        return $filtered;
    }
}
