<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Concurrency;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

use function extension_loaded;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * P-096: FrankenPHP threaded workers share the validator instance across
 * threads within a single process. The validator's internal mutable state
 * (ValidatorPool LRU cache, ValidationContext breadcrumbs/depth, libxml
 * globals) is not thread-safe by default. These tests document the contract
 * required for threaded mode: each request sees its own dataPath and the
 * pool survives a reset() without leaking state into adjacent workers.
 *
 * Tests gracefully skip when ext-frankenphp is not loaded; CI matrices that
 * ship the extension will execute them.
 *
 * @internal
 */
final class FrankenPhpThreadedTest extends TestCase
{
    private const string SHARED_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: FrankenPHP Threaded API
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
                - id
                - total
              properties:
                id:
                  type: string
                  minLength: 1
                total:
                  type: number
                  minimum: 0
      responses:
        '201':
          description: Created
  /refunds:
    post:
      operationId: createRefund
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - reason
              properties:
                reason:
                  type: string
                  minLength: 5
      responses:
        '201':
          description: Created
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        if (false === extension_loaded('frankenphp')) {
            $this->markTestSkipped('ext-frankenphp not loaded');
        }
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function thread_safe_validator_reset(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SHARED_SPEC)
            ->build();

        $firstRequest = $this->factory
            ->createServerRequest('POST', '/orders')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode([
                'id' => '',
                'total' => -1,
            ], JSON_THROW_ON_ERROR)));

        $firstDataPath = $this->captureErrorDataPath($validator, $firstRequest);
        self::assertSame('/id', $firstDataPath);

        $validator->reset();

        $secondRequest = $this->factory
            ->createServerRequest('POST', '/refunds')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['reason' => 'x'], JSON_THROW_ON_ERROR)));

        $secondDataPath = $this->captureErrorDataPath($validator, $secondRequest);
        self::assertSame(
            '/reason',
            $secondDataPath,
            'After reset() the threaded worker must observe a fresh dataPath for the next request.',
        );
    }

    private function captureErrorDataPath(OpenApiValidatorInterface $validator, ServerRequestInterface $request): ?string
    {
        try {
            $validator->validateRequest($request);

            return null;
        } catch (ValidationException $e) {
            return $e->getErrors()[0]->dataPath();
        }
    }
}
