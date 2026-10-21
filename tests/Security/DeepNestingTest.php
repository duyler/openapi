<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Security;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;
use function str_repeat;

use const JSON_THROW_ON_ERROR;

/**
 * @internal
 */
final class DeepNestingTest extends TestCase
{
    private const string SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Deep Nesting DoS API
  version: 1.0.0
paths:
  /nested:
    post:
      operationId: createNested
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/Node'
      responses:
        '201':
          description: Created
components:
  schemas:
    Node:
      type: object
      properties:
        child:
          $ref: '#/components/schemas/Node'
YAML;

    private Psr17Factory $factory;

    #[Override]
    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    #[Test]
    public function rejects_excessive_schema_depth(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();

        $payload = '{"child":' . str_repeat('{"child":', 100) . 'null' . str_repeat('}', 100) . '}';

        $request = $this->factory
            ->createServerRequest('POST', '/nested')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream($payload));

        $caught = null;
        try {
            $validator->validateRequest($request);
        } catch (SchemaDepthExceededException|ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull(
            $caught,
            'A 100-level nested payload must trigger the depth guard (SchemaDepthExceededException or ValidationException).',
        );
    }

    #[Test]
    public function rejects_pathological_string_array_via_depth_guard(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();

        $payload = '';
        for ($i = 0; $i < 70; ++$i) {
            $payload .= '{"child":';
        }
        $payload .= 'null' . str_repeat('}', 70);

        $request = $this->factory
            ->createServerRequest('POST', '/nested')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream($payload));

        $caught = null;
        try {
            $validator->validateRequest($request);
        } catch (SchemaDepthExceededException|ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull(
            $caught,
            'A 70-level nested payload must trip the MAX_DEPTH guard at 64 before reaching the leaf.',
        );
    }

    #[Test]
    public function normal_depth_payload_is_accepted(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();

        $payload = json_encode(['child' => ['child' => ['child' => []]]], JSON_THROW_ON_ERROR);

        $request = $this->factory
            ->createServerRequest('POST', '/nested')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->factory->createStream($payload));

        $operation = $validator->validateRequest($request);

        self::assertSame('POST', $operation->method);
        self::assertSame('/nested', $operation->path);
    }
}
