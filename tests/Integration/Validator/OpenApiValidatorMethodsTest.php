<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Test\Support\Psr7RequestFactory;
use Duyler\OpenApi\Validator\Operation;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OpenApiValidatorMethodsTest extends TestCase
{
    private const string ALL_METHODS_YAML = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: Success
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
      responses:
        '201':
          description: Created
    put:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
      responses:
        '200':
          description: Updated
    patch:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
      responses:
        '200':
          description: Patched
    delete:
      responses:
        '204':
          description: Deleted
    options:
      responses:
        '200':
          description: Options
    head:
      responses:
        '200':
          description: Head
    trace:
      responses:
        '200':
          description: Trace
YAML;

    private const string QUERY_SEARCH_YAML = <<<YAML
openapi: 3.2.0
info:
  title: Test API
  version: 1.0.0
paths:
  /search:
    query:
      summary: Search with body
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
      responses:
        '200':
          description: Search results
YAML;

    private const string COPY_OPERATION_YAML = <<<YAML
openapi: 3.2.0
info:
  title: Test API
  version: 1.0.0
paths:
  /resource:
    additionalOperations:
      COPY:
        summary: Copy resource
        responses:
          '201':
            description: Copied
YAML;

    #[Test]
    #[DataProvider('httpMethodValidationProvider')]
    public function validate_request_and_response_per_http_method(
        string $yaml,
        string $side,
        string $method,
        string $path,
        ?string $contentType = null,
        ?string $body = null,
        int $responseStatus = 200,
    ): void {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        if ('response' === $side) {
            $operation = new Operation($path, $method);
            $response = new Psr17Factory()->createResponse($responseStatus);

            if (null !== $contentType) {
                $response = $response->withHeader('Content-Type', $contentType);
            }

            if (null !== $body) {
                $response = $response->withBody(new Psr17Factory()->createStream($body));
            }

            $validator->validateResponse($response, $operation);
            self::expectNotToPerformAssertions();

            return;
        }

        $request = Psr7RequestFactory::createRequest(
            $method,
            $path,
            null === $contentType ? [] : ['Content-Type' => $contentType],
            $body ?? '',
        );

        $operation = $validator->validateRequest($request);
        self::assertSame($method, $operation->method);
    }

    public static function httpMethodValidationProvider(): iterable
    {
        yield 'request post' => [
            'yaml' => self::ALL_METHODS_YAML,
            'side' => 'request',
            'method' => 'POST',
            'path' => '/test',
            'contentType' => 'application/json',
            'body' => '{"data":"test"}',
        ];

        yield 'request put' => [
            'yaml' => self::ALL_METHODS_YAML,
            'side' => 'request',
            'method' => 'PUT',
            'path' => '/test',
            'contentType' => 'application/json',
            'body' => '{"data":"test"}',
        ];

        yield 'request patch' => [
            'yaml' => self::ALL_METHODS_YAML,
            'side' => 'request',
            'method' => 'PATCH',
            'path' => '/test',
            'contentType' => 'application/json',
            'body' => '{"data":"test"}',
        ];

        yield 'request delete' => [
            'yaml' => self::ALL_METHODS_YAML,
            'side' => 'request',
            'method' => 'DELETE',
            'path' => '/test',
        ];

        yield 'request options' => [
            'yaml' => self::ALL_METHODS_YAML,
            'side' => 'request',
            'method' => 'OPTIONS',
            'path' => '/test',
        ];

        yield 'request head' => [
            'yaml' => self::ALL_METHODS_YAML,
            'side' => 'request',
            'method' => 'HEAD',
            'path' => '/test',
        ];

        yield 'request trace' => [
            'yaml' => self::ALL_METHODS_YAML,
            'side' => 'request',
            'method' => 'TRACE',
            'path' => '/test',
        ];

        yield 'request query' => [
            'yaml' => self::QUERY_SEARCH_YAML,
            'side' => 'request',
            'method' => 'QUERY',
            'path' => '/search',
            'contentType' => 'application/json',
            'body' => '{"query":"test"}',
        ];

        yield 'request copy' => [
            'yaml' => self::COPY_OPERATION_YAML,
            'side' => 'request',
            'method' => 'COPY',
            'path' => '/resource',
        ];

        yield 'request copy case insensitive' => [
            'yaml' => self::COPY_OPERATION_YAML,
            'side' => 'request',
            'method' => 'copy',
            'path' => '/resource',
        ];

        yield 'response post' => [
            'yaml' => self::ALL_METHODS_YAML,
            'side' => 'response',
            'method' => 'POST',
            'path' => '/test',
            'contentType' => 'application/json',
            'body' => '{"success":true}',
            'responseStatus' => 201,
        ];

        yield 'response put' => [
            'yaml' => self::ALL_METHODS_YAML,
            'side' => 'response',
            'method' => 'PUT',
            'path' => '/test',
            'contentType' => 'application/json',
            'body' => '{"success":true}',
        ];

        yield 'response patch' => [
            'yaml' => self::ALL_METHODS_YAML,
            'side' => 'response',
            'method' => 'PATCH',
            'path' => '/test',
            'contentType' => 'application/json',
            'body' => '{"success":true}',
        ];

        yield 'response delete' => [
            'yaml' => self::ALL_METHODS_YAML,
            'side' => 'response',
            'method' => 'DELETE',
            'path' => '/test',
            'responseStatus' => 204,
        ];

        yield 'response options' => [
            'yaml' => self::ALL_METHODS_YAML,
            'side' => 'response',
            'method' => 'OPTIONS',
            'path' => '/test',
        ];

        yield 'response head' => [
            'yaml' => self::ALL_METHODS_YAML,
            'side' => 'response',
            'method' => 'HEAD',
            'path' => '/test',
        ];

        yield 'response trace' => [
            'yaml' => self::ALL_METHODS_YAML,
            'side' => 'response',
            'method' => 'TRACE',
            'path' => '/test',
        ];

        yield 'response query' => [
            'yaml' => self::QUERY_SEARCH_YAML,
            'side' => 'response',
            'method' => 'QUERY',
            'path' => '/search',
            'contentType' => 'application/json',
            'body' => '{"results":[]}',
        ];

        yield 'response copy' => [
            'yaml' => self::COPY_OPERATION_YAML,
            'side' => 'response',
            'method' => 'COPY',
            'path' => '/resource',
            'responseStatus' => 201,
        ];
    }

    #[Test]
    public function validateRequest_with_multiple_additional_operations(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Test API
  version: 1.0.0
paths:
  /resource:
    additionalOperations:
      COPY:
        summary: Copy resource
        responses:
          '201':
            description: Copied
      MOVE:
        summary: Move resource
        responses:
          '201':
            description: Moved
      PURGE:
        summary: Purge cache
        responses:
          '200':
            description: Purged
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $copyRequest = Psr7RequestFactory::createRequest('COPY', '/resource');
        $copyOperation = $validator->validateRequest($copyRequest);
        self::assertSame('COPY', $copyOperation->method);

        $moveRequest = Psr7RequestFactory::createRequest('MOVE', '/resource');
        $moveOperation = $validator->validateRequest($moveRequest);
        self::assertSame('MOVE', $moveOperation->method);

        $purgeRequest = Psr7RequestFactory::createRequest('PURGE', '/resource');
        $purgeOperation = $validator->validateRequest($purgeRequest);
        self::assertSame('PURGE', $purgeOperation->method);
    }
}
