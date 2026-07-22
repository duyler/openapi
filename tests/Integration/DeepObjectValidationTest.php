<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\RequiredError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeepObjectValidationTest extends TestCase
{
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function test_deep_object_simple(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Color API
  version: 1.0.0
paths:
  /colors:
    get:
      parameters:
        - name: color
          in: query
          style: deepObject
          explode: true
          schema:
            type: object
            properties:
              R:
                type: string
              G:
                type: string
              B:
                type: string
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/colors?color[R]=100&color[G]=200&color[B]=150',
        );

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/colors', $operation->path);
    }

    #[Test]
    public function test_deep_object_nested(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: User API
  version: 1.0.0
paths:
  /users:
    get:
      parameters:
        - name: user
          in: query
          style: deepObject
          explode: true
          schema:
            type: object
            properties:
              name:
                type: object
                properties:
                  first:
                    type: string
              age:
                type: string
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/users?user[name][first]=Alice&user[age]=30',
        );

        $operation = $validator->validateRequest($request);

        $this->assertSame('/users', $operation->path);
    }

    #[Test]
    public function test_deep_object_url_encoded(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Color API
  version: 1.0.0
paths:
  /colors:
    get:
      parameters:
        - name: color
          in: query
          style: deepObject
          explode: true
          schema:
            type: object
            properties:
              R:
                type: string
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/colors?color%5BR%5D=100',
        );

        $operation = $validator->validateRequest($request);

        $this->assertSame('/colors', $operation->path);
    }

    #[Test]
    public function test_deep_object_against_schema(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Color API
  version: 1.0.0
paths:
  /colors:
    get:
      parameters:
        - name: color
          in: query
          style: deepObject
          explode: true
          required: true
          schema:
            type: object
            properties:
              R:
                type: string
              G:
                type: string
              B:
                type: string
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/colors?color[R]=100&color[G]=200&color[B]=150',
        );

        $operation = $validator->validateRequest($request);

        $this->assertSame('/colors', $operation->path);
    }

    #[Test]
    public function test_deep_object_invalid_value_rejected(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Color API
  version: 1.0.0
paths:
  /colors:
    get:
      parameters:
        - name: color
          in: query
          style: deepObject
          explode: true
          schema:
            type: object
            properties:
              R:
                type: integer
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/colors?color[R]=abc',
        );

        $caught = null;

        try {
            $validator->validateRequest($request);
            self::fail('Expected ValidationException for non-integer value');
        } catch (ValidationException $e) {
            $caught = $e->getErrors()[0] ?? null;
        }

        self::assertInstanceOf(TypeMismatchError::class, $caught);
    }

    #[Test]
    public function test_deep_object_missing_required_property(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Color API
  version: 1.0.0
paths:
  /colors:
    get:
      parameters:
        - name: color
          in: query
          style: deepObject
          explode: true
          schema:
            type: object
            properties:
              R:
                type: string
              G:
                type: string
              B:
                type: string
            required:
              - R
              - G
              - B
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/colors?color[R]=100',
        );

        $caught = null;

        try {
            $validator->validateRequest($request);
            self::fail('Expected ValidationException for missing required property');
        } catch (ValidationException $e) {
            foreach ($e->getErrors() as $error) {
                if ($error instanceof RequiredError) {
                    $caught = $error;
                    break;
                }
            }
        }

        self::assertInstanceOf(RequiredError::class, $caught);
    }
}
