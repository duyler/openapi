<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\BodyReader;
use Duyler\OpenApi\Validator\Dto\ParameterValidationConfig;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Exception\UndefinedResponseException;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Schema\Model\Response;
use Psr\Http\Message\ResponseInterface;

use function is_array;
use function str_starts_with;

final readonly class ResponseValidatorWithContext
{
    private const int HTTP_STATUS_RANGE_DIVISOR = 100;

    private readonly ResponseHeadersValidator $headersValidator;
    private readonly ResponseBodyValidatorWithContext $bodyValidator;
    private readonly StatusCodeValidator $statusCodeValidator;

    public function __construct(
        private readonly OpenApiDocument $document,
        private readonly SchemaValidatorDependencies $dependencies,
        private readonly ValidatorConfiguration $configuration = new ValidatorConfiguration(),
        ?PregExecutor $pregExecutor = null,
    ) {
        $resolvedPregExecutor = $pregExecutor ?? new PregExecutor($this->configuration->maxRegexBacktracks);
        $this->statusCodeValidator = new StatusCodeValidator();

        $schemaValidator = new SchemaValidator(
            $this->dependencies->pool,
            $this->dependencies->formatRegistry,
            strictFormats: $this->configuration->strictFormats,
            reportDeprecated: $this->configuration->reportDeprecated,
            logger: $this->dependencies->logger,
            eventDispatcher: $this->dependencies->eventDispatcher,
            pregExecutor: $resolvedPregExecutor,
        );
        $this->headersValidator = new ResponseHeadersValidator(
            schemaValidator: $schemaValidator,
            pool: $this->dependencies->pool,
            config: new ParameterValidationConfig(
                nullableAsType: $this->configuration->nullableAsType,
                emptyArrayStrategy: $this->configuration->emptyArrayStrategy,
            ),
        );
        $this->bodyValidator = new ResponseBodyValidatorWithContext(
            document: $this->document,
            dependencies: $this->dependencies,
            configuration: $this->configuration,
            pregExecutor: $resolvedPregExecutor,
        );
    }

    public function validate(
        ResponseInterface $response,
        Operation $operation,
    ): void {
        $statusCode = $response->getStatusCode();
        $responses = $operation->responses?->responses ?? [];

        $this->statusCodeValidator->validate($statusCode, $responses);

        $responseDefinition = $this->resolveResponseRef(
            $responses[(string) $statusCode]
                ?? $responses[$this->getRange($statusCode)]
                ?? $responses['default'],
        );

        if (false === ($responseDefinition instanceof Response)) {
            throw new UndefinedResponseException($statusCode, array_keys($responses));
        }

        $headers = $response->getHeaders();
        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            /** @var array<array-key, string>|string $value */
            $normalizedHeaders[$key] = is_array($value) ? implode(', ', $value) : $value;
        }

        $this->headersValidator->validate($normalizedHeaders, $responseDefinition->headers);

        $contentType = $response->getHeaderLine('Content-Type');
        $content = $responseDefinition->content;

        if (StreamingMediaTypeDetector::isStreaming($contentType)) {
            $this->bodyValidator->validateStream(
                $response->getBody(),
                $contentType,
                $content,
            );

            return;
        }

        $maxBytes = str_starts_with($contentType, 'multipart/')
            ? $this->configuration->maxMultipartBodyBytes
            : $this->configuration->maxJsonBodyBytes;
        $body = BodyReader::readSafely($response->getBody(), $maxBytes);

        $this->bodyValidator->validate($body, $contentType, $content);
    }

    private function getRange(int $statusCode): string
    {
        $firstDigit = (int) floor($statusCode / self::HTTP_STATUS_RANGE_DIVISOR);

        return $firstDigit . 'XX';
    }

    private function resolveResponseRef(object $response): object
    {
        if (false === $response instanceof Response) {
            return $response;
        }

        if (null === $response->ref) {
            return $response;
        }

        return $this->dependencies->refResolver->resolveResponse($response->ref, $this->document);
    }
}
