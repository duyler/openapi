<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\RequestBody;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\CoercionContext;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Exception\MissingRequestBodyException;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use Duyler\OpenApi\Validator\Example\ExampleValidator;
use Duyler\OpenApi\Validator\Example\ValidatesExamplesTrait;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\TypeGuarantor;
use Duyler\OpenApi\Validator\ValidatorMode;
use Override;

final readonly class RequestBodyValidatorWithContext implements RequestBodyValidatorInterface
{
    use ValidatesExamplesTrait;

    private SchemaValidatorWithContext $contextSchemaValidator;
    private RequestBodyCoercer $coercer;
    private readonly ContentTypeNegotiator $negotiator;
    private readonly ExampleValidator $exampleValidator;

    public function __construct(
        private readonly OpenApiDocument $document,
        private readonly SchemaValidatorDependencies $dependencies,
        private readonly ValidatorConfiguration $configuration = new ValidatorConfiguration(),
        ?PregExecutor $pregExecutor = null,
    ) {
        $resolvedPregExecutor = $pregExecutor ?? new PregExecutor($this->configuration->maxRegexBacktracks);
        $this->negotiator = new ContentTypeNegotiator($resolvedPregExecutor);
        $this->exampleValidator = new ExampleValidator();

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

        $requestMediaType = $this->negotiator->getMediaType($contentType);

        $matchedSpecType = MediaTypeMatcher::findMatch(
            $requestMediaType,
            array_keys($requestBody->content->mediaTypes),
        );

        if (null === $matchedSpecType) {
            throw new UnsupportedMediaTypeException($requestMediaType, array_keys($requestBody->content->mediaTypes));
        }

        $content = $requestBody->content->mediaTypes[$matchedSpecType];

        $parsedBody = $this->dependencies->bodyParser->parse($body, $requestMediaType, $contentType);

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

            $this->contextSchemaValidator->validate($parsedBody, $schema, ValidatorMode::Request);

            $this->dispatchExampleWarnings(
                $parsedBody,
                $content,
                $this->exampleValidator,
                $this->dependencies->eventDispatcher,
            );
        }
    }
}
