<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use Duyler\OpenApi\Schema\Model\Operation;
use Psr\Http\Message\ResponseInterface;

use function is_array;

readonly class ResponseValidator
{
    public function __construct(
        private readonly StatusCodeValidator $statusCodeValidator,
        private readonly ResponseHeadersValidator $headersValidator,
        private readonly ResponseBodyValidator $bodyValidator,
    ) {}

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

        $headers = $response->getHeaders();
        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            /** @var array<array-key, string>|string $value */
            $normalizedHeaders[$key] = is_array($value) ? implode(', ', $value) : $value;
        }
        /** @var array<array-key, string> $normalizedHeaders */
        $this->headersValidator->validate($normalizedHeaders, $responseDefinition->headers);

        $contentType = $response->getHeaderLine('Content-Type');
        $body = (string) $response->getBody();
        $this->bodyValidator->validate($body, $contentType, $responseDefinition->content);
    }

    private function getRange(int $statusCode): string
    {
        $firstDigit = (int) floor($statusCode / 100);

        return $firstDigit . 'XX';
    }
}
