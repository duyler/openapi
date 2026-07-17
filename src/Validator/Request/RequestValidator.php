<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Validator\BodyReader;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Duyler\OpenApi\Schema\Model\Parameter;

use function is_array;
use function str_starts_with;

final readonly class RequestValidator implements RequestValidatorInterface
{
    public function __construct(
        private readonly PathParser $pathParser,
        private readonly PathParametersValidator $pathParamsValidator,
        private readonly QueryParser $queryParser,
        private readonly QueryParametersValidator $queryParamsValidator,
        private readonly QueryStringValidator $queryStringValidator,
        private readonly HeadersValidator $headersValidator,
        private readonly CookieValidator $cookieValidator,
        private readonly RequestBodyValidatorInterface $bodyValidator,
        private readonly ValidatorConfiguration $configuration = new ValidatorConfiguration(),
    ) {}

    #[Override]
    public function validate(
        ServerRequestInterface $request,
        Operation $operation,
        string $pathTemplate,
    ): void {
        $parameters = $operation->parameters?->parameters ?? [];

        /** @var list<Parameter> $parameterSchemas */
        $parameterSchemas = array_filter($parameters, fn($param) => $param instanceof Parameter);

        $pathParams = $this->pathParser->matchPath(
            $request->getUri()->getPath(),
            $pathTemplate,
        );
        $this->pathParamsValidator->validate($pathParams, $parameterSchemas);

        $queryString = $request->getUri()->getQuery();
        $queryParams = $this->queryParser->parse($queryString);
        $this->queryParamsValidator->validate($queryParams, $parameterSchemas);

        $this->queryStringValidator->validate($queryString, $parameterSchemas);

        $headers = $request->getHeaders();
        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            /** @var array<array-key, string>|string $value */
            $normalizedHeaders[$key] = is_array($value) ? implode(', ', $value) : $value;
        }
        /** @var array<array-key, string> $normalizedHeaders */
        $this->headersValidator->validate($normalizedHeaders, $parameterSchemas);

        $cookies = $request->getCookieParams();
        $cookieHeader = $request->getHeaderLine('Cookie');
        if ([] === $cookies) {
            $cookies = $this->cookieValidator->parseCookies($cookieHeader);
        }
        /** @var array<string, string> $cookies */
        $this->cookieValidator->validateWithHeader($cookies, $cookieHeader, $parameterSchemas);

        $contentType = $request->getHeaderLine('Content-Type');
        $maxBytes = str_starts_with($contentType, 'multipart/')
            ? $this->configuration->maxMultipartBodyBytes
            : $this->configuration->maxJsonBodyBytes;
        $body = BodyReader::readSafely($request->getBody(), $maxBytes);
        $this->bodyValidator->validate($body, $contentType, $operation->requestBody);
    }
}
