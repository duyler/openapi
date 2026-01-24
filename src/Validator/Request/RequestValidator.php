<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Operation;
use Psr\Http\Message\ServerRequestInterface;
use Duyler\OpenApi\Schema\Model\Parameter;

use function is_array;

final readonly class RequestValidator
{
    public function __construct(
        private readonly PathParser $pathParser,
        private readonly PathParametersValidator $pathParamsValidator,
        private readonly QueryParser $queryParser,
        private readonly QueryParametersValidator $queryParamsValidator,
        private readonly HeadersValidator $headersValidator,
        private readonly CookieValidator $cookieValidator,
        private readonly RequestBodyValidator $bodyValidator,
    ) {}

    public function validate(
        ServerRequestInterface $request,
        Operation $operation,
        string $pathTemplate,
    ): void {
        $parameters = $operation->parameters?->parameters ?? [];

        $parameterSchemas = array_filter($parameters, fn($param) => $param instanceof Parameter);

        $pathParams = $this->pathParser->matchPath(
            $request->getUri()->getPath(),
            $pathTemplate,
        );
        $this->pathParamsValidator->validate($pathParams, $parameterSchemas);

        $queryString = $request->getUri()->getQuery();
        $queryParams = $this->queryParser->parse($queryString);
        $this->queryParamsValidator->validate($queryParams, $parameterSchemas);

        $headers = $request->getHeaders();
        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            /** @var array<array-key, string>|string $value */
            $normalizedHeaders[$key] = is_array($value) ? implode(', ', $value) : $value;
        }
        /** @var array<array-key, string> $normalizedHeaders */
        $this->headersValidator->validate($normalizedHeaders, $parameterSchemas);

        $cookieHeader = $request->getHeaderLine('Cookie');
        $cookies = $this->cookieValidator->parseCookies($cookieHeader);
        $this->cookieValidator->validate($cookies, $parameterSchemas);

        $contentType = $request->getHeaderLine('Content-Type');
        $body = (string) $request->getBody();
        $this->bodyValidator->validate($body, $contentType, $operation->requestBody);
    }
}
