<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Regression;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParameterContentSchemaXorRegressionTest extends TestCase
{
    private const string INLINE_BOTH = <<<'YAML'
openapi: 3.1.0
info:
  title: Inline XOR Probe
  version: '1'
paths:
  /items:
    get:
      operationId: listItems
      parameters:
        - name: filter
          in: query
          content:
            application/json:
              schema:
                type: string
          schema:
            type: integer
      responses:
        '200':
          description: OK
YAML;

    private const string COMPONENTS_BOTH = <<<'YAML'
openapi: 3.1.0
info:
  title: Components XOR Probe
  version: '1'
paths:
  /items:
    get:
      operationId: listItems
      parameters:
        - $ref: '#/components/parameters/Filter'
      responses:
        '200':
          description: OK
components:
  parameters:
    Filter:
      name: filter
      in: query
      content:
        application/json:
          schema:
            type: string
      schema:
        type: integer
YAML;

    private const string INLINE_CONTENT_ONLY = <<<'YAML'
openapi: 3.1.0
info:
  title: Content Only Probe
  version: '1'
paths:
  /items:
    get:
      operationId: listItems
      parameters:
        - name: filter
          in: query
          content:
            application/json:
              schema:
                type: string
      responses:
        '200':
          description: OK
YAML;

    private const string INLINE_SCHEMA_ONLY = <<<'YAML'
openapi: 3.1.0
info:
  title: Schema Only Probe
  version: '1'
paths:
  /items:
    get:
      operationId: listItems
      parameters:
        - name: filter
          in: query
          schema:
            type: integer
      responses:
        '200':
          description: OK
YAML;

    #[Test]
    public function inline_parameter_with_both_content_and_schema_fails_build(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Parameter must not have both "schema" and "content"');

        $this->build(self::INLINE_BOTH);
    }

    #[Test]
    public function components_parameter_with_both_content_and_schema_fails_build(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Parameter must not have both "schema" and "content"');

        $this->build(self::COMPONENTS_BOTH);
    }

    #[Test]
    public function inline_parameter_with_content_only_builds(): void
    {
        $validator = $this->build(self::INLINE_CONTENT_ONLY);

        self::assertNotNull($validator->getDocument());
    }

    #[Test]
    public function inline_parameter_with_schema_only_builds(): void
    {
        $validator = $this->build(self::INLINE_SCHEMA_ONLY);

        self::assertNotNull($validator->getDocument());
    }

    private function build(string $spec): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString($spec)
            ->build();
    }
}
