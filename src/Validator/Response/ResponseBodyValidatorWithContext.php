<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Request\BodyParser\BodyParser;
use Duyler\OpenApi\Validator\Request\ContentTypeNegotiator;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\TypeGuarantor;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;

readonly class ResponseBodyValidatorWithContext
{
    private SchemaValidator $regularSchemaValidator;
    private SchemaValidatorWithContext $contextSchemaValidator;
    private RefResolver $refResolver;

    public function __construct(
        private readonly ValidatorPool $pool,
        private readonly OpenApiDocument $document,
        private readonly BodyParser $bodyParser,
        private readonly ContentTypeNegotiator $negotiator = new ContentTypeNegotiator(),
        private readonly ResponseTypeCoercer $typeCoercer = new ResponseTypeCoercer(),
        private readonly StreamingContentParser $streamingParser = new StreamingContentParser(),
        private readonly bool $coercion = false,
        private readonly bool $nullableAsType = true,
        private readonly EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
    ) {
        $formatRegistry = BuiltinFormats::create();
        $this->regularSchemaValidator = new SchemaValidator($this->pool, $formatRegistry);

        $this->refResolver = new RefResolver();
        $this->contextSchemaValidator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document, $this->nullableAsType, $this->emptyArrayStrategy);
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
        } else {
            $this->validateRegularContent($body, $mediaTypeSchema, $mediaType);
        }
    }

    private function validateRegularContent(
        string $body,
        MediaType $mediaTypeSchema,
        string $mediaType,
    ): void {
        $parsedBody = $this->bodyParser->parse($body, $mediaType);

        if ($this->coercion && null !== $mediaTypeSchema->schema) {
            $parsedBody = $this->typeCoercer->coerce($parsedBody, $mediaTypeSchema->schema, true, $this->nullableAsType);
            $parsedBody = TypeGuarantor::ensureValidType($parsedBody, $this->nullableAsType);
        }

        if (null !== $mediaTypeSchema->schema) {
            $schema = $mediaTypeSchema->schema;
            $hasDiscriminator = null !== $schema->discriminator || $this->refResolver->schemaHasDiscriminator($schema, $this->document);
            $hasRef = $this->refResolver->schemaHasRef($schema);

            $context = ValidationContext::create($this->pool, $this->nullableAsType, $this->emptyArrayStrategy);

            if ($hasDiscriminator || $hasRef) {
                $this->contextSchemaValidator->validate($parsedBody, $schema);
            } else {
                $this->regularSchemaValidator->validate($parsedBody, $schema, $context);
            }
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

        $context = ValidationContext::create($this->pool, $this->nullableAsType, $this->emptyArrayStrategy);
        $hasDiscriminator = null !== $schema->discriminator || $this->refResolver->schemaHasDiscriminator($schema, $this->document);
        $hasRef = $this->refResolver->schemaHasRef($schema);

        foreach ($items as $item) {
            if (null !== $item) {
                if ($hasDiscriminator || $hasRef) {
                    $this->contextSchemaValidator->validate($item, $schema);
                } else {
                    $this->regularSchemaValidator->validate($item, $schema, $context);
                }
            }
        }
    }
}
