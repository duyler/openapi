<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Support;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

use function http_build_query;

trait Psr7RequestStubFactory
{
    use StreamStubHelper;

    private function createStubRequest(
        string $method,
        string $uri,
        array $headers = [],
        string $body = '',
        array $queryParams = [],
        array $cookies = [],
    ): ServerRequestInterface {
        /** @var ServerRequestInterface $request */
        $request = $this->createStub(ServerRequestInterface::class);

        $request->method('getMethod')->willReturn($method);

        $uriStub = $this->createStub(UriInterface::class);
        $uriStub->method('getPath')->willReturn($uri);
        $uriStub->method('getQuery')->willReturn(http_build_query($queryParams));

        $request->method('getUri')->willReturn($uriStub);
        $request->method('getQueryParams')->willReturn($queryParams);
        $request->method('getHeaders')->willReturn($headers);
        $request->method('getHeaderLine')->willReturnCallback(
            fn(string $name): string => match ($name) {
                'Cookie' => http_build_query($cookies, '', '; '),
                default => $headers[$name] ?? '',
            },
        );
        $request->method('getCookieParams')->willReturn($cookies);

        $stream = $this->createStub(StreamInterface::class);
        $stream->method('__toString')->willReturn($body);
        $this->configureReadableStream($stream, $body);

        $request->method('getBody')->willReturn($stream);

        return $request;
    }
}
