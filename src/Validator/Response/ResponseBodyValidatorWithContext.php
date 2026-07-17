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
use Duyler\OpenApi\Validator\Example\ExampleValidator;
use Duyler\OpenApi\Validator\Example\ValidatesExamplesTrait;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Request\ContentTypeNegotiator;
use Duyler\OpenApi\Validator\Request\MediaTypeMatcher;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\TypeGuarantor;
use Duyler\OpenApi\Validator\ValidatorMode;
use Psr\Http\Message\StreamInterface;

use function array_keys;

final readonly class ResponseBodyValidatorWithContext
{
    use ValidatesExamplesTrait;

    private SchemaValidatorWithContext $contextSchemaValidator;
    private readonly ContentTypeNegotiator $negotiator;
    private readonly ResponseTypeCoercer $typeCoercer;
    private readonly StreamingContentParser $streamingParser;
    private readonly ExampleValidator $exampleValidator;

    public function __construct(
        private readonly OpenApiDocument $document,
        private readonly SchemaValidatorDependencies $dependencies,
        private readonly ValidatorConfiguration $configuration = new ValidatorConfiguration(),
        ?PregExecutor $pregExecutor = null,
    ) {
        $resolvedPregExecutor = $pregExecutor ?? new PregExecutor($this->configuration->maxRegexBacktracks);
        $this->negotiator = new ContentTypeNegotiator($resolvedPregExecutor);
        $this->typeCoercer = new ResponseTypeCoercer();
        $this->streamingParser = new StreamingContentParser(
            $this->dependencies->logger,
            strictStreaming: $this->configuration->strictStreaming,
        );
        $this->exampleValidator = new ExampleValidator();

        $this->contextSchemaValidator = $this->dependencies->rootSchemaValidator(
            $this->document,
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
        $mediaTypeKey = MediaTypeMatcher::findMatch($mediaType, array_keys($content->mediaTypes));
        $mediaTypeSchema = null === $mediaTypeKey ? null : $content->mediaTypes[$mediaTypeKey];

        if (null === $mediaTypeSchema) {
            return;
        }

        if (StreamingMediaTypeDetector::isStreaming($contentType)) {
            $this->validateStreamingContent($body, $mediaTypeSchema, $contentType);

            return;
        }

        $this->validateRegularContent($body, $mediaTypeSchema, $mediaType, $contentType);
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
        $mediaTypeKey = MediaTypeMatcher::findMatch($mediaType, array_keys($content->mediaTypes));
        $mediaTypeSchema = null === $mediaTypeKey ? null : $content->mediaTypes[$mediaTypeKey];

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
        string $contentType,
    ): void {
        /** @var array|int|string|float|bool|null $parsedBody */
        $parsedBody = $this->dependencies->bodyParser->parse($body, $mediaType, $contentType);

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
     * @param array<int|string, mixed>|int|string|float|bool|null $data
     */
    private function validateAgainstSchema(mixed $data, Schema $schema): void
    {
        $this->contextSchemaValidator->validate($data, $schema, ValidatorMode::Response);
    }
}
