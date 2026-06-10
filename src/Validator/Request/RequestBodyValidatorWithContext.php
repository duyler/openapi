<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Exception\MissingRequestBodyException;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Request\BodyParser\BodyParser;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\TypeGuarantor;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Override;

readonly class RequestBodyValidatorWithContext implements RequestBodyValidatorInterface
{
    private SchemaValidator $regularSchemaValidator;
    private SchemaValidatorWithContext $contextSchemaValidator;
    private RefResolverInterface $refResolver;
    private RequestBodyCoercer $coercer;
    private readonly FormatRegistry $formatRegistry;

    public function __construct(
        private readonly ValidatorPool $pool,
        private readonly OpenApiDocument $document,
        private readonly BodyParser $bodyParser,
        ?FormatRegistry $formatRegistry = null,
        private readonly ContentTypeNegotiator $negotiator = new ContentTypeNegotiator(),
        private readonly bool $nullableAsType = true,
        private readonly EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
        private readonly bool $coercion = false,
    ) {
        $this->formatRegistry = $formatRegistry ?? BuiltinFormats::instance();
        $this->regularSchemaValidator = new SchemaValidator($this->pool, $this->formatRegistry);

        $this->refResolver = new RefResolver();
        $this->contextSchemaValidator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document, $this->formatRegistry, $this->nullableAsType, $this->emptyArrayStrategy);
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

        if ($requestBody->required && '' === trim($body)) {
            throw new MissingRequestBodyException();
        }

        if (null === $requestBody->content) {
            if ($requestBody->required) {
                throw new MissingRequestBodyException();
            }

            return;
        }

        if ('' === trim($body) && false === $requestBody->required) {
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

            if (false === $hasDiscriminator) {
                $context = ValidationContext::create($this->pool, $this->nullableAsType, $this->emptyArrayStrategy);
                $this->regularSchemaValidator->validate($parsedBody, $schema, $context);

                return;
            }

            $this->contextSchemaValidator->validate($parsedBody, $schema);
        }
    }
}
