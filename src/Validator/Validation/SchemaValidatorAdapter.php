<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\ValidationContext as ErrorValidationContext;
use Duyler\OpenApi\Validator\EventDispatchingTrait;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

use function gettype;
use function is_array;
use function is_scalar;
use function sprintf;

final readonly class SchemaValidatorAdapter
{
    use EventDispatchingTrait;

    private readonly ?EventDispatcherInterface $eventDispatcher;
    private readonly LoggerInterface $logger;
    private readonly RefResolver $refResolver;
    private readonly OpenApiDocument $document;
    private readonly SchemaValidator $schemaValidator;
    private readonly SchemaValidatorWithContext $contextSchemaValidator;

    public function __construct(
        private readonly ValidationContext $context,
    ) {
        $this->eventDispatcher = $context->eventDispatcher;
        $this->logger = $context->logger;
        $this->refResolver = $context->refResolver;
        $this->document = $context->document;
        $this->schemaValidator = new SchemaValidator(
            $context->pool,
            $context->formatRegistry,
            strictFormats: $context->strictFormats,
            logger: $context->logger,
            reportDeprecated: $context->reportDeprecated,
            eventDispatcher: $context->eventDispatcher,
        );
        $this->contextSchemaValidator = $context->schemaValidatorWithContext;
    }

    public function validate(mixed $data, string $schemaRef): void
    {
        $this->withValidationEvents(
            request: null,
            response: null,
            path: $schemaRef,
            method: 'SCHEMA',
            callback: function () use ($data, $schemaRef): void {
                $this->logger->info(sprintf('Resolving schema ref: %s', $schemaRef));

                $schema = $this->refResolver->resolve($schemaRef, $this->document);

                $this->logger->info(sprintf('Validating schema: %s', $schemaRef));

                $this->validateAgainstSchema($data, $schema);
            },
            warningMessage: sprintf('Schema validation failed: %s', $schemaRef),
            schemaRef: $schemaRef,
        );
    }

    private function validateAgainstSchema(mixed $data, Schema $schema): void
    {
        $hasDiscriminator = null !== $schema->discriminator
            || $this->refResolver->schemaHasDiscriminator($schema, $this->document);
        $hasRef = $this->refResolver->schemaHasRef($schema);

        if (false === ($hasDiscriminator || $hasRef)) {
            $context = ErrorValidationContext::create(
                pool: $this->context->pool,
                errorFormatter: $this->context->errorFormatter,
                nullableAsType: $this->context->nullableAsType,
                emptyArrayStrategy: $this->context->emptyArrayStrategy,
            );

            /** @var array<array-key, mixed>|array-key $data */
            $this->schemaValidator->validate($data, $schema, $context);

            return;
        }

        if (null !== $data && false === is_scalar($data) && false === is_array($data)) {
            throw new InvalidDataTypeException(
                sprintf('Data must be scalar, array, or null, got %s', gettype($data)),
            );
        }

        /** @var array<int|string, mixed>|int|string|float|bool|null $data */
        $this->contextSchemaValidator->validate($data, $schema);
    }
}
