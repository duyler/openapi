<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;

use function json_encode;
use function sprintf;

final class FullCycleTest extends TestCase
{
    private const USER_API_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Full Cycle API
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
              type: object
              properties:
                name:
                  type: string
                  minLength: 1
                email:
                  type: string
                  format: email
              required:
                - name
                - email
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
                required:
                  - id
                  - name
                  - email
  /admin/users:
    post:
      operationId: createAdminUser
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                name:
                  type: string
              required:
                - name
      responses:
        '201':
          description: Admin created
          content:
            application/json:
              schema:
                type: object
                properties:
                  role:
                    type: string
                required:
                  - role
YAML;

    private Psr17Factory $psrFactory;

    #[Override]
    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function full_cycle_valid_request_and_response_passes(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::USER_API_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'name' => 'John',
                'email' => 'john@example.com',
            ])));

        $operation = $validator->validateRequest($request);

        $this->assertSame('/users', $operation->path);
        $this->assertSame('POST', $operation->method);
        $this->assertSame('POST /users', (string) $operation);

        $response = $this->psrFactory->createResponse(201)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => 1,
                'name' => 'John',
                'email' => 'john@example.com',
            ])));

        $validator->validateResponse($response, $operation);

        $this->assertSame(0, $operation->countPlaceholders());
    }

    #[Test]
    public function full_cycle_response_with_wrong_type_for_id_fails(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::USER_API_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'name' => 'John',
                'email' => 'john@example.com',
            ])));

        $operation = $validator->validateRequest($request);

        $response = $this->psrFactory->createResponse(201)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => 'not-an-integer',
                'name' => 'John',
                'email' => 'john@example.com',
            ])));

        try {
            $validator->validateResponse($response, $operation);
            $this->fail('Expected validation error for response with string id was not thrown');
        } catch (ValidationException|AbstractValidationError $e) {
            $error = $this->firstError($e);

            $this->assertSame('type', $error->keyword());
            $this->assertStringContainsString('id', $error->dataPath());
        }
    }

    #[Test]
    public function full_cycle_request_missing_required_email_fails(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::USER_API_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'name' => 'John',
            ])));

        try {
            $validator->validateRequest($request);
            $this->fail('Expected validation error for request missing email was not thrown');
        } catch (ValidationException|AbstractValidationError $e) {
            $error = $this->firstError($e);

            $this->assertSame('required', $error->keyword());
            $this->assertSame('email', $error->params()['property']);
        }
    }

    #[Test]
    public function full_cycle_operation_binds_response_to_path_and_method(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::USER_API_SPEC)
            ->build();

        $createUserRequest = $this->psrFactory->createServerRequest('POST', '/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'name' => 'John',
                'email' => 'john@example.com',
            ])));

        $createUserOperation = $validator->validateRequest($createUserRequest);

        $createAdminRequest = $this->psrFactory->createServerRequest('POST', '/admin/users')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'name' => 'Admin',
            ])));

        $createAdminOperation = $validator->validateRequest($createAdminRequest);

        $this->assertSame('/users', $createUserOperation->path);
        $this->assertSame('/admin/users', $createAdminOperation->path);

        $userResponse = $this->psrFactory->createResponse(201)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->psrFactory->createStream(json_encode([
                'id' => 1,
                'name' => 'John',
                'email' => 'john@example.com',
            ])));

        try {
            $validator->validateResponse($userResponse, $createAdminOperation);
            $this->fail(
                'Response valid for POST /users must fail when validated against POST /admin/users operation',
            );
        } catch (ValidationException|AbstractValidationError $e) {
            $error = $this->firstError($e);

            $this->assertSame('required', $error->keyword());
            $this->assertSame('role', $error->params()['property']);
        }

        $validator->validateResponse($userResponse, $createUserOperation);
    }

    private function firstError(ValidationException|AbstractValidationError $e): AbstractValidationError
    {
        if ($e instanceof ValidationException) {
            $errors = $e->getErrors();

            $this->assertNotEmpty(
                $errors,
                sprintf('ValidationException "%s" must contain at least one error', $e->getMessage()),
            );

            $first = $errors[0];
            $this->assertInstanceOf(AbstractValidationError::class, $first);

            return $first;
        }

        return $e;
    }
}
