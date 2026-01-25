<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Exception;

final class OpenApiValidatorSchemaTest extends TestCase
{
    private const SCHEMA_YAML = <<<YAML
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
        email:
          type: string
          format: email
      required:
        - name
        - email
YAML;

    #[Test]
    public function validateSchema_succeeds_on_valid_data(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->build();

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $validator->validateSchema($data, '#/components/schemas/User');
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validateSchema_throws_on_invalid_format(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->build();

        $data = [
            'name' => 'John Doe',
            'email' => 'invalid-email',
        ];

        $this->expectException(Exception::class);
        $validator->validateSchema($data, '#/components/schemas/User');
    }

    #[Test]
    public function validateSchema_throws_on_missing_required_field(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SCHEMA_YAML)
            ->build();

        $data = [
            'email' => 'john@example.com',
        ];

        $this->expectException(Exception::class);
        $validator->validateSchema($data, '#/components/schemas/User');
    }
}
