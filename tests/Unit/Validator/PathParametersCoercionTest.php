<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;

use const PHP_URL_PATH;
use const PHP_URL_QUERY;

final class PathParametersCoercionTest extends TestCase
{
    private const string YAML_WITH_INTEGER_PATH_PARAM = <<<YAML
openapi: 3.0.0
info:
  title: 'API'
  version: 1.0.0
paths:
  '/album/{albumId}':
    get:
      operationId: album.getById
      parameters:
        -
          name: albumId
          in: path
          required: true
          schema:
            type: integer
            minimum: 1
      responses:
        '200':
          description: Success
YAML;

    #[Test]
    public function throw_type_error_for_integer_path_param_without_coercion(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::YAML_WITH_INTEGER_PATH_PARAM)
            ->build();

        $request = $this->createMockServerRequest('GET', '/album/666');

        $this->expectException(TypeMismatchError::class);
        $this->expectExceptionMessage('Expected type "integer", but got "string"');

        $validator->validateRequest($request);
    }

    #[Test]
    public function validate_integer_path_param_with_coercion(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::YAML_WITH_INTEGER_PATH_PARAM)
            ->enableCoercion()
            ->build();

        $request = $this->createMockServerRequest('GET', '/album/666');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/album/{albumId}', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function validate_number_path_param_with_coercion(): void
    {
        $yaml = <<<YAML
openapi: 3.0.0
info:
  title: 'API'
  version: 1.0.0
paths:
  '/product/{price}':
    get:
      operationId: product.getByPrice
      parameters:
        -
          name: price
          in: path
          required: true
          schema:
            type: number
            minimum: 0
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->createMockServerRequest('GET', '/product/19.99');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/product/{price}', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function validate_boolean_path_param_with_coercion(): void
    {
        $yaml = <<<YAML
openapi: 3.0.0
info:
  title: 'API'
  version: 1.0.0
paths:
  '/settings/{enabled}':
    get:
      operationId: settings.get
      parameters:
        -
          name: enabled
          in: path
          required: true
          schema:
            type: boolean
      responses:
        '200':
          description: Success
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->createMockServerRequest('GET', '/settings/true');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/settings/{enabled}', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    private function createMockServerRequest(string $method, string $uri): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);

        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($this->createMockUri($uri));
        $request->method('getHeaders')->willReturn([]);
        $request->method('getHeaderLine')->willReturn('');
        $request->method('getBody')->willReturn($this->createMockStream(''));

        return $request;
    }

    private function createMockUri(string $uri): UriInterface
    {
        $uriMock = $this->createMock(UriInterface::class);
        $uriMock->method('getPath')->willReturn(parse_url($uri, PHP_URL_PATH) ?? $uri);
        $uriMock->method('getQuery')->willReturn(parse_url($uri, PHP_URL_QUERY) ?? '');

        return $uriMock;
    }

    private function createMockStream(string $content): StreamInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn($content);

        return $stream;
    }
}
