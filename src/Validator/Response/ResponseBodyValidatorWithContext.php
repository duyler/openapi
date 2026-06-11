<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Request\BodyParser\BodyParser;
use Duyler\OpenApi\Validator\Request\ContentTypeNegotiator;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\TypeGuarantor;
use Duyler\OpenApi\Validator\ValidatorMode;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class ResponseBodyValidatorWithContext
{
    private SchemaValidator $regularSchemaValidator;
    private SchemaValidatorWithContext $contextSchemaValidator;
    private RefResolver $refResolver;
    private readonly FormatRegistry $formatRegistry;
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly ValidatorPool $pool,
        private readonly OpenApiDocument $document,
        private readonly BodyParser $bodyParser,
        StatelessValidatorRegistry $statelessValidators,
        ?FormatRegistry $formatRegistry = null,
        private readonly ContentTypeNegotiator $negotiator = new ContentTypeNegotiator(),
        private readonly ResponseTypeCoercer $typeCoercer = new ResponseTypeCoercer(),
        private readonly StreamingContentParser $streamingParser = new StreamingContentParser(),
        private readonly bool $coercion = false,
        private readonly bool $nullableAsType = true,
        private readonly EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
        private readonly bool $reportDeprecated = false,
        ?LoggerInterface $logger = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->formatRegistry = $formatRegistry ?? BuiltinFormats::instance();
        $this->logger = $logger ?? new NullLogger();
        $this->regularSchemaValidator = new SchemaValidator($this->pool, $this->formatRegistry, reportDeprecated: $this->reportDeprecated, logger: $this->logger, eventDispatcher: $this->eventDispatcher);

        $this->refResolver = new RefResolver();
        $this->contextSchemaValidator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document, $statelessValidators, $this->nullableAsType, $this->emptyArrayStrategy, reportDeprecated: $this->reportDeprecated, logger: $this->logger, eventDispatcher: $this->eventDispatcher);
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

    private function validateRegularContent(
        string $body,
        MediaType $mediaTypeSchema,
        string $mediaType,
    ): void {
        /** @var array|int|string|float|bool|null $parsedBody */
        $parsedBody = $this->bodyParser->parse($body, $mediaType);

        if ($this->coercion && null !== $mediaTypeSchema->schema) {
            $parsedBody = $this->typeCoercer->coerce($parsedBody, $mediaTypeSchema->schema, true, $this->nullableAsType);
            $parsedBody = TypeGuarantor::ensureValidType($parsedBody, $this->nullableAsType);
        }

        if (null !== $mediaTypeSchema->schema) {
            $schema = $mediaTypeSchema->schema;
            $hasDiscriminator = null !== $schema->discriminator || $this->refResolver->schemaHasDiscriminator($schema, $this->document);
            $hasRef = $this->refResolver->schemaHasRef($schema);

            $context = ValidationContext::create($this->pool, $this->nullableAsType, $this->emptyArrayStrategy, ValidatorMode::Response);

            if (false === ($hasDiscriminator || $hasRef)) {
                $this->regularSchemaValidator->validate($parsedBody, $schema, $context);

                return;
            }

            $this->contextSchemaValidator->validate($parsedBody, $schema, true, ValidatorMode::Response);
        }
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

        $context = ValidationContext::create($this->pool, $this->nullableAsType, $this->emptyArrayStrategy, ValidatorMode::Response);
        $hasDiscriminator = null !== $schema->discriminator || $this->refResolver->schemaHasDiscriminator($schema, $this->document);
        $hasRef = $this->refResolver->schemaHasRef($schema);

        foreach ($items as $item) {
            if (null === $item) {
                continue;
            }

            if (false === ($hasDiscriminator || $hasRef)) {
                $this->regularSchemaValidator->validate($item, $schema, $context);

                continue;
            }

            $this->contextSchemaValidator->validate($item, $schema, true, ValidatorMode::Response);
        }
    }
}
