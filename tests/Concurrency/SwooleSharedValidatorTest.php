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
 * P-096: Swoole coroutine isolation. The validator instance is shared across
 * coroutines in long-running Swoole workers. These tests prove that the
 * per-request ValidationContext (dataPath breadcrumbs and depth counter)
 * does not cross-contaminate between two sequential validations issued from
 * the same coroutine scheduler slice, and that the documented contract holds
 * under the Swoole execution model.
 *
 * Tests gracefully skip when ext-swoole is not loaded; this is the standard
 * pattern for runtime-conditional test coverage in Docker-based CI matrices.
 *
 * @internal
 */
final class SwooleSharedValidatorTest extends TestCase
{
    private const string SHARED_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Swoole Isolation API
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
              $ref: '#/components/schemas/User'
      responses:
        '201':
          description: Created
  /items:
    post:
      operationId: createItem
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/Item'
      responses:
        '201':
          description: Created
components:
  schemas:
    User:
      type: object
      required:
        - name
        - contact
      properties:
        name:
          type: string
          minLength: 3
        contact:
          $ref: '#/components/schemas/Contact'
    Contact:
      type: object
      required:
        - label
      properties:
        label:
          type: string
          minLength: 2
    Item:
      type: object
      required:
        - title
      properties:
        title:
          type: string
          minLength: 1
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        if (false === extension_loaded('swoole')) {
            $this->markTestSkipped('ext-swoole not loaded');
        }
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function isolated_datapath_under_coroutines(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SHARED_SPEC)
            ->build();

        $firstRequest = $this->factory
            ->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode([
                'name' => 'Alice',
                'contact' => ['label' => 'x'],
            ], JSON_THROW_ON_ERROR)));

        $firstDataPath = $this->captureErrorDataPath($validator, $firstRequest);
        self::assertSame('/contact/label', $firstDataPath);

        $secondRequest = $this->factory
            ->createServerRequest('POST', '/items')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode(['title' => ''], JSON_THROW_ON_ERROR)));

        $secondDataPath = $this->captureErrorDataPath($validator, $secondRequest);
        self::assertSame(
            '/title',
            $secondDataPath,
            'Second request dataPath must NOT leak /contact/label from the first coroutine slice.',
        );
    }

    #[Test]
    public function validator_pool_does_not_race_under_coroutines(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SHARED_SPEC)
            ->build();

        $okRequest = $this->factory
            ->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream(json_encode([
                'name' => 'Alice',
                'contact' => ['label' => 'work'],
            ], JSON_THROW_ON_ERROR)));

        $operation = $validator->validateRequest($okRequest);
        self::assertSame('POST', $operation->method);
        self::assertSame('/users', $operation->path);

        $validator->reset();

        $reused = $validator->validateRequest($okRequest);
        self::assertSame('/users', $reused->path, 'Validator must remain usable after reset() under coroutine reuse.');
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
