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

/**
 * TC-08: Header parameter type coercion.
 *
 * PSR-7 headers are always delivered as strings. When an OpenAPI parameter
 * is declared `type: integer` and `enableCoercion()` is on, the validator's
 * TypeCoercer converts the header string to int before schema validation.
 *
 * HeadersValidator invokes TypeCoercer with strict=true (consistent with
 * CookieValidator which also uses strict=true). In strict mode non-numeric
 * strings and overflow values are rejected with TypeMismatchError.
 */
final class HeaderCoercionTest extends TestCase
{
    private const string HEADER_COERCION_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: TC-08 Header Coercion API
  version: 1.0.0
paths:
  /metrics:
    get:
      parameters:
        - name: X-Count
          in: header
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
     * Direct unit-level proof: TypeCoercer converts header string "42" to int.
     *
     * assertSame(42, ...) checks both value and type — this is the only place
     * where the coerced integer is directly observable. Header validation
     * uses strict=true, so the assertion is performed with strict mode.
     */
    #[Test]
    public function tc_08_type_coercer_directly_converts_header_string_to_int(): void
    {
        $coercer = new TypeCoercer();
        $param = new Parameter(schema: new Schema(type: 'integer'));

        $result = $coercer->coerce('42', $param, enabled: true, strict: true);

        $this->assertSame(42, $result);
        $this->assertIsInt($result);
    }

    /**
     * Baseline: without coercion the header string "42" is left as a string
     * and fails the const constraint. PHP's loose comparison keeps "42"
     * through the type check, but strict `===` in ConstError rejects it.
     *
     * This baseline proves the next test passes specifically because of
     * coercion, not because the validator is lenient about types.
     */
    #[Test]
    public function tc_08_header_without_coercion_fails_const_check_on_string_value(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::HEADER_COERCION_SPEC)
            ->build();

        $request = $this->buildHeaderRequest('42');

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
     * Positive: with coercion, header string "42" is converted to int 42 and
     * passes both the type check and the const constraint.
     *
     * The const constraint can only match the integer literal 42 — the
     * string "42" fails it (see baseline test above). Successful validation
     * therefore proves coercion occurred end-to-end.
     */
    #[Test]
    public function tc_08_header_string_coerced_to_integer_passes_const_constraint(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::HEADER_COERCION_SPEC)
            ->enableCoercion()
            ->build();

        $request = $this->buildHeaderRequest('42');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/metrics', $operation->path);
    }

    /**
     * Negative: header string "abc" cannot be coerced to integer.
     *
     * HeadersValidator uses strict=true, so TypeMismatchError is raised
     * directly during coercion (before schema constraint validation).
     */
    #[Test]
    public function tc_08_header_non_numeric_string_fails_coercion_with_type_mismatch(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::HEADER_COERCION_SPEC)
            ->enableCoercion()
            ->build();

        $request = $this->buildHeaderRequest('abc');

        $caught = null;

        try {
            $validator->validateRequest($request);
        } catch (TypeMismatchError $e) {
            $caught = $e;
        }

        $this->assertNotNull(
            $caught,
            'Non-numeric header value must fail coercion with TypeMismatchError in strict mode.',
        );

        $this->assertSame('integer', $caught->params()['expected']);
        $this->assertSame('abc', $caught->params()['actual']);
    }

    /**
     * Negative: an overflow header value must fail coercion in strict mode.
     *
     * PHP's `(int) "99999999999999999999"` cannot represent the value
     * faithfully, so the strict coercer rejects it as a type mismatch.
     */
    #[Test]
    public function tc_08_header_overflow_numeric_string_fails_coercion_with_type_mismatch(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: TC-08 Header Coercion Overflow API
  version: 1.0.0
paths:
  /metrics:
    get:
      parameters:
        - name: X-Count
          in: header
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->buildHeaderRequest('99999999999999999999');

        $caught = null;

        try {
            $validator->validateRequest($request);
        } catch (TypeMismatchError $e) {
            $caught = $e;
        }

        $this->assertNotNull(
            $caught,
            'Overflow header value must fail coercion with TypeMismatchError in strict mode.',
        );

        $this->assertSame('integer', $caught->params()['expected']);
    }

    private function buildHeaderRequest(string $value): ServerRequestInterface
    {
        return $this->psrFactory
            ->createServerRequest('GET', '/metrics')
            ->withHeader('X-Count', $value);
    }
}
