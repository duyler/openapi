<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Event\ValidationWarningEvent;
use Duyler\OpenApi\Validator\Dto\CoercionContext;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Example\ExampleValidator;
use Duyler\OpenApi\Validator\Request\RequestBodyCoercer;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\TypeGuarantor;
use Duyler\OpenApi\Validator\ValidatorMode;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\StreamInterface;

use function sprintf;

/** @internal */
final readonly class StreamingMultipartValidator
{
    private RequestBodyCoercer $coercer;

    private ExampleValidator $exampleValidator;

    public function __construct(
        private readonly MultipartEncodingApplier $encodingApplier,
        private readonly MultipartInstanceResolver $instanceResolver,
        private readonly OrderedMultipartPlanner $orderedPlanner,
        private readonly SchemaValidatorWithContext $schemaValidator,
        private readonly ValidatorConfiguration $configuration,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->coercer = new RequestBodyCoercer();
        $this->exampleValidator = new ExampleValidator();
    }

    public function validate(
        StreamInterface $stream,
        string $contentType,
        MediaType $mediaType,
        ?Schema $schema,
        bool $skipContentMediaType,
    ): int {
        if (null !== $schema) {
            $this->orderedPlanner->assertConfiguration($mediaType, $schema);
        }

        $splitter = new MultipartStreamSplitter(
            fullContentType: $contentType,
            maxPartBytes: $this->configuration->maxMultipartBodyBytes,
        );

        $parts = $splitter->parts($stream);

        foreach ($parts as $index => $part) {
            $partSchema = $this->partSchema($index, $mediaType, $schema);
            $value = null === $partSchema ? null : $this->instanceResolver->positionalValue(
                part: $part,
                index: $index,
                schema: $schema,
                prefixEncoding: $mediaType->prefixEncoding,
                itemEncoding: $mediaType->itemEncoding,
                partSchema: $partSchema,
            );

            $this->encodingApplier->applyToParts(
                parts: [$index => $part],
                prefixEncoding: $mediaType->prefixEncoding,
                itemEncoding: $mediaType->itemEncoding,
                encoding: $mediaType->encoding,
            );

            if (null === $partSchema) {
                continue;
            }

            $value = $this->coercePart($value, $partSchema);

            $this->schemaValidator->validate(
                $value,
                $partSchema,
                ValidatorMode::Request,
                skipContentMediaType: $skipContentMediaType,
            );

            $this->dispatchPartExampleWarnings($value, $partSchema);
        }

        return $parts->getReturn();
    }

    private function coercePart(string|bool|int|float|array|null $value, Schema $partSchema): string|bool|int|float|array|null
    {
        if (false === $this->configuration->coercion) {
            return $value;
        }

        $coerced = $this->coercer->coerce(
            $value,
            new CoercionContext(
                schema: $partSchema,
                strict: $this->configuration->strictCoercion,
                nullableAsType: $this->configuration->nullableAsType,
            ),
        );

        return TypeGuarantor::ensureValidType($coerced);
    }

    private function dispatchPartExampleWarnings(mixed $value, Schema $partSchema): void
    {
        if (null === $this->eventDispatcher) {
            return;
        }

        foreach ($this->exampleValidator->validate($value, $partSchema) as $warning) {
            $this->eventDispatcher->dispatch(
                new ValidationWarningEvent(
                    message: sprintf('%s (data: %s)', $warning->message, $warning->data),
                ),
            );
        }
    }

    private function partSchema(int $index, MediaType $mediaType, ?Schema $schema): ?Schema
    {
        $prefixItem = $schema?->prefixItems[$index] ?? null;

        if (null !== $prefixItem) {
            return $prefixItem;
        }

        if (null !== $mediaType->itemSchema) {
            return $mediaType->itemSchema;
        }

        $items = $schema?->items ?? null;

        return $items instanceof Schema ? $items : null;
    }
}
