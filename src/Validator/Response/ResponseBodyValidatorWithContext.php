<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\CoercionContext;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Example\ExampleValidator;
use Duyler\OpenApi\Validator\Example\ValidatesExamplesTrait;
use Duyler\OpenApi\Validator\Request\ContentTypeNegotiator;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\TypeGuarantor;
use Duyler\OpenApi\Validator\ValidatorMode;
use Psr\Http\Message\StreamInterface;

final readonly class ResponseBodyValidatorWithContext
{
    use ValidatesExamplesTrait;

    private SchemaValidator $regularSchemaValidator;
    private SchemaValidatorWithContext $contextSchemaValidator;
    private readonly ContentTypeNegotiator $negotiator;
    private readonly ResponseTypeCoercer $typeCoercer;
    private readonly StreamingContentParser $streamingParser;
    private readonly ExampleValidator $exampleValidator;

    public function __construct(
        private readonly OpenApiDocument $document,
        private readonly SchemaValidatorDependencies $dependencies,
        private readonly ValidatorConfiguration $configuration = new ValidatorConfiguration(),
    ) {
        $this->negotiator = new ContentTypeNegotiator();
        $this->typeCoercer = new ResponseTypeCoercer();
        $this->streamingParser = new StreamingContentParser($this->dependencies->logger);
        $this->exampleValidator = new ExampleValidator();
        $effectiveFormatRegistry = $this->dependencies->formatRegistry;

        $this->regularSchemaValidator = new SchemaValidator(
            $this->dependencies->pool,
            $effectiveFormatRegistry,
            reportDeprecated: $this->configuration->reportDeprecated,
            logger: $this->dependencies->logger,
            eventDispatcher: $this->dependencies->eventDispatcher,
        );

        $this->contextSchemaValidator = new SchemaValidatorWithContext(
            $this->document,
            $this->dependencies,
            $this->configuration,
        );
    }

    public function validate(
        string $body,
        string $contentType,
        ?Content $content,
    ): void {
        if (null === $content) {
            return;
        }

        $mediaType = $this->negotiator->getMediaType($contentType);
        $mediaTypeSchema = $content->mediaTypes[$mediaType] ?? null;

        if (null === $mediaTypeSchema) {
            return;
        }

        if (StreamingMediaTypeDetector::isStreaming($contentType)) {
            $this->validateStreamingContent($body, $mediaTypeSchema, $contentType);

            return;
        }

        $this->validateRegularContent($body, $mediaTypeSchema, $mediaType);
    }

    public function validateStream(
        StreamInterface $stream,
        string $contentType,
        ?Content $content,
    ): void {
        if (null === $content) {
            return;
        }

        $mediaType = $this->negotiator->getMediaType($contentType);
        $mediaTypeSchema = $content->mediaTypes[$mediaType] ?? null;

        if (null === $mediaTypeSchema) {
            return;
        }

        $schema = $mediaTypeSchema->itemSchema ?? $mediaTypeSchema->schema;

        if (null === $schema) {
            return;
        }

        $effectiveContentType = $mediaTypeSchema->itemEncoding->contentType ?? $contentType;
        $items = $this->streamingParser->parseStream($stream, $effectiveContentType);

        foreach ($items as $item) {
            if (null === $item) {
                continue;
            }

            $this->validateAgainstSchema($item, $schema);
        }
    }

    private function validateRegularContent(
        string $body,
        MediaType $mediaTypeSchema,
        string $mediaType,
    ): void {
        /** @var array|int|string|float|bool|null $parsedBody */
        $parsedBody = $this->dependencies->bodyParser->parse($body, $mediaType);

        if ($this->configuration->coercion && null !== $mediaTypeSchema->schema) {
            $coercionContext = new CoercionContext(
                schema: $mediaTypeSchema->schema,
                enabled: true,
                strict: true,
                nullableAsType: $this->configuration->nullableAsType,
            );
            $parsedBody = $this->typeCoercer->coerce($parsedBody, $coercionContext);
            $parsedBody = TypeGuarantor::ensureValidType($parsedBody, $this->configuration->nullableAsType);
        }

        if (null !== $mediaTypeSchema->schema) {
            $this->validateAgainstSchema($parsedBody, $mediaTypeSchema->schema);
        }

        $this->dispatchExampleWarnings(
            $parsedBody,
            $mediaTypeSchema,
            $this->exampleValidator,
            $this->dependencies->eventDispatcher,
        );
    }

    private function validateStreamingContent(
        string $body,
        MediaType $mediaType,
        string $contentType,
    ): void {
        $schema = $mediaType->itemSchema ?? $mediaType->schema;

        if (null === $schema) {
            return;
        }

        $effectiveContentType = $mediaType->itemEncoding->contentType ?? $contentType;
        $items = $this->streamingParser->parse($body, $effectiveContentType);

        foreach ($items as $item) {
            if (null === $item) {
                continue;
            }

            $this->validateAgainstSchema($item, $schema);
        }
    }

    /**
     * Validate data against a schema, choosing the appropriate validator
     * based on discriminator or $ref presence.
     *
     * @param array<int|string, mixed>|int|string|float|bool|null $data
     */
    private function validateAgainstSchema(mixed $data, Schema $schema): void
    {
        $context = ValidationContext::create(
            $this->dependencies->pool,
            $this->dependencies->errorFormatter,
            $this->configuration->nullableAsType,
            $this->configuration->emptyArrayStrategy,
            ValidatorMode::Response,
        );

        $hasDiscriminator = null !== $schema->discriminator
            || $this->dependencies->refResolver->schemaHasDiscriminator($schema, $this->document);
        $hasRef = $this->dependencies->refResolver->schemaHasRef($schema);

        if (false === ($hasDiscriminator || $hasRef)) {
            $this->regularSchemaValidator->validate($data, $schema, $context);

            return;
        }

        $this->contextSchemaValidator->validate($data, $schema, ValidatorMode::Response);
    }
}
