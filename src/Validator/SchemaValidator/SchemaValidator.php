<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Registry\DefaultValidatorRegistry;
use Duyler\OpenApi\Validator\Registry\ValidatorRegistryInterface;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
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
