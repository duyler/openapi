<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Regression\R4;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Duyler\OpenApi\Validator\Coercion\AbstractCoercer;
use Duyler\OpenApi\Validator\Request\RequestBodyCoercer;

use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Regression suite for R4-SPEC-005 / R4-TEST-008: strict type coercion
 * accepts whole-valued floats (3.0) as integers per JSON Schema
 * 2020-12 §4.2.3 and rejects fractional / non-finite floats.
 *
 * Anti-test: re-introducing the legacy rejection of any float (including
 * whole-valued 3.0) in {@see AbstractCoercer::coerceToIntegerStrict()}
 * makes tests 1–3 fail; loosening the strict guard to accept fractional
 * floats makes tests 4–5 fail.
 *
 * Unlike the inline AbstractCoercerTest, this suite drives the
 * coercion path through the public request body validator
 * ({@see OpenApiValidatorBuilder::build()->validateRequest()}) so the
 * regression also fires when {@see RequestBodyCoercer}
 * is rewired.
 *
 * @internal
 */
final class CoercionWholeFloatRegressionTest extends TestCase
{
    private const string SPEC = <<<'YAML'
openapi: 3.2.0
info: { title: T, version: '1' }
paths:
  /count:
    post:
      operationId: setCount
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [count]
              properties:
                count: { type: integer }
              additionalProperties: false
      responses:
        '204':
          description: ok
YAML;

    #[Test]
    public function strict_coercion_accepts_whole_valued_float_as_integer(): void
    {
        $validator = $this->buildStrict();

        $this->assertNoValidationError(fn(): mixed => $validator->validateRequest(
            $this->jsonRequest(['count' => 3.0]),
        ));
    }

    #[Test]
    public function strict_coercion_accepts_zero_whole_valued_float(): void
    {
        $validator = $this->buildStrict();

        $this->assertNoValidationError(fn(): mixed => $validator->validateRequest(
            $this->jsonRequest(['count' => 0.0]),
        ));
    }

    #[Test]
    public function strict_coercion_accepts_negative_whole_valued_float(): void
    {
        $validator = $this->buildStrict();

        $this->assertNoValidationError(fn(): mixed => $validator->validateRequest(
            $this->jsonRequest(['count' => -42.0]),
        ));
    }

    #[Test]
    public function strict_coercion_rejects_fractional_float_with_type_mismatch(): void
    {
        $validator = $this->buildStrict();

        $caught = $this->captureTypeMismatch(fn(): mixed => $validator->validateRequest(
            $this->jsonRequest(['count' => 3.14]),
        ));

        self::assertNotNull(
            $caught,
            'Fractional float 3.14 must produce a TypeMismatchError under strict coercion.',
        );
    }

    #[Test]
    public function strict_coercion_rejects_fractional_float_with_non_zero_fraction(): void
    {
        $validator = $this->buildStrict();

        $caught = $this->captureTypeMismatch(fn(): mixed => $validator->validateRequest(
            $this->jsonRequest(['count' => -1.5]),
        ));

        self::assertNotNull($caught);
    }

    #[Test]
    public function disable_strict_coercion_keeps_whole_valued_float_accepted(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->enableCoercion()
            ->disableStrictCoercion()
            ->build();

        $this->assertNoValidationError(fn(): mixed => $validator->validateRequest(
            $this->jsonRequest(['count' => 10.0]),
        ));
    }

    #[Test]
    public function integer_value_still_accepted_under_strict_coercion(): void
    {
        $validator = $this->buildStrict();

        $operation = $validator->validateRequest($this->jsonRequest(['count' => 42]));

        self::assertSame('/count', $operation->path);
    }

    private function buildStrict(): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->enableCoercion()
            ->build();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonRequest(array $payload): ServerRequestInterface
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $factory = new Psr17Factory();

        return $factory->createServerRequest('POST', '/count')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream($body));
    }

    /**
     * @param callable(): mixed $callback
     */
    private function assertNoValidationError(callable $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected no validation failure, got: %s', $e->getMessage()));
        }

        self::assertTrue(true);
    }

    /**
     * @param callable(): mixed $callback
     */
    private function captureTypeMismatch(callable $callback): ?TypeMismatchError
    {
        try {
            $callback();
        } catch (ValidationException $e) {
            foreach ($e->getErrors() as $error) {
                if ($error instanceof TypeMismatchError) {
                    return $error;
                }
            }
        } catch (TypeMismatchError $e) {
            return $e;
        }

        return null;
    }
}
