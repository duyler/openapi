<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Performance;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Test\Support\Psr7RequestStubFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValidationBenchTest extends TestCase
{
    use Psr7RequestStubFactory;

    #[Test]
    public function benchmark_simple_validation(): void
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
                  minLength: 1
                  maxLength: 100
                email:
                  type: string
                  format: email
              required:
                - name
                - email
      responses:
        '201':
          description: Created
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $iterations = 100;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $request = $this->createStubRequest(
                'POST',
                '/users',
                ['Content-Type' => 'application/json'],
                '{"name": "John Doe", "email": "john@example.com"}',
            );

            $validator->validateRequest($request);
        }

        $duration = (microtime(true) - $start) * 1000.0;
        $avgDuration = $duration / (float) $iterations;

        self::assertLessThan(10, $avgDuration, 'Average validation should be under 10ms');
    }

    #[Test]
    public function benchmark_schema_validation(): void
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
        id:
          type: integer
        name:
          type: string
        email:
          type: string
          format: email
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $iterations = 100;
        $start = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $validator->validateSchema(
                ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
                '#/components/schemas/User',
            );
        }

        $duration = (microtime(true) - $start) * 1000.0;
        $avgDuration = $duration / (float) $iterations;

        self::assertLessThan(5, $avgDuration, 'Average schema validation should be under 5ms');
    }
}
