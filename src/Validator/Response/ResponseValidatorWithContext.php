<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Request\BodyParser\BodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Psr\Http\Message\ResponseInterface;
use Duyler\OpenApi\Validator\Exception\UndefinedResponseException;
use Duyler\OpenApi\Schema\Model\Response;

use function is_array;

final readonly class ResponseValidatorWithContext
{
    private readonly FormatRegistry $formatRegistry;

    public function __construct(
        private readonly ValidatorPool $pool,
        private readonly OpenApiDocument $document,
        ?FormatRegistry $formatRegistry = null,
        private readonly BodyParser $bodyParser = new BodyParser(
            jsonParser: new JsonBodyParser(),
            formParser: new FormBodyParser(),
            multipartParser: new MultipartBodyParser(),
            textParser: new TextBodyParser(),
            xmlParser: new XmlBodyParser(),
        ),
        private readonly bool $coercion = false,
        private readonly StatusCodeValidator $statusCodeValidator = new StatusCodeValidator(),
        private readonly bool $nullableAsType = true,
        private readonly EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
        private readonly ?RefResolverInterface $refResolver = null,
    ) {
        $this->formatRegistry = $formatRegistry ?? BuiltinFormats::instance();
    }

    public function validate(
        ResponseInterface $response,
        Operation $operation,
    ): void {
        $statusCode = $response->getStatusCode();
        $responses = $operation->responses?->responses ?? [];

        $this->statusCodeValidator->validate($statusCode, $responses);

        $responseDefinition = $responses[(string) $statusCode]
            ?? $responses[$this->getRange($statusCode)]
            ?? $responses['default'];

        $responseDefinition = $this->resolveResponseRef($responseDefinition);

        if (!$responseDefinition instanceof Response) {
            throw new UndefinedResponseException($statusCode, array_keys($responses));
        }

        $headers = $response->getHeaders();
        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            /** @var array<array-key, string>|string $value */
            $normalizedHeaders[$key] = is_array($value) ? implode(', ', $value) : $value;
        }

        $schemaValidator = new SchemaValidator($this->pool, $this->formatRegistry);
        $headersValidator = new ResponseHeadersValidator($schemaValidator);
        $headersValidator->validate($normalizedHeaders, $responseDefinition->headers ?? null);

        $contentType = $response->getHeaderLine('Content-Type');
        $body = (string) $response->getBody();

        $bodyValidator = new ResponseBodyValidatorWithContext(pool: $this->pool, document: $this->document, bodyParser: $this->bodyParser, formatRegistry: $this->formatRegistry, coercion: $this->coercion, nullableAsType: $this->nullableAsType, emptyArrayStrategy: $this->emptyArrayStrategy);
        $bodyValidator->validate($body, $contentType, $responseDefinition->content ?? null);
    }

    private function getRange(int $statusCode): string
    {
        $firstDigit = (int) floor($statusCode / 100);

        return $firstDigit . 'XX';
    }

    private function resolveResponseRef(object $response): object
    {
        if (false === $response instanceof Response) {
            return $response;
        }

        if (null === $response->ref || null === $this->refResolver) {
            return $response;
        }

        return $this->refResolver->resolveResponse($response->ref, $this->document);
    }
}
