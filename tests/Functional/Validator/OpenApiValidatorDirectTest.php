<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Operation;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Nyholm\Psr7\Factory\Psr17Factory;

final class OpenApiValidatorDirectTest extends TestCase
{
    private const string SIMPLE_YAML = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users/{id}:
    get:
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Success
          content:
            application/json:
              schema:
                type: object
                properties:
                  id:
                    type: string
                  name:
                    type: string
                required:
                  - id
                  - name
YAML;

    #[Test]
    public function validate_response_throws_on_invalid_data(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SIMPLE_YAML)
            ->build();

        $operation = new Operation('/users/{id}', 'GET');
        $response = new Psr17Factory()
            ->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new Psr17Factory()->createStream(json_encode(['invalid' => 'data'])));

        $this->expectException(ValidationException::class);
        $validator->validateResponse($response, $operation);
    }

    #[Test]
    public function validate_response_succeeds_on_valid_data(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SIMPLE_YAML)
            ->build();

        $operation = new Operation('/users/{id}', 'GET');
        $response = new Psr17Factory()
            ->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new Psr17Factory()->createStream(json_encode(['id' => '123', 'name' => 'John'])));

        $validator->validateResponse($response, $operation);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function get_formatted_errors_returns_formatted_message(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SIMPLE_YAML)
            ->build();

        $operation = new Operation('/users/{id}', 'GET');
        $response = new Psr17Factory()
            ->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(new Psr17Factory()->createStream(json_encode(['invalid' => 'data'])));

        try {
            $validator->validateResponse($response, $operation);
            $this->fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $formatted = (new SimpleFormatter())->formatException($e);
            $this->assertIsString($formatted);
            $this->assertNotEmpty($formatted);
        }
    }
}
