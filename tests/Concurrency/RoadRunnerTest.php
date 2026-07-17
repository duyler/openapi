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

use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * P-096: RoadRunner uses a prefork execution model (one worker per process),
 * so the validator instance is implicitly isolated between workers. These
 * tests document the prefork reuse contract: a single worker that reuses
 * the validator instance across many requests does not accumulate state
 * from previous requests, and reset() leaves the worker in a clean state.
 *
 * No special extension is required (RoadRunner ships as a binary that talks
 * to PHP via the standard CGI/Worker protocol); the tests therefore run on
 * every PHP 8.4+ runtime. They are grouped under Concurrency because they
 * describe the long-running reuse contract rather than a single-shot
 * request lifecycle.
 *
 * @internal
 */
final class RoadRunnerTest extends TestCase
{
    private const string SHARED_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: RoadRunner Reuse API
  version: 1.0.0
paths:
  /products:
    post:
      operationId: createProduct
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - sku
                - price
              properties:
                sku:
                  type: string
                  pattern: '^[A-Z]{3}-[0-9]+$'
                price:
                  type: number
                  minimum: 0
      responses:
        '201':
          description: Created
  /coupons:
    post:
      operationId: createCoupon
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - code
              properties:
                code:
                  type: string
                  minLength: 4
      responses:
        '201':
          description: Created
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function worker_reuse_does_not_leak_state(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SHARED_SPEC)
            ->build();

        $firstBad = $this->factory
            ->createServerRequest('POST', '/products')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode([
                'sku' => 'bad-sku',
                'price' => 10,
            ], JSON_THROW_ON_ERROR)));

        $firstDataPath = $this->captureErrorDataPath($validator, $firstBad);
        self::assertSame('/sku', $firstDataPath);

        $secondBad = $this->factory
            ->createServerRequest('POST', '/coupons')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['code' => 'x'], JSON_THROW_ON_ERROR)));

        $secondDataPath = $this->captureErrorDataPath($validator, $secondBad);
        self::assertSame(
            '/code',
            $secondDataPath,
            'Worker reuse in RoadRunner must NOT leak /sku from the previous request.',
        );

        $validator->reset();

        $replayedDataPath = $this->captureErrorDataPath($validator, $firstBad);
        self::assertSame(
            '/sku',
            $replayedDataPath,
            'After reset() the validator must produce the same dataPath for a replayed failing request.',
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
