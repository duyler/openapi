<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\CoercionContext;
use Duyler\OpenApi\Validator\Dto\ParameterValidationConfig;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Exception\MissingRequestBodyException;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use Duyler\OpenApi\Validator\Example\ExampleValidator;
use Duyler\OpenApi\Validator\Example\ValidatesExamplesTrait;
use Duyler\OpenApi\Validator\ExternalMediaType;
use Duyler\OpenApi\Validator\Request\Internal\EncodingHeaderRefResolver;
use Duyler\OpenApi\Validator\Request\Internal\MultipartEncodingApplier;
use Duyler\OpenApi\Validator\Request\Internal\OrderedMultipartPlanner;
use Duyler\OpenApi\Validator\Request\Internal\PartHeaderValueValidator;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorDispatcher;
use Duyler\OpenApi\Validator\TypeGuarantor;
use Duyler\OpenApi\Validator\ValidatorMode;
use Override;

use function str_starts_with;

final readonly class RequestBodyValidatorWithContext implements RequestBodyValidatorInterface
{
    use ValidatesExamplesTrait;

    private SchemaValidatorWithContext $contextSchemaValidator;
    private RequestBodyCoercer $coercer;
    private readonly ContentTypeNegotiator $negotiator;
    private readonly ExampleValidator $exampleValidator;
    private readonly MultipartEncodingApplier $encodingApplier;
    private readonly OrderedMultipartPlanner $orderedPlanner;
    private readonly EncodingHeaderRefResolver $encodingHeaderRefs;

    public function __construct(
        private readonly OpenApiDocument $document,
        private readonly SchemaValidatorDependencies $dependencies,
        private readonly ValidatorConfiguration $configuration = new ValidatorConfiguration(),
    ) {
        $this->negotiator = new ContentTypeNegotiator($this->dependencies->pregExecutor);
        $this->exampleValidator = new ExampleValidator();
        $this->encodingApplier = new MultipartEncodingApplier(
            multipartParser: $this->dependencies->bodyParser->multipartParser(),
            headerValueValidator: new PartHeaderValueValidator(
                schemaValidator: new StatelessValidatorDispatcher(
                    validators: $this->dependencies->createFullList(),
                    pool: $this->dependencies->pool,
                ),
                pool: $this->dependencies->pool,
                config: new ParameterValidationConfig(
                    nullableAsType: $this->configuration->nullableAsType,
                    emptyArrayStrategy: $this->configuration->emptyArrayStrategy,
                    dialectProfile: $this->configuration->dialectProfile,
                ),
            ),
        );
        $this->orderedPlanner = new OrderedMultipartPlanner();
        $this->encodingHeaderRefs = new EncodingHeaderRefResolver();

        $this->contextSchemaValidator = $this->dependencies->rootSchemaValidator(
            $this->document,
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

        $requestBody = $this->dependencies->refResolver->resolveRequestBodyWithOverride(
            $requestBody,
            $this->document,
        );

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

        $requestMediaType = $this->negotiator->getMediaType($contentType);

        $matchedSpecType = MediaTypeMatcher::findMatch(
            $requestMediaType,
            array_keys($requestBody->content->mediaTypes),
        );

        if (null === $matchedSpecType) {
            throw new UnsupportedMediaTypeException($requestMediaType, array_keys($requestBody->content->mediaTypes));
        }

        $content = $this->encodingHeaderRefs->resolve(
            $requestBody->content->mediaTypes[$matchedSpecType],
            $this->document,
        );

        $schema = $this->resolveSchema($content);

        $parsedBody = $this->parseBody($body, $contentType, $requestMediaType, $content, $schema);

        if (null !== $schema) {
            if ($this->configuration->coercion) {
                $coercionContext = new CoercionContext(
                    schema: $schema,
                    strict: $this->configuration->strictCoercion,
                    nullableAsType: $this->configuration->nullableAsType,
                );

                /** @var array|int|string|float|bool|null $parsedBody */
                $parsedBody = $this->coercer->coerce($parsedBody, $coercionContext);
                $parsedBody = TypeGuarantor::ensureValidType($parsedBody);
            }

            $this->contextSchemaValidator->validate(
                $parsedBody,
                $schema,
                ValidatorMode::Request,
                skipContentMediaType: ExternalMediaType::isDefined(null, $matchedSpecType),
            );

            $this->dispatchExampleWarnings(
                $parsedBody,
                $content,
                $this->exampleValidator,
                $this->dependencies->eventDispatcher,
            );
        }
    }

    private function resolveSchema(MediaType $content): ?Schema
    {
        if (null === $content->schema) {
            return null;
        }

        $schema = $content->schema;

        if (null !== $schema->ref) {
            return $this->dependencies->refResolver->resolve($schema->ref, $this->document);
        }

        return $schema;
    }

    /**
     * @return array|int|string|float|bool|null
     */
    private function parseBody(
        string $body,
        string $contentType,
        string $requestMediaType,
        MediaType $content,
        ?Schema $schema,
    ): array|int|string|float|bool|null {
        if (null !== $schema && $this->isOrderedMultipart($requestMediaType, $content)) {
            return $this->applyOrderedMultipart($body, $contentType, $content, $schema);
        }

        $parsedBody = $this->dependencies->bodyParser->parse($body, $requestMediaType, $contentType);

        $this->applyMultipartEncoding($body, $contentType, $requestMediaType, $content);

        return $parsedBody;
    }

    /**
     * @return array<mixed>
     */
    private function applyOrderedMultipart(
        string $body,
        string $contentType,
        MediaType $content,
        Schema $schema,
    ): array {
        $plan = $this->orderedPlanner->plan(
            parts: $this->dependencies->bodyParser->multipartParser()->parse($body, $contentType),
            mediaType: $content,
            schema: $schema,
        );

        $this->orderedPlanner->apply($plan, $this->encodingApplier);

        return $plan->instance;
    }

    private function isOrderedMultipart(string $requestMediaType, MediaType $content): bool
    {
        if (false === str_starts_with($requestMediaType, 'multipart/')) {
            return false;
        }

        return null !== $content->prefixEncoding || null !== $content->itemEncoding;
    }

    private function applyMultipartEncoding(
        string $body,
        string $contentType,
        string $requestMediaType,
        MediaType $content,
    ): void {
        if (false === str_starts_with($requestMediaType, 'multipart/')) {
            return;
        }

        $this->encodingApplier->applyToBody(
            body: $body,
            fullContentType: $contentType,
            prefixEncoding: $content->prefixEncoding,
            itemEncoding: $content->itemEncoding,
            encoding: $content->encoding,
        );
    }
}
