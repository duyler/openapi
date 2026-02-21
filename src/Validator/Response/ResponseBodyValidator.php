<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Validator\Request\BodyParser\BodyParser;
use Duyler\OpenApi\Validator\Request\ContentTypeNegotiator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\TypeGuarantor;

readonly class ResponseBodyValidator
{
    public function __construct(
        private readonly SchemaValidatorInterface $schemaValidator,
        private readonly BodyParser $bodyParser,
        private readonly ContentTypeNegotiator $negotiator,
        private readonly ResponseTypeCoercer $typeCoercer,
        private readonly StreamingContentParser $streamingParser = new StreamingContentParser(),
        private readonly bool $coercion = false,
    ) {}

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
            $parsedBody = $this->typeCoercer->coerce($parsedBody, $mediaTypeSchema->schema, true);
            $parsedBody = TypeGuarantor::ensureValidType($parsedBody);
        }

        if (null !== $mediaTypeSchema->schema) {
            $this->schemaValidator->validate($parsedBody, $mediaTypeSchema->schema);
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

        foreach ($items as $item) {
            if (null !== $item) {
                $this->schemaValidator->validate($item, $schema);
            }
        }
    }
}
