<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation;

use Duyler\OpenApi\Event\ValidationErrorEvent;
use Duyler\OpenApi\Event\ValidationFinishedEvent;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Duyler\OpenApi\Validator\EventDispatchingTrait;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

use function sprintf;

final readonly class SchemaValidatorAdapter
{
    use EventDispatchingTrait;

    private readonly ?EventDispatcherInterface $eventDispatcher;
    private readonly LoggerInterface $logger;
    private readonly RefResolver $refResolver;
    private readonly SchemaValidator $schemaValidator;

    public function __construct(
        private readonly ValidationContext $context,
    ) {
        $this->eventDispatcher = $context->eventDispatcher;
        $this->logger = $context->logger;
        $this->refResolver = $context->refResolver;
        $this->schemaValidator = new SchemaValidator(
            $context->pool,
            $context->formatRegistry,
            strictFormats: $context->strictFormats,
            logger: $context->logger,
            reportDeprecated: $context->reportDeprecated,
            eventDispatcher: $context->eventDispatcher,
        );
    }

    public function validate(mixed $data, string $schemaRef): void
    {
        $this->withValidationEvents(
            startedEvent: new ValidationStartedEvent(path: $schemaRef, method: 'SCHEMA', schemaRef: $schemaRef),
            makeFinishedEvent: fn(bool $success, float $duration): ValidationFinishedEvent => new ValidationFinishedEvent(
                path: $schemaRef,
                method: 'SCHEMA',
                success: $success,
                duration: $duration,
                schemaRef: $schemaRef,
            ),
            makeErrorEvent: fn(ValidationException $e): ValidationErrorEvent => new ValidationErrorEvent(
                path: $schemaRef,
                method: 'SCHEMA',
                exception: $e,
                schemaRef: $schemaRef,
            ),
            callback: function () use ($data, $schemaRef): void {
                $this->logger->info(sprintf('Resolving schema ref: %s', $schemaRef));

                $schema = $this->refResolver->resolve($schemaRef, $this->context->document);

                $this->logger->info(sprintf('Validating schema: %s', $schemaRef));

                /** @var array<array-key, mixed>|array-key $data */
                $this->schemaValidator->validate($data, $schema);
            },
            warningMessage: sprintf('Schema validation failed: %s', $schemaRef),
        );
    }
}
