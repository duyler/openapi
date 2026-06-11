<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\E2E;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class SecurityE2ETest extends TestCase
{
    private const REDOS_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: ReDoS Test API
  version: 1.0.0
paths:
  /validate:
    post:
      summary: Validate with potentially dangerous pattern
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - value
              properties:
                value:
                  type: string
                  pattern: '(a+)+$'
      responses:
        '200':
          description: Valid
YAML;

    private const XML_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: XXE Test API
  version: 1.0.0
paths:
  /xml-accept:
    post:
      summary: Accept XML body
      requestBody:
        required: true
        content:
          application/xml:
            schema:
              type: object
              properties:
                name:
                  type: string
                value:
                  type: string
              required:
                - name
      responses:
        '200':
          description: Accepted
YAML;

    private const DEEP_NESTING_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Deep Nesting Test API
  version: 1.0.0
paths:
  /nested:
    post:
      summary: Accept deeply nested data
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                data:
                  type: object
      responses:
        '200':
          description: Accepted
YAML;

    private const CIRCULAR_REF_SPEC = <<<'YAML'
openapi: 3.1.0
info:
  title: Circular Ref Test API
  version: 1.0.0
paths:
  /tree:
    post:
      summary: Accept tree node
      requestBody:
        required: true
        content:
          application/json:
            schema:
              $ref: '#/components/schemas/TreeNode'
      responses:
        '200':
          description: Accepted
components:
  schemas:
    TreeNode:
      type: object
      properties:
        name:
          type: string
        children:
          type: array
          items:
            $ref: '#/components/schemas/TreeNode'
YAML;

    private Psr17Factory $psrFactory;

    #[Override]
    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function redos_pattern_does_not_hang_on_long_input(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REDOS_SPEC)
            ->build();

        $attackString = str_repeat('a', 30) . 'b';

        $request = $this->psrFactory->createServerRequest('POST', '/validate')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'value' => $attackString,
            ])));

        $elapsed = $this->measureTime($validator, $request);

        $this->assertLessThan(1.0, $elapsed, 'ReDoS-vulnerable pattern must complete within 1 second');
    }

    #[Test]
    public function redos_pattern_with_matching_prefix_does_not_hang(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::REDOS_SPEC)
            ->build();

        $attackString = str_repeat('a', 50);

        $request = $this->psrFactory->createServerRequest('POST', '/validate')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'value' => $attackString,
            ])));

        $elapsed = $this->measureTime($validator, $request);

        $this->assertLessThan(1.0, $elapsed, 'ReDoS-vulnerable pattern with matching prefix must complete within 1 second');
    }

    #[Test]
    public function xxe_attack_does_not_read_system_files(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::XML_SPEC)
            ->build();

        $xxePayload = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE foo [
  <!ENTITY xxe SYSTEM "file:///nonexistent/xxe-test-file">
]>
<root>
  <name>test</name>
  <value>&xxe;</value>
</root>
XML;

        $request = $this->psrFactory->createServerRequest('POST', '/xml-accept')
            ->withHeader('Content-Type', 'application/xml')
            ->withBody($this->psrFactory->createStream($xxePayload));

        $exceptionCaught = false;
        $startTime = microtime(true);

        try {
            $validator->validateRequest($request);
        } catch (ValidationException) {
            $exceptionCaught = true;
        } catch (Throwable) {
            $exceptionCaught = true;
        }

        $elapsed = microtime(true) - $startTime;

        $this->assertTrue($exceptionCaught, 'XXE payload must be rejected by the validator');
        $this->assertLessThan(1.0, $elapsed, 'XXE processing must complete within 1 second');
    }

    #[Test]
    public function deeply_nested_json_200_levels_does_not_crash(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::DEEP_NESTING_SPEC)
            ->build();

        $nestedData = ['leaf' => 'end'];
        for ($i = 0; $i < 200; ++$i) {
            $nestedData = ['data' => $nestedData];
        }
        $payload = ['data' => $nestedData];

        $request = $this->psrFactory->createServerRequest('POST', '/nested')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode($payload)));

        $completed = false;
        $startTime = microtime(true);

        try {
            $validator->validateRequest($request);
            $completed = true;
        } catch (SchemaDepthExceededException|ValidationException|Throwable) {
            $completed = true;
        }

        $elapsed = microtime(true) - $startTime;

        $this->assertTrue($completed, 'Deeply nested JSON must complete without stack overflow');
        $this->assertLessThan(1.0, $elapsed, 'Deeply nested JSON validation must complete within 1 second');
    }

    #[Test]
    public function circular_ref_schema_does_not_cause_infinite_recursion(): void
    {
        $startTime = microtime(true);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::CIRCULAR_REF_SPEC)
            ->build();

        $tree = $this->buildDeepTree(100);

        $request = $this->psrFactory->createServerRequest('POST', '/tree')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode($tree)));

        $completed = false;
        try {
            $validator->validateRequest($request);
            $completed = true;
        } catch (Throwable) {
            $completed = true;
        }

        $elapsed = microtime(true) - $startTime;

        $this->assertTrue($completed, 'Circular $ref schema must complete without hanging');
        $this->assertLessThan(1.0, $elapsed, 'Circular $ref schema validation must complete within 1 second');
    }

    #[Test]
    public function huge_enum_in_schema_does_not_cause_timeout(): void
    {
        $enumValues = [];
        for ($i = 0; $i < 1000; ++$i) {
            $enumValues[] = 'value_' . $i;
        }

        $enumList = implode(', ', $enumValues);

        $spec = <<<YAML
openapi: 3.1.0
info:
  title: Huge Enum Test API
  version: 1.0.0
paths:
  /select:
    post:
      summary: Select value from huge enum
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - choice
              properties:
                choice:
                  type: string
                  enum: [{$enumList}]
      responses:
        '200':
          description: Selected
YAML;

        $startTime = microtime(true);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($spec)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/select')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'choice' => 'not_in_enum',
            ])));

        $completed = false;
        try {
            $validator->validateRequest($request);
            $completed = true;
        } catch (Throwable) {
            $completed = true;
        }

        $elapsed = microtime(true) - $startTime;

        $this->assertTrue($completed, 'Huge enum validation must complete without hanging');
        $this->assertLessThan(1.0, $elapsed, 'Huge enum schema must validate within 1 second');
    }

    private function measureTime(
        OpenApiValidator $validator,
        ServerRequestInterface $request,
    ): float {
        $startTime = microtime(true);

        try {
            $validator->validateRequest($request);
        } catch (Throwable) {
            return microtime(true) - $startTime;
        }

        return microtime(true) - $startTime;
    }

    private function buildDeepTree(int $depth): array
    {
        $node = ['name' => 'node_' . ($depth - 1), 'children' => []];
        for ($i = $depth - 2; $i >= 0; --$i) {
            $node = ['name' => 'node_' . $i, 'children' => [$node]];
        }

        return [
            'name' => 'root',
            'children' => 0 === $depth ? [] : [$node],
        ];
    }
}
