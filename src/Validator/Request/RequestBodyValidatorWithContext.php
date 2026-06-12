<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\MissingRequestBodyException;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Request\BodyParser\BodyParser;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\TypeGuarantor;
use Duyler\OpenApi\Validator\ValidatorMode;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class RequestBodyValidatorWithContext implements RequestBodyValidatorInterface
{
    private SchemaValidator $regularSchemaValidator;
    private SchemaValidatorWithContext $contextSchemaValidator;
    private RequestBodyCoercer $coercer;
    private readonly FormatRegistry $formatRegistry;
    private readonly LoggerInterface $logger;
    private readonly ErrorFormatterInterface $errorFormatter;

    public function __construct(
        private readonly ValidatorPool $pool,
        private readonly OpenApiDocument $document,
        private readonly BodyParser $bodyParser,
        StatelessValidatorRegistry $statelessValidators,
        private readonly RefResolverInterface $refResolver,
        FormatRegistry $formatRegistry,
        private readonly ContentTypeNegotiator $negotiator = new ContentTypeNegotiator(),
        private readonly bool $nullableAsType = true,
        private readonly EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
        private readonly bool $coercion = false,
        private readonly bool $reportDeprecated = false,
        ?LoggerInterface $logger = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        ?ErrorFormatterInterface $errorFormatter = null,
    ) {
        $this->formatRegistry = $formatRegistry;
        $this->logger = $logger ?? new NullLogger();
        $this->errorFormatter = $errorFormatter ?? SimpleFormatter::shared();
        $this->regularSchemaValidator = new SchemaValidator($this->pool, $this->formatRegistry, reportDeprecated: $this->reportDeprecated, logger: $this->logger, eventDispatcher: $this->eventDispatcher);

        $this->contextSchemaValidator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document, $statelessValidators, $this->nullableAsType, $this->emptyArrayStrategy, reportDeprecated: $this->reportDeprecated, logger: $this->logger, eventDispatcher: $this->eventDispatcher, errorFormatter: $this->errorFormatter);
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

            /** @var array|int|string|float|bool|null $parsedBody */
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
                $context = ValidationContext::create($this->pool, $this->errorFormatter, $this->nullableAsType, $this->emptyArrayStrategy, ValidatorMode::Request);
                $this->regularSchemaValidator->validate($parsedBody, $schema, $context);

                return;
            }

            $this->contextSchemaValidator->validate($parsedBody, $schema, true, ValidatorMode::Request);
        }
    }
}
