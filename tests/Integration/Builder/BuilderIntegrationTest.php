<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Builder;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Error\Formatter\DetailedFormatter;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;

final class BuilderIntegrationTest extends TestCase
{
    private const string COMPLETE_YAML = <<<YAML
openapi: 3.0.3
info:
  title: Complete API
  version: 1.0.0
  description: A complete API example
paths:
  /users:
    get:
      summary: List users
      operationId: listUsers
      parameters:
        - name: limit
          in: query
          description: Maximum number of users to return
          required: false
          schema:
            type: integer
            minimum: 1
            maximum: 100
            default: 10
        - name: offset
          in: query
          schema:
            type: integer
            minimum: 0
            default: 0
      responses:
        '200':
          description: A list of users
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items:
                      type: object
                      properties:
                        id:
                          type: integer
                        name:
                          type: string
                        email:
                          type: string
                          format: email
                  total:
                    type: integer
    post:
      summary: Create user
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
                  maxLength: 100
                email:
                  type: string
                  format: email
      responses:
        '201':
          description: User created
          content:
            application/json:
              schema:
                type: object
                properties:
                  id:
                    type: integer
                  name:
                    type: string
                  email:
                    type: string
        '400':
          description: Invalid input
  /users/{id}:
    get:
      summary: Get user by ID
      operationId: getUser
      parameters:
        - name: id
          in: path
          required: true
          description: User ID
          schema:
            type: integer
      responses:
        '200':
          description: User found
          content:
            application/json:
              schema:
                type: object
                properties:
                  id:
                    type: integer
                  name:
                    type: string
                  email:
                    type: string
        '404':
          description: User not found
YAML;

    #[Test]
    public function build_complete_validator(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::COMPLETE_YAML)
            ->build();

        $this->assertSame('Complete API', $validator->document->info->title);
        $this->assertSame('1.0.0', $validator->document->info->version);
    }

    #[Test]
    public function validate_real_openapi_spec(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::COMPLETE_YAML)
            ->build();

        $this->assertSame('Complete API', $validator->document->info->title);
        $this->assertSame('1.0.0', $validator->document->info->version);
    }

    #[Test]
    public function handle_all_builder_options(): void
    {
        $pool = new ValidatorPool();
        $formatter = new DetailedFormatter();

        $customValidator = new class implements FormatValidatorInterface {
            public function validate(mixed $data): void
            {
                // Custom validation logic
            }
        };

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::COMPLETE_YAML)
            ->withValidatorPool($pool)
            ->withErrorFormatter($formatter)
            ->withFormat('string', 'custom-phone', $customValidator)
            ->enableCoercion()
            ->enableNullableAsType()
            ->build();

        $this->assertSame('Complete API', $validator->document->info->title);
    }

    #[Test]
    public function maintain_immutability(): void
    {
        $builder1 = OpenApiValidatorBuilder::create()->fromYamlString(self::COMPLETE_YAML);
        $builder2 = $builder1->enableCoercion();
        $builder3 = $builder2->enableNullableAsType();

        $validator1 = $builder1->build();
        $validator2 = $builder2->build();
        $validator3 = $builder3->build();

        // Each validator should be independent
        $this->assertNotSame($validator1, $validator2);
        $this->assertNotSame($validator2, $validator3);

        // Validators should have different configurations
        $this->assertFalse($validator1->coercion);
        $this->assertTrue($validator2->coercion);
        $this->assertTrue($validator3->coercion);

        $this->assertTrue($validator1->nullableAsType);
        $this->assertTrue($validator2->nullableAsType);
        $this->assertTrue($validator3->nullableAsType);
    }

    #[Test]
    public function handle_validation_with_custom_formatter(): void
    {
        $formatter = new DetailedFormatter();

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::COMPLETE_YAML)
            ->withErrorFormatter($formatter)
            ->build();

        $this->assertSame('Complete API', $validator->document->info->title);
        $this->assertInstanceOf(DetailedFormatter::class, $validator->errorFormatter);
    }

    #[Test]
    public function support_multiple_validators_from_same_builder(): void
    {
        $builder = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::COMPLETE_YAML);

        $validator1 = $builder->build();
        $validator2 = $builder->build();

        $this->assertNotSame($validator1, $validator2);
        $this->assertSame($validator1->document->info->title, $validator2->document->info->title);
    }

    #[Test]
    public function validate_complex_request_with_all_components(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::COMPLETE_YAML)
            ->enableCoercion()
            ->build();

        $this->assertTrue($validator->coercion);
        $this->assertSame('Complete API', $validator->document->info->title);
    }

    #[Test]
    public function handle_json_and_yaml_interchangeably(): void
    {
        $yaml = "openapi: 3.0.3\ninfo:\n  title: Test\n  version: 1.0.0\npaths:\n  /test:\n    get:\n      summary: Test\n      responses:\n        '200':\n          description: OK";

        $validatorFromYaml = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $json = json_encode([
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Test',
                'version' => '1.0.0',
            ],
            'paths' => [
                '/test' => [
                    'get' => [
                        'summary' => 'Test',
                        'responses' => [
                            '200' => [
                                'description' => 'OK',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $validatorFromJson = OpenApiValidatorBuilder::create()
            ->fromJsonString($json)
            ->build();

        $this->assertSame(
            $validatorFromYaml->document->info->title,
            $validatorFromJson->document->info->title,
        );
    }
}
