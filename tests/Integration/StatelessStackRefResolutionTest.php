<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StatelessStackRefResolutionTest extends TestCase
{
    private const string SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Stateless stack ref resolution
  version: 1.0.0
paths:
  /users:
    get:
      parameters:
        - name: code
          in: query
          required: true
          schema:
            allOf:
              - $ref: '#/components/schemas/Code'
      responses:
        '200':
          description: OK
          headers:
            X-Request-Id:
              required: true
              schema:
                allOf:
                  - $ref: '#/components/schemas/RequestId'
components:
  schemas:
    Code:
      type: string
      minLength: 4
    RequestId:
      type: string
      minLength: 5
YAML;

    private Psr17Factory $psrFactory;

    #[Override]
    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function parameter_nested_ref_accepts_valid_value(): void
    {
        $validator = $this->createValidator();

        $operation = $validator->validateRequest(
            $this->psrFactory->createServerRequest('GET', '/users?code=abcd'),
        );

        self::assertSame('/users', $operation->path);
    }

    #[Test]
    public function parameter_nested_ref_rejects_violating_value(): void
    {
        $validator = $this->createValidator();

        try {
            $validator->validateRequest(
                $this->psrFactory->createServerRequest('GET', '/users?code=ab'),
            );
            self::fail('Expected nested ref validation error for short code');
        } catch (ValidationException|AbstractValidationError $exception) {
            self::assertSame('minLength', $this->firstError($exception)->keyword());
        }
    }

    #[Test]
    public function response_header_nested_ref_rejects_violating_value(): void
    {
        $validator = $this->createValidator();

        $operation = $validator->validateRequest(
            $this->psrFactory->createServerRequest('GET', '/users?code=abcd'),
        );

        $response = $this->psrFactory
            ->createResponse(200)
            ->withHeader('X-Request-Id', 'abc');

        try {
            $validator->validateResponse($response, $operation);
            self::fail('Expected nested ref validation error for short X-Request-Id');
        } catch (ValidationException|AbstractValidationError $exception) {
            self::assertSame('minLength', $this->firstError($exception)->keyword());
        }
    }

    #[Test]
    public function response_header_nested_ref_accepts_valid_value(): void
    {
        $validator = $this->createValidator();

        $operation = $validator->validateRequest(
            $this->psrFactory->createServerRequest('GET', '/users?code=abcd'),
        );

        $response = $this->psrFactory
            ->createResponse(200)
            ->withHeader('X-Request-Id', 'a-long-id');

        $validator->validateResponse($response, $operation);

        $this->addToAssertionCount(1);
    }

    private function createValidator(): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();
    }

    private function firstError(ValidationException|AbstractValidationError $exception): AbstractValidationError
    {
        if ($exception instanceof ValidationException) {
            $errors = $exception->getErrors();
            self::assertNotEmpty($errors);
            $first = $errors[0];
            self::assertInstanceOf(AbstractValidationError::class, $first);

            return $first;
        }

        return $exception;
    }
}
