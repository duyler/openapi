<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\ConstError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Operation;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * TC-07: Cookie parameter type coercion.
 *
 * PSR-7 cookies are always delivered as strings (RFC 6265). When an OpenAPI
 * parameter is declared `type: integer` and `enableCoercion()` is on, the
 * validator's TypeCoercer converts the cookie string to int before schema
 * validation. The coerced value is not exposed via Operation, so coercion is
 * proven indirectly through schema constraints that can only be satisfied by
 * the coerced integer form.
 *
 * CookieValidator invokes TypeCoercer with strict=true (consistent with
 * HeadersValidator). In strict mode non-numeric strings are rejected with
 * TypeMismatchError during coercion rather than silently coerced to 0.
 */
final class CookieCoercionTest extends TestCase
{
    private const string COOKIE_COERCION_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: TC-07 Cookie Coercion API
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

    /**
     * Direct unit-level proof: TypeCoercer converts cookie string "42" to int.
     *
     * assertSame(42, ...) checks both value and type — this is the only place
     * where the coerced integer is directly observable.
     */
    #[Test]
    public function tc_07_type_coercer_directly_converts_cookie_string_to_int(): void
    {
        $coercer = new TypeCoercer();
        $param = new Parameter(schema: new Schema(type: 'integer'));

        $result = $coercer->coerce('42', $param, enabled: true, strict: false);

        $this->assertSame(42, $result);
        $this->assertIsInt($result);
    }

    /**
     * Baseline: without coercion the cookie string "42" is left as a string
     * and fails the const constraint. PHP's loose comparison keeps "42"
     * through the type check, but strict `===` in ConstError rejects it.
     *
     * This baseline proves the next test passes specifically because of
     * coercion, not because the validator is lenient about types.
     */
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

    /**
     * Positive: with coercion, cookie string "42" is converted to int 42 and
     * passes both the type check and the const constraint.
     *
     * The const constraint can only match the integer literal 42 — the
     * string "42" fails it (see baseline test above). Successful validation
     * therefore proves coercion occurred end-to-end.
     */
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

    /**
     * Negative: cookie string "abc" cannot be coerced to integer.
     *
     * CookieValidator invokes TypeCoercer with strict=true, so the coercer
     * rejects non-numeric strings directly with TypeMismatchError during
     * coercion (before the const constraint is evaluated).
     */
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

    /**
     * Direct unit-level proof: in strict mode TypeCoercer rejects non-numeric
     * strings with TypeMismatchError. CookieValidator passes strict=true, so
     * this is the path exercised end-to-end by the test above.
     */
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
