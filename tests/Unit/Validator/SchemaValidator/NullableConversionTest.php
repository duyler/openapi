<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NullableConversionTest extends TestCase
{
    #[Test]
    public function openapi_31_nullable_true_converts_to_type_array(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
                  nullable: true
              required:
                - name
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $op = $validator->document->paths->paths['/users']->post;
        $schema = $op->requestBody->content->mediaTypes['application/json']->schema;

        $this->assertSame(['string', 'null'], $schema->properties['name']->type);
    }

    #[Test]
    public function openapi_31_nullable_validates_null_value(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
components:
  schemas:
    User:
      type: object
      properties:
        name:
          type: string
          nullable: true
      required:
        - name
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $validator->validateSchema(['name' => null], '#/components/schemas/User');

        $this->assertTrue(true);
    }

    #[Test]
    public function openapi_31_nullable_validates_non_null_value(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
components:
  schemas:
    User:
      type: object
      properties:
        name:
          type: string
          nullable: true
      required:
        - name
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $validator->validateSchema(['name' => 'John'], '#/components/schemas/User');

        $this->assertTrue(true);
    }

    #[Test]
    public function openapi_30_nullable_remains_boolean(): void
    {
        $yaml = <<<YAML
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
                  nullable: true
              required:
                - name
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $op = $validator->document->paths->paths['/users']->post;
        $schema = $op->requestBody->content->mediaTypes['application/json']->schema;

        $this->assertSame('string', $schema->properties['name']->type);
        $this->assertTrue($schema->properties['name']->nullable);
    }

    #[Test]
    public function openapi_31_nullable_false_does_not_convert(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
                  nullable: false
              required:
                - name
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $op = $validator->document->paths->paths['/users']->post;
        $schema = $op->requestBody->content->mediaTypes['application/json']->schema;

        $this->assertSame('string', $schema->properties['name']->type);
    }
}
