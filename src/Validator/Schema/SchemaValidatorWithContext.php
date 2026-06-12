<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorMode;
use Duyler\OpenApi\Validator\ValidatorPool;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function count;
use function is_array;

final class SchemaValidatorWithContext
{
    private const int MAX_SCHEMA_DEPTH = 64;

    private readonly LoggerInterface $logger;
    private readonly ErrorFormatterInterface $errorFormatter;

    public function __construct(
        private readonly ValidatorPool $pool,
        private readonly RefResolverInterface $refResolver,
        private readonly OpenApiDocument $document,
        private readonly StatelessValidatorRegistry $statelessValidators,
        private readonly bool $nullableAsType = true,
        private readonly EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
        private readonly bool $reportDeprecated = false,
        ?LoggerInterface $logger = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        ?ErrorFormatterInterface $errorFormatter = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->errorFormatter = $errorFormatter ?? SimpleFormatter::shared();
    }

    public function validate(array|int|string|float|bool|null $data, Schema $schema, bool $useDiscriminator = true, ?ValidatorMode $mode = null): void
    {
        $context = ValidationContext::create($this->pool, $this->errorFormatter, $this->nullableAsType, $this->emptyArrayStrategy, $mode);

        $schema = $this->resolveRef($schema);

        if ($useDiscriminator && null !== $schema->discriminator && null !== $schema->oneOf) {
            $oneOfValidator = new OneOfValidatorWithContext($this->pool, $this->refResolver, $this->document, $this->statelessValidators, reportDeprecated: $this->reportDeprecated, logger: $this->logger, eventDispatcher: $this->eventDispatcher);
            $oneOfValidator->validateWithContext($data, $schema, $context, $useDiscriminator);
            return;
        }

        if ($useDiscriminator && null !== $schema->discriminator && null !== $data) {
            $this->validateInternal($data, $schema, $context);

            $discriminatorValidator = new DiscriminatorValidator($this->refResolver, $this->pool, $this->statelessValidators, reportDeprecated: $this->reportDeprecated, logger: $this->logger, eventDispatcher: $this->eventDispatcher);
            $discriminatorValidator->validate($data, $schema, $this->document);

            $this->validatePropertiesAndItems($data, $schema, $context, $useDiscriminator);
            return;
        }

        $this->validateInternal($data, $schema, $context);

        $this->validatePropertiesAndItems($data, $schema, $context, $useDiscriminator);
    }

    public function validateWithContext(array|int|string|float|bool|null $data, Schema $schema, ValidationContext $context, bool $useDiscriminator = true): void
    {
        $this->checkDepth($context);

        $context = $context->withIncrementedDepth();

        $schema = $this->resolveRef($schema);

        if ($useDiscriminator && null !== $schema->discriminator && null !== $schema->oneOf) {
            $oneOfValidator = new OneOfValidatorWithContext($this->pool, $this->refResolver, $this->document, $this->statelessValidators, reportDeprecated: $this->reportDeprecated, logger: $this->logger, eventDispatcher: $this->eventDispatcher);
            $oneOfValidator->validateWithContext($data, $schema, $context, $useDiscriminator);
            return;
        }

        if ($useDiscriminator && null !== $schema->discriminator && null !== $data) {
            $this->validateInternal($data, $schema, $context);

            $discriminatorValidator = new DiscriminatorValidator($this->refResolver, $this->pool, $this->statelessValidators, reportDeprecated: $this->reportDeprecated, logger: $this->logger, eventDispatcher: $this->eventDispatcher);
            $discriminatorValidator->validate($data, $schema, $this->document);

            $this->validatePropertiesAndItems($data, $schema, $context, $useDiscriminator);
            return;
        }

        $this->validateInternal($data, $schema, $context);

        $this->validatePropertiesAndItems($data, $schema, $context, $useDiscriminator);
    }

    private function checkDepth(ValidationContext $context): void
    {
        if ($context->depth >= self::MAX_SCHEMA_DEPTH) {
            throw new SchemaDepthExceededException(self::MAX_SCHEMA_DEPTH);
        }
    }

    private function validatePropertiesAndItems(
        array|int|string|float|bool|null $data,
        Schema $schema,
        ValidationContext $context,
        bool $useDiscriminator,
    ): void {
        if (null !== $schema->properties && [] !== $schema->properties && is_array($data)) {
            $propertiesValidator = new PropertiesValidatorWithContext($this->pool, $this->refResolver, $this->document, $this->statelessValidators, reportDeprecated: $this->reportDeprecated, logger: $this->logger, eventDispatcher: $this->eventDispatcher);
            $propertiesValidator->validateWithContext($data, $schema, $context, $useDiscriminator);
        }

        if (null !== $schema->items && is_array($data)) {
            $itemsValidator = new ItemsValidatorWithContext($this->pool, $this->refResolver, $this->document, $this->statelessValidators, reportDeprecated: $this->reportDeprecated, logger: $this->logger, eventDispatcher: $this->eventDispatcher);
            $itemsValidator->validateWithContext($data, $schema, $context, $useDiscriminator);
        }
    }

    private function resolveRef(Schema $schema): Schema
    {
        if (null === $schema->ref) {
            return $schema;
        }

        return $this->refResolver->resolveSchemaWithOverride($schema, $this->document);
    }

    private function validateInternal(array|int|string|float|bool|null $data, Schema $schema, ?ValidationContext $context = null): void
    {
        $errors = [];

        foreach ($this->statelessValidators->getValidators() as $validator) {
            try {
                $validator->validate($data, $schema, $context);
            } catch (AbstractValidationError $e) {
                $errors[] = $e;
            }
        }

        if (count($errors) > 0) {
            throw new ValidationException(
                'Schema validation failed',
                errors: $errors,
            );
        }
    }
}
