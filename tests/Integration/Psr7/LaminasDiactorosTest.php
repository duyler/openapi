<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Psr7;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

use function class_exists;
use function interface_exists;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
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

    #[Override]
    protected function setUp(): void
    {
        if (false === interface_exists(RequestInterface::class) || false === class_exists(ServerRequestFactory::class)) {
            self::markTestSkipped('LaminasDiactorosTest requires the laminas/laminas-diactoros dev dependency (see composer.json require-dev)');
        }
    }

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
