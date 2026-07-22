<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Regression\R4;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Exception\OperationNotFoundException;
use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

use function implode;

/**
 * Regression suite for R4-SEC-007a/b/c/d / R4-TEST-007: exception
 * messages must not reflect attacker-controlled inputs (request path,
 * method, media type, parameter name) when emitted from the public
 * validator API. Anti-test: re-introducing the legacy behaviour that
 * interpolates the attacker value into getMessage() makes the four
 * structural assertions below fail.
 *
 * Unlike the inline exception tests that exercise the constructors
 * in isolation, this suite drives every case through the public
 * {@see OpenApiValidatorBuilder::build()} path so the regression also
 * fires when an HTTP-side code path reconstructs the exception from
 * raw input rather than through the public constructor.
 *
 * @internal
 */
final class ExceptionInfoLeakRegressionTest extends TestCase
{
    private const string PETSTORE_SPEC = <<<'YAML'
openapi: 3.2.0
info: { title: T, version: '1' }
paths:
  /pets/{petId}:
    get:
      operationId: getPet
      parameters:
        - name: petId
          in: path
          required: true
          schema: { type: integer }
      responses:
        '200':
          description: ok
          content:
            application/json:
              schema:
                type: object
                properties:
                  id: { type: integer }
    post:
      operationId: addPet
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                name: { type: string }
      responses:
        '201':
          description: created
YAML;

    #[Test]
    public function path_lookup_failure_message_does_not_reflect_attacker_path(): void
    {
        $attackerPath = '/<script>alert(1)</script>';
        $factory = new Psr17Factory();
        $validator = $this->buildValidator();

        $caught = $this->capture(fn(): mixed => $validator->validateRequest(
            $factory->createServerRequest('GET', $attackerPath),
        ));

        self::assertInstanceOf(OperationNotFoundException::class, $caught);
        self::assertSame('No operation matches the request', $caught->getMessage());
        self::assertStringNotContainsString($attackerPath, $caught->getMessage());
        self::assertStringNotContainsString('<script>', $caught->getMessage());
    }

    #[Test]
    public function operation_not_found_message_does_not_reflect_attacker_method(): void
    {
        $attackerMethod = 'EVIL';
        $factory = new Psr17Factory();
        $validator = $this->buildValidator();

        $caught = $this->capture(fn(): mixed => $validator->validateRequest(
            $factory->createServerRequest($attackerMethod, '/pets/42'),
        ));

        self::assertInstanceOf(OperationNotFoundException::class, $caught);
        self::assertSame('No operation matches the request', $caught->getMessage());
        self::assertStringNotContainsString($attackerMethod, $caught->getMessage());
    }

    #[Test]
    public function unsupported_media_type_message_does_not_reflect_attacker_media_type(): void
    {
        $attackerMediaType = 'text/malicious-marker';
        $factory = new Psr17Factory();
        $validator = $this->buildValidator();

        $request = $factory->createServerRequest('POST', '/pets/42')
            ->withHeader('Content-Type', $attackerMediaType)
            ->withBody($factory->createStream('{"name":"Tom"}'));

        $caught = $this->capture(fn(): mixed => $validator->validateRequest($request));

        self::assertInstanceOf(UnsupportedMediaTypeException::class, $caught);
        self::assertStringNotContainsString($attackerMediaType, $caught->getMessage());
        self::assertStringNotContainsString('malicious-marker', $caught->getMessage());
        self::assertStringContainsString('application/json', $caught->getMessage());
    }

    #[Test]
    public function invalid_parameter_message_does_not_reflect_attacker_message_argument(): void
    {
        $attackerMarker = 'attacker-marker';
        $factory = new Psr17Factory();
        $validator = $this->buildValidator();

        $segments = [];
        for ($i = 0; $i < 70; ++$i) {
            $segments[] = '[' . $attackerMarker . '_' . $i . ']';
        }
        $deepQueryString = 'filter' . implode('', $segments) . '=v';

        $request = $factory->createServerRequest('GET', '/pets/42?' . $deepQueryString);

        $caught = $this->capture(fn(): mixed => $validator->validateRequest($request));

        self::assertInstanceOf(InvalidParameterException::class, $caught);
        self::assertSame('Invalid parameter configuration', $caught->getMessage());
        self::assertStringNotContainsString($attackerMarker, $caught->getMessage());
    }

    /**
     * @param callable(): mixed $callback
     */
    private function capture(callable $callback): Throwable
    {
        try {
            $callback();
        } catch (Throwable $e) {
            return $e;
        }

        self::fail('Expected an exception to be thrown by the validator.');
    }

    private function buildValidator(): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString(self::PETSTORE_SPEC)
            ->enableCoercion()
            ->build();
    }
}
