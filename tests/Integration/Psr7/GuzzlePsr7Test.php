<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Psr7;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use GuzzleHttp\Psr7\HttpFactory;
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
        if (false === interface_exists(RequestInterface::class) || false === class_exists(HttpFactory::class)) {
            self::markTestSkipped('GuzzlePsr7Test requires the guzzlehttp/psr7 dev dependency (see composer.json require-dev)');
        }

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
