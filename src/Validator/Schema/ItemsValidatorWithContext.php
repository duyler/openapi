<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\DiscriminatorMismatchException;
use Duyler\OpenApi\Validator\Exception\InvalidDiscriminatorValueException;
use Duyler\OpenApi\Validator\Exception\MissingDiscriminatorPropertyException;
use Duyler\OpenApi\Validator\Exception\UnknownDiscriminatorValueException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function count;
use function sprintf;

final readonly class ItemsValidatorWithContext
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly ValidatorPool $pool,
        private readonly RefResolverInterface $refResolver,
        private readonly OpenApiDocument $document,
        private readonly StatelessValidatorRegistry $statelessValidators,
        private readonly bool $reportDeprecated = false,
        ?LoggerInterface $logger = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function validateWithContext(array $data, Schema $schema, ValidationContext $context, bool $useDiscriminator = true): void
    {
        if (null === $schema->items) {
            return;
        }

        $errors = [];
        $itemSchema = $schema->items;
        $prefixCount = null !== $schema->prefixItems ? count($schema->prefixItems) : 0;

        foreach ($data as $index => $item) {
            /** @var int $index */
            if ($index < $prefixCount) {
                continue;
            }

            try {
                $itemContext = $context->withBreadcrumbIndex($index);

                $allowNull = $itemSchema->nullable && $context->nullableAsType;
                $normalizedItem = SchemaValueNormalizer::normalize($item, $allowNull);
                $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document, $this->statelessValidators, reportDeprecated: $this->reportDeprecated, logger: $this->logger, eventDispatcher: $this->eventDispatcher);
                $validator->validateWithContext($normalizedItem, $itemSchema, $itemContext, $useDiscriminator);
            } catch (DiscriminatorMismatchException|
                InvalidDiscriminatorValueException|
                MissingDiscriminatorPropertyException|
                UnknownDiscriminatorValueException $e
            ) {
                throw $e;
            } catch (AbstractValidationError $e) {
                $errors[] = $e;
            }
        }

        if (count($errors) > 0) {
            throw new ValidationException(
                sprintf('Items validation failed at %s', $context->breadcrumbs->currentPath()),
                errors: $errors,
            );
        }
    }
}
