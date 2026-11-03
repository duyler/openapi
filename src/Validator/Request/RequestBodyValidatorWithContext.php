<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\BodyReader;
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
use Duyler\OpenApi\Validator\Request\Internal\MatchedRequestBody;
use Duyler\OpenApi\Validator\Request\Internal\MultipartEncodingApplier;
use Duyler\OpenApi\Validator\Request\Internal\MultipartInstanceResolver;
use Duyler\OpenApi\Validator\Request\Internal\OrderedMultipartPlanner;
use Duyler\OpenApi\Validator\Request\Internal\PartHeaderValueValidator;
use Duyler\OpenApi\Validator\Request\Internal\StreamingMultipartValidator;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorDispatcher;
use Duyler\OpenApi\Validator\TypeGuarantor;
use Duyler\OpenApi\Validator\ValidatorMode;
use Override;
use Psr\Http\Message\StreamInterface;

use function array_keys;
use function str_starts_with;

final readonly class RequestBodyValidatorWithContext implements RequestBodyValidatorInterface
{
    use ValidatesExamplesTrait;

    private const string FORM_DATA_MEDIA_TYPE = 'multipart/form-data';

    private SchemaValidatorWithContext $contextSchemaValidator;
    private RequestBodyCoercer $coercer;
    private readonly ContentTypeNegotiator $negotiator;
    private readonly ExampleValidator $exampleValidator;
    private readonly MultipartEncodingApplier $encodingApplier;
    private readonly OrderedMultipartPlanner $orderedPlanner;
    private readonly MultipartInstanceResolver $instanceResolver;
    private readonly EncodingHeaderRefResolver $encodingHeaderRefs;
    private readonly StreamingMultipartValidator $streamingMultipartValidator;

    public function __construct(
        private readonly OpenApiDocument $document,
        private readonly SchemaValidatorDependencies $dependencies,
        private readonly ValidatorConfiguration $configuration = new ValidatorConfiguration(),
    ) {
        $this->negotiator = new ContentTypeNegotiator($this->dependencies->pregExecutor);
        $this->exampleValidator = new ExampleValidator();
        $this->encodingHeaderRefs = new EncodingHeaderRefResolver();

        $this->contextSchemaValidator = $this->dependencies->rootSchemaValidator(
            $this->document,
            $this->configuration,
        );
        $this->coercer = new RequestBodyCoercer();

        $maxNestingDepth = MultipartInstanceResolver::MAX_NESTING_DEPTH;

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
            maxNestingDepth: $maxNestingDepth,
        );
        $this->instanceResolver = new MultipartInstanceResolver(
            multipartParser: $this->dependencies->bodyParser->multipartParser(),
            xmlParser: $this->dependencies->bodyParser->xmlParser(),
            maxNestingDepth: $maxNestingDepth,
        );
        $this->orderedPlanner = new OrderedMultipartPlanner($this->instanceResolver);
        $this->streamingMultipartValidator = new StreamingMultipartValidator(
            encodingApplier: $this->encodingApplier,
            instanceResolver: $this->instanceResolver,
            orderedPlanner: $this->orderedPlanner,
            schemaValidator: $this->contextSchemaValidator,
            configuration: $this->configuration,
            eventDispatcher: $this->dependencies->eventDispatcher,
        );
    }

    #[Override]
    public function validate(
        string $body,
        string $contentType,
        ?RequestBody $requestBody,
    ): void {
        $requestBody = $this->resolvedBody($requestBody);

        if (null === $requestBody) {
            return;
        }

        $this->validateResolved($body, $contentType, $requestBody);
    }

    #[Override]
    public function validateStream(
        StreamInterface $stream,
        string $contentType,
        ?RequestBody $requestBody,
    ): void {
        $requestBody = $this->resolvedBody($requestBody);

        if (null === $requestBody) {
            return;
        }

        $matched = $this->matchContent($requestBody, $contentType);

        if (null === $matched) {
            return;
        }

        if (false === $this->isOrderedMultipart($matched->requestMediaType, $matched->content)) {
            $this->validateResolved(
                body: BodyReader::readSafely($stream, $this->maxBodyBytes($contentType)),
                contentType: $contentType,
                requestBody: $requestBody,
            );

            return;
        }

        $mediaType = $this->encodingHeaderRefs->resolve($matched->content, $this->document);

        $bytes = $this->streamingMultipartValidator->validate(
            stream: $stream,
            contentType: $contentType,
            mediaType: $mediaType,
            schema: $this->resolveSchema($mediaType),
            skipContentMediaType: ExternalMediaType::isDefined(
                $mediaType->itemEncoding?->contentType,
                $matched->specMediaType,
            ),
        );

        if ($requestBody->required && 0 === $bytes) {
            throw new MissingRequestBodyException();
        }
    }

    private function resolvedBody(?RequestBody $requestBody): ?RequestBody
    {
        if (null === $requestBody) {
            return null;
        }

        return $this->dependencies->refResolver->resolveRequestBodyWithOverride(
            $requestBody,
            $this->document,
        );
    }

    private function matchContent(RequestBody $requestBody, string $contentType): ?MatchedRequestBody
    {
        if (null === $requestBody->content) {
            if ($requestBody->required) {
                throw new MissingRequestBodyException();
            }

            return null;
        }

        $requestMediaType = $this->negotiator->getMediaType($contentType);

        $matchedSpecType = MediaTypeMatcher::findMatch(
            $requestMediaType,
            array_keys($requestBody->content->mediaTypes),
        );

        if (null === $matchedSpecType) {
            throw new UnsupportedMediaTypeException($requestMediaType, array_keys($requestBody->content->mediaTypes));
        }

        return new MatchedRequestBody(
            requestMediaType: $requestMediaType,
            specMediaType: $matchedSpecType,
            content: $requestBody->content->mediaTypes[$matchedSpecType],
        );
    }

    private function validateResolved(
        string $body,
        string $contentType,
        RequestBody $requestBody,
    ): void {
        if ($requestBody->required && '' === trim($body)) {
            throw new MissingRequestBodyException();
        }

        if ('' === trim($body) && false === $requestBody->required) {
            return;
        }

        $matched = $this->matchContent($requestBody, $contentType);

        if (null === $matched) {
            return;
        }

        $content = $this->encodingHeaderRefs->resolve($matched->content, $this->document);

        $schema = $this->resolveSchema($content);

        $parsedBody = $this->parseBody($body, $contentType, $matched->requestMediaType, $content, $schema);

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
                skipContentMediaType: ExternalMediaType::isDefined(null, $matched->specMediaType),
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

        if (str_starts_with($requestMediaType, 'multipart/')) {
            return $this->parseMultipartBody($body, $contentType, $requestMediaType, $content, $schema);
        }

        return $this->dependencies->bodyParser->parse($body, $requestMediaType, $contentType, $schema);
    }

    /**
     * @return array<int|string, mixed>|string
     */
    private function parseMultipartBody(
        string $body,
        string $contentType,
        string $requestMediaType,
        MediaType $content,
        ?Schema $schema,
    ): array|string {
        $hasEncoding = null !== $content->prefixEncoding
            || null !== $content->itemEncoding
            || null !== $content->encoding;

        if (self::FORM_DATA_MEDIA_TYPE !== $requestMediaType && false === $hasEncoding) {
            return $body;
        }

        $parts = $this->dependencies->bodyParser->multipartParser()->parse($body, $contentType);

        if ($hasEncoding) {
            $this->encodingApplier->applyToParts(
                parts: $parts,
                prefixEncoding: $content->prefixEncoding,
                itemEncoding: $content->itemEncoding,
                encoding: $content->encoding,
            );
        }

        if (self::FORM_DATA_MEDIA_TYPE !== $requestMediaType) {
            return $body;
        }

        if (null !== $schema && $this->isNamedMultipart($schema)) {
            return $this->instanceResolver->namedValues($parts, $content->encoding, $schema);
        }

        return $parts;
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

    private function isNamedMultipart(Schema $schema): bool
    {
        return null !== $schema->properties && [] !== $schema->properties;
    }

    private function maxBodyBytes(string $contentType): int
    {
        return str_starts_with($contentType, 'multipart/')
            ? $this->configuration->maxMultipartBodyBytes
            : $this->configuration->maxJsonBodyBytes;
    }
}
