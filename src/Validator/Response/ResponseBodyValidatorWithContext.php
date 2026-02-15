<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use Duyler\OpenApi\Schema\Model\Content;
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

        $parsedBody = $this->bodyParser->parse($body, $mediaType);

        if ($this->coercion && null !== $mediaTypeSchema->schema) {
            $parsedBody = $this->typeCoercer->coerce($parsedBody, $mediaTypeSchema->schema, true, $this->nullableAsType);
            $parsedBody = TypeGuarantor::ensureValidType($parsedBody, $this->nullableAsType);
        }

        if (null !== $mediaTypeSchema->schema) {
            $schema = $mediaTypeSchema->schema;
            $hasDiscriminator = null !== $schema->discriminator || $this->refResolver->schemaHasDiscriminator($schema, $this->document);

            $context = ValidationContext::create($this->pool, $this->nullableAsType, $this->emptyArrayStrategy);

            if ($hasDiscriminator) {
                $this->contextSchemaValidator->validate($parsedBody, $schema);
            } else {
                $this->regularSchemaValidator->validate($parsedBody, $schema, $context);
            }
        }
    }
}
