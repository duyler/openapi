<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Event\ValidationWarningEvent;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidContentMediaTypeException;
use Override;

use function is_string;
use function sprintf;

final readonly class ContentMediaTypeValidator implements KeywordApplicable
{
    private readonly ContentAssertionPipeline $pipeline;

    public function __construct(
        private readonly SchemaValidatorDependencies $dependencies,
    ) {
        $this->pipeline = new ContentAssertionPipeline($dependencies);
    }

    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->contentMediaType;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->contentMediaType || false === is_string($data)) {
            return;
        }

        if (null !== $context) {
            $context->annotate('contentMediaType', $schema->contentMediaType, $context->path());
        }

        if (false === $this->dependencies->contentAssertionsEnabled) {
            if (false === $this->pipeline->isRecognizedMediaType($schema->contentMediaType)) {
                $this->warnUnknownMediaType($schema->contentMediaType, $schema, $context);
            }

            if (null !== $context) {
                $context->attachContentResult($this->pipeline->defer($data, $schema, $context));
            }

            return;
        }

        $context ??= ValidationContext::fromInit(new ValidationContextInit(pool: $this->dependencies->pool));

        $result = $this->pipeline->assert($data, $schema, $context);

        if (false === $result->isMediaTypeValid()) {
            throw new InvalidContentMediaTypeException($data, $schema->contentMediaType);
        }

        $context->attachContentResult($result);
    }

    private function warnUnknownMediaType(string $mediaType, Schema $schema, ?ValidationContext $context): void
    {
        $propertyPath = null === $context ? '/' : $context->path();

        $this->dependencies->eventDispatcher?->dispatch(
            new ValidationWarningEvent(
                propertyPath: $propertyPath,
                propertyName: 'contentMediaType',
                message: sprintf('Unrecognized contentMediaType "%s" is collected as an annotation', $mediaType),
                schemaRef: $schema->ref,
            ),
        );

        $this->dependencies->logger->warning(
            sprintf('Unrecognized contentMediaType "%s" at path "%s"', $mediaType, $propertyPath),
        );
    }
}
