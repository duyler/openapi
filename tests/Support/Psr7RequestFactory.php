<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;

use function is_string;
use function json_encode;

use const JSON_THROW_ON_ERROR;

final class Psr7RequestFactory
{
    public static function createRequest(
        string $method,
        string $uri,
        array $headers = [],
        string $body = '',
    ): ServerRequestInterface {
        $request = new Psr17Factory()->createServerRequest($method, $uri);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request->withBody(new Psr17Factory()->createStream($body));
    }

    public static function createJsonRequest(string $method, string $uri, string|array $body): ServerRequestInterface
    {
        $payload = is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR);

        return self::createRequest($method, $uri, ['Content-Type' => 'application/json'], $payload);
    }

    /**
     * @param array<string, string> $headers
     */
    public static function createWebhookRequest(
        string $uri,
        string $body,
        array $headers = ['Content-Type' => 'application/json'],
    ): ServerRequestInterface {
        $request = self::createRequest('POST', $uri, $headers, $body);

        if (!isset($headers['Content-Type'])) {
            $request = $request->withHeader('Content-Type', 'application/json');
        }

        return $request;
    }
}
