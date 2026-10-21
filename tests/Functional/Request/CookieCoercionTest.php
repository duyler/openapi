<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\ConstError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class CookieCoercionTest extends TestCase
{
    private const string COOKIE_COERCION_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Cookie Coercion API
  version: 1.0.0
paths:
  /visit:
    get:
      parameters:
        - name: count
          in: cookie
          required: true
          schema:
            type: integer
            const: 42
      responses:
        '200':
          description: OK
YAML;

    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function tc_07_type_coercer_directly_converts_cookie_string_to_int(): void
    {
        $coercer = new TypeCoercer();
        $param = new Parameter(schema: new Schema(type: 'integer'));

        $result = $coercer->coerce('42', $param, enabled: true, strict: false);

        $this->assertSame(42, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function tc_07_cookie_without_coercion_fails_const_check_on_string_value(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::COOKIE_COERCION_SPEC)
            ->build();

        $request = $this->buildCookieRequest(['count' => '42']);

        $caught = null;

        try {
            $validator->validateRequest($request);
        } catch (ConstError $e) {
            $caught = $e;
        }

        $this->assertNotNull(
            $caught,
            'Without coercion, string "42" must fail const check against integer 42.',
        );

        $this->assertSame(42, $caught->params()['expected']);
        $this->assertSame('42', $caught->params()['actual']);
    }

    #[Test]
    public function tc_07_cookie_string_coerced_to_integer_passes_const_constraint(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::COOKIE_COERCION_SPEC)
            ->enableCoercion()
            ->build();

        $request = $this->buildCookieRequest(['count' => '42']);

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/visit', $operation->path);
    }

    #[Test]
    public function tc_07_cookie_non_numeric_string_throws_type_mismatch(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::COOKIE_COERCION_SPEC)
            ->enableCoercion()
            ->build();

        $request = $this->buildCookieRequest(['count' => 'abc']);

        $caught = null;

        try {
            $validator->validateRequest($request);
        } catch (TypeMismatchError $e) {
            $caught = $e;
        }

        $this->assertNotNull(
            $caught,
            'Non-numeric cookie "abc" must fail coercion with TypeMismatchError in strict mode.',
        );

        $this->assertSame('integer', $caught->params()['expected']);
        $this->assertSame('abc', $caught->params()['actual']);
    }

    #[Test]
    public function tc_07_type_coercer_strict_mode_rejects_non_numeric_cookie_with_type_mismatch(): void
    {
        $coercer = new TypeCoercer();
        $param = new Parameter(schema: new Schema(type: 'integer'));

        $caught = null;

        try {
            $coercer->coerce('abc', $param, enabled: true, strict: true);
        } catch (TypeMismatchError $e) {
            $caught = $e;
        }

        $this->assertNotNull(
            $caught,
            'Strict mode must reject non-numeric cookie value with TypeMismatchError.',
        );

        $this->assertSame('integer', $caught->params()['expected']);
        $this->assertSame('abc', $caught->params()['actual']);
    }

    private function buildCookieRequest(array $cookies): ServerRequestInterface
    {
        return $this->psrFactory
            ->createServerRequest('GET', '/visit')
            ->withCookieParams($cookies);
    }
}
