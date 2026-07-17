<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Psr7;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use GuzzleHttp\Psr7\HttpFactory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * P-100: PSR-7 alternate implementation. The validator targets the PSR-7
 * interfaces (ServerRequestInterface, ResponseInterface, StreamInterface)
 * and MUST work with any compliant implementation, not only nyholm/psr7.
 *
 * These tests build the validator once and exercise it with Guzzle PSR-7
 * request objects (guzzlehttp/psr7 ^2.6), proving that the public contract
 * is implementation-agnostic. nyholm/psr7 remains the canonical test-time
 * implementation; this suite documents the portability guarantee.
 *
 * @internal
 */
final class GuzzlePsr7Test extends TestCase
{
    private const string SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Guzzle PSR-7 Portability API
  version: 1.0.0
paths:
  /users:
    post:
      operationId: createUser
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - name
                - email
              properties:
                name:
                  type: string
                  minLength: 1
                email:
                  type: string
                  format: email
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

    private HttpFactory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new HttpFactory();
    }

    #[Test]
    public function validates_request_with_guzzle_psr7_implementation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode([
                'name' => 'Alice',
                'email' => 'alice@example.com',
            ], JSON_THROW_ON_ERROR)));

        $operation = $validator->validateRequest($request);

        self::assertSame('POST', $operation->method);
        self::assertSame('/users', $operation->path);
    }

    #[Test]
    public function surfaces_validation_error_with_guzzle_psr7_implementation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();

        $request = $this->factory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode([
                'name' => '',
                'email' => 'not-an-email',
            ], JSON_THROW_ON_ERROR)));

        $this->expectException(ValidationException::class);

        $validator->validateRequest($request);
    }

    #[Test]
    public function validates_get_request_with_guzzle_psr7_implementation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();

        $request = $this->factory->createServerRequest('GET', '/health');

        $operation = $validator->validateRequest($request);

        self::assertSame('GET', $operation->method);
        self::assertSame('/health', $operation->path);
    }
}
