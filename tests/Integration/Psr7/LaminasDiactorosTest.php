<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Psr7;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * P-100: PSR-7 alternate implementation. The validator targets the PSR-7
 * interfaces (ServerRequestInterface, ResponseInterface, StreamInterface)
 * and MUST work with any compliant implementation, not only nyholm/psr7.
 *
 * These tests build the validator once and exercise it with Laminas
 * Diactoros request objects (laminas/laminas-diactoros ^3.3), proving
 * that the public contract is implementation-agnostic.
 *
 * @internal
 */
final class LaminasDiactorosTest extends TestCase
{
    private const string SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Laminas Diactoros Portability API
  version: 1.0.0
paths:
  /orders:
    post:
      operationId: createOrder
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - sku
                - quantity
              properties:
                sku:
                  type: string
                  pattern: '^[A-Z]{3}-[0-9]+$'
                quantity:
                  type: integer
                  minimum: 1
      responses:
        '201':
          description: Created
  /health:
    get:
      operationId: health
      responses:
        '200':
          description: OK
YAML;

    #[Test]
    public function validates_request_with_laminas_diactoros_implementation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();

        $requestFactory = new ServerRequestFactory();
        $streamFactory = new StreamFactory();

        $request = $requestFactory->createServerRequest('POST', '/orders', [])
            ->withHeader('Content-Type', 'application/json')
            ->withBody($streamFactory->createStream(json_encode([
                'sku' => 'ABC-123',
                'quantity' => 5,
            ], JSON_THROW_ON_ERROR)));

        $operation = $validator->validateRequest($request);

        self::assertSame('POST', $operation->method);
        self::assertSame('/orders', $operation->path);
    }

    #[Test]
    public function surfaces_validation_error_with_laminas_diactoros_implementation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();

        $requestFactory = new ServerRequestFactory();
        $streamFactory = new StreamFactory();

        $request = $requestFactory->createServerRequest('POST', '/orders', [])
            ->withHeader('Content-Type', 'application/json')
            ->withBody($streamFactory->createStream(json_encode([
                'sku' => 'bad',
                'quantity' => 0,
            ], JSON_THROW_ON_ERROR)));

        $this->expectException(ValidationException::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function validates_get_request_with_laminas_diactoros_implementation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();

        $requestFactory = new ServerRequestFactory();
        $request = $requestFactory->createServerRequest('GET', '/health', []);

        $operation = $validator->validateRequest($request);

        self::assertSame('GET', $operation->method);
        self::assertSame('/health', $operation->path);
    }
}
