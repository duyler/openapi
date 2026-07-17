<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\EventDispatchingTrait;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\TypeFormatter;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

use function is_array;
use function is_scalar;
use function sprintf;

final readonly class SchemaValidatorAdapter
{
    use EventDispatchingTrait;

    private readonly ?EventDispatcherInterface $eventDispatcher;
    private readonly LoggerInterface $logger;
    private readonly RefResolver $refResolver;

    public function __construct(
        private readonly ValidatorDependencies $context,
    ) {
        $this->eventDispatcher = $context->eventDispatcher;
        $this->logger = $context->logger;
        $this->refResolver = $context->refResolver;
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

                $schema = $this->refResolver->resolve($schemaRef, $this->context->document);

                $this->logger->info(sprintf('Validating schema: %s', $schemaRef));

                $this->validateAgainstSchema($data, $schema);
            },
            warningMessage: sprintf('Schema validation failed: %s', $schemaRef),
            schemaRef: $schemaRef,
        );
    }

    private function validateAgainstSchema(mixed $data, Schema $schema): void
    {
        if (null !== $data && false === is_scalar($data) && false === is_array($data)) {
            throw new InvalidDataTypeException(
                sprintf('Data must be scalar, array, or null, got %s', TypeFormatter::format($data)),
            );
        }

        /** @var array<int|string, mixed>|int|string|float|bool|null $data */
        $this->context->schemaValidatorWithContext->validate($data, $schema);
    }
}
