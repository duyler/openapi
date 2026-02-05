<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use Duyler\OpenApi\Validator\Request\BodyParser\BodyParser;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\TypeGuarantor;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Override;

final readonly class RequestBodyValidatorWithContext implements RequestBodyValidatorInterface
{
    private SchemaValidator $regularSchemaValidator;
    private SchemaValidatorWithContext $contextSchemaValidator;
    private RefResolverInterface $refResolver;
    private RequestBodyCoercer $coercer;

    public function __construct(
        private readonly ValidatorPool $pool,
        private readonly OpenApiDocument $document,
        private readonly BodyParser $bodyParser,
        private readonly ContentTypeNegotiator $negotiator = new ContentTypeNegotiator(),
        private readonly bool $nullableAsType = true,
        private readonly bool $coercion = false,
    ) {
        $formatRegistry = BuiltinFormats::create();
        $this->regularSchemaValidator = new SchemaValidator($this->pool, $formatRegistry);

        $this->refResolver = new RefResolver();
        $this->contextSchemaValidator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document, $this->nullableAsType);
        $this->coercer = new RequestBodyCoercer();
    }

    #[Override]
    public function validate(
        string $body,
        string $contentType,
        ?RequestBody $requestBody,
    ): void {
        if (null === $requestBody) {
            return;
        }

        if (null === $requestBody->content) {
            return;
        }

        $mediaType = $this->negotiator->getMediaType($contentType);
        $content = $requestBody->content->mediaTypes[$mediaType] ?? null;

        if (null === $content) {
            throw new UnsupportedMediaTypeException($mediaType, array_keys($requestBody->content->mediaTypes));
        }

        $parsedBody = $this->bodyParser->parse($body, $mediaType);

        if ($this->coercion && null !== $content->schema) {
            $schema = $content->schema;

            if (null !== $schema->ref) {
                $schema = $this->refResolver->resolve($schema->ref, $this->document);
            }

            $parsedBody = $this->coercer->coerce($parsedBody, $schema, true, true, $this->nullableAsType);
            $parsedBody = TypeGuarantor::ensureValidType($parsedBody, $this->nullableAsType);
        }

        if (null !== $content->schema) {
            $schema = $content->schema;

            if (null !== $schema->ref) {
                $schema = $this->refResolver->resolve($schema->ref, $this->document);
            }

            $hasDiscriminator = null !== $schema->discriminator || $this->refResolver->schemaHasDiscriminator($schema, $this->document);

            if ($hasDiscriminator) {
                $this->contextSchemaValidator->validate($parsedBody, $schema);
            } else {
                $context = ValidationContext::create($this->pool, $this->nullableAsType);
                $this->regularSchemaValidator->validate($parsedBody, $schema, $context);
            }
        }
    }
}
