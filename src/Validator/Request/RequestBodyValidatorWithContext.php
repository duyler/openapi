<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\CoercionContext;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\MissingRequestBodyException;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\TypeGuarantor;
use Duyler\OpenApi\Validator\ValidatorMode;
use Override;

final readonly class RequestBodyValidatorWithContext implements RequestBodyValidatorInterface
{
    private SchemaValidator $regularSchemaValidator;
    private SchemaValidatorWithContext $contextSchemaValidator;
    private RequestBodyCoercer $coercer;
    private readonly ContentTypeNegotiator $negotiator;

    public function __construct(
        private readonly OpenApiDocument $document,
        private readonly SchemaValidatorDependencies $dependencies,
        private readonly ValidatorConfiguration $configuration = new ValidatorConfiguration(),
    ) {
        $this->negotiator = new ContentTypeNegotiator();
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

        $parsedBody = $this->dependencies->bodyParser->parse($body, $mediaType);

        if ($this->configuration->coercion && null !== $content->schema) {
            $schema = $content->schema;

            if (null !== $schema->ref) {
                $schema = $this->dependencies->refResolver->resolve($schema->ref, $this->document);
            }

            $coercionContext = new CoercionContext(
                schema: $schema,
                enabled: true,
                strict: true,
                nullableAsType: $this->configuration->nullableAsType,
            );

            /** @var array|int|string|float|bool|null $parsedBody */
            $parsedBody = $this->coercer->coerce($parsedBody, $coercionContext);
            $parsedBody = TypeGuarantor::ensureValidType($parsedBody, $this->configuration->nullableAsType);
        }

        if (null !== $content->schema) {
            $schema = $content->schema;

            if (null !== $schema->ref) {
                $schema = $this->dependencies->refResolver->resolve($schema->ref, $this->document);
            }

            $hasDiscriminator = null !== $schema->discriminator || $this->dependencies->refResolver->schemaHasDiscriminator($schema, $this->document);

            if (false === $hasDiscriminator) {
                $context = ValidationContext::create(
                    $this->dependencies->pool,
                    $this->dependencies->errorFormatter,
                    $this->configuration->nullableAsType,
                    $this->configuration->emptyArrayStrategy,
                    ValidatorMode::Request,
                );
                $this->regularSchemaValidator->validate($parsedBody, $schema, $context);

                return;
            }

            $this->contextSchemaValidator->validate($parsedBody, $schema, ValidatorMode::Request);
        }
    }
}
