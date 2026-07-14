<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\ConstError;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * CK-01 / CK-02 / CK-03: Cookie parameter edge cases.
 *
 * - CK-01: integer cookie coercion with `enableCoercion()`. PSR-7 cookie
 *   values are always strings (RFC 6265); the TypeCoercer converts
 *   numeric strings to int when the schema declares `type: integer`.
 *   CookieValidator passes `strict=true` to the coercer, so non-numeric
 *   strings are rejected with TypeMismatchError during coercion.
 * - CK-02: non-ASCII cookie values URL-encoded as `%XX` sequences.
 *   CookieValidator uses `rawurldecode` on the raw cookie value.
 * - CK-03: `style: form + explode: true` for cookie arrays. Multiple
 *   cookies with the same name are collected into an indexed array
 *   via CookieValidator::parseExplodedValues.
 */
final class CookieValidationTest extends TestCase
{
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    /**
     * CK-01 unit-level: TypeCoercer converts cookie string "25" to int 25.
     *
     * `assertSame(25, $result)` checks both value and type — the only place
     * where the coerced integer is directly observable end-to-end.
     * CookieValidator passes strict=true to the coercer.
     */
    #[Test]
    public function ck_01_type_coercer_directly_converts_cookie_string_to_int_25(): void
    {
        $coercer = new TypeCoercer();
        $param = new Parameter(schema: new Schema(type: 'integer'));

        $result = $coercer->coerce('25', $param, enabled: true, strict: true);

        $this->assertSame(25, $result);
        $this->assertIsInt($result);
    }

    /**
     * CK-01 full-cycle positive: cookie "age=25" with `type: integer` and
     * `const: 25`. The string "25" is coerced to int 25, satisfying the
     * const constraint that only matches the integer literal.
     *
     * Without coercion (see CK-01 negative below), the same request fails
     * because the cookie value remains a string.
     */
    #[Test]
    public function ck_01_cookie_integer_value_coerced_passes_const_constraint(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: CK-01 Cookie Coercion API
  version: 1.0.0
paths:
  /visit:
    get:
      parameters:
        - name: age
          in: cookie
          required: true
          schema:
            type: integer
            const: 25
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->buildCookieRequest('/visit', ['age' => '25']);

        $operation = $validator->validateRequest($request);

        $this->assertSame('/visit', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    /**
     * CK-01 baseline: without `enableCoercion()`, cookie string "25" is
     * left as a string and fails the integer const constraint.
     */
    #[Test]
    public function ck_01_cookie_integer_without_coercion_fails_const_check(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: CK-01 Cookie Coercion API
  version: 1.0.0
paths:
  /visit:
    get:
      parameters:
        - name: age
          in: cookie
          required: true
          schema:
            type: integer
            const: 25
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->buildCookieRequest('/visit', ['age' => '25']);

        $caught = null;

        try {
            $validator->validateRequest($request);
        } catch (ConstError $e) {
            $caught = $e;
        }

        $this->assertNotNull(
            $caught,
            'Without coercion, string "25" must fail const check against integer 25.',
        );

        $this->assertSame(25, $caught->params()['expected']);
        $this->assertSame('25', $caught->params()['actual']);
    }

    /**
     * CK-01 negative: cookie "age=abc" cannot be coerced to integer.
     *
     * CookieValidator invokes TypeCoercer with strict=true, so the coercer
     * rejects non-numeric strings directly with TypeMismatchError during
     * coercion (before the const constraint is evaluated).
     */
    #[Test]
    public function ck_01_cookie_non_numeric_string_throws_type_mismatch(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: CK-01 Cookie Coercion API
  version: 1.0.0
paths:
  /visit:
    get:
      parameters:
        - name: age
          in: cookie
          required: true
          schema:
            type: integer
            const: 25
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->buildCookieRequest('/visit', ['age' => 'abc']);

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
     * CK-01 unit-level negative: direct TypeCoercer invocation in strict
     * mode. `coerce('abc', integer, strict: true)` raises TypeMismatchError,
     * which is the path exercised end-to-end by the test above.
     */
    #[Test]
    public function ck_01_type_coercer_strict_mode_rejects_non_numeric_string_with_type_mismatch(): void
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

    /**
     * CK-02 positive: URL-encoded UTF-8 cookie value. PSR-7 cookie "name"
     * contains the raw encoded form `%D0%98%D0%B2%D0%B0%D0%BD`.
     * CookieValidator::decodeValue applies `rawurldecode`, yielding the
     * UTF-8 string "Иван". The decoded value satisfies `const: 'Иван'`.
     */
    #[Test]
    public function ck_02_cookie_url_encoded_utf8_value_decoded_to_string(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: CK-02 Cookie Non-ASCII API
  version: 1.0.0
paths:
  /who:
    get:
      parameters:
        - name: name
          in: cookie
          required: true
          schema:
            type: string
            const: 'Иван'
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->buildCookieRequest('/who', ['name' => '%D0%98%D0%B2%D0%B0%D0%BD']);

        $operation = $validator->validateRequest($request);

        $this->assertSame('/who', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    /**
     * CK-02 negative: the same URL-encoded Cyrillic value with a different
     * const constraint. The decoded value "Иван" does not match const
     * "Петр", proving the value was decoded to UTF-8 before validation.
     */
    #[Test]
    public function ck_02_cookie_url_encoded_utf8_value_decoded_does_not_match_other_const(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: CK-02 Cookie Non-ASCII API
  version: 1.0.0
paths:
  /who:
    get:
      parameters:
        - name: name
          in: cookie
          required: true
          schema:
            type: string
            const: 'Петр'
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->buildCookieRequest('/who', ['name' => '%D0%98%D0%B2%D0%B0%D0%BD']);

        $caught = null;

        try {
            $validator->validateRequest($request);
        } catch (ConstError $e) {
            $caught = $e;
        }

        $this->assertNotNull(
            $caught,
            'URL-decoded UTF-8 "Иван" must fail const check against "Петр".',
        );

        $this->assertSame('Петр', $caught->params()['expected']);
        $this->assertSame('Иван', $caught->params()['actual']);
    }

    /**
     * CK-02 unit-level: rawurldecode on the cookie raw value yields UTF-8.
     *
     * `%D0%98%D0%B2%D0%B0%D0%BD` is the UTF-8 byte sequence for "Иван"
     * percent-encoded. rawurldecode reverses this losslessly.
     */
    #[Test]
    public function ck_02_raw_url_decode_of_cyrillic_cookie_value_yields_utf8(): void
    {
        $decoded = rawurldecode('%D0%98%D0%B2%D0%B0%D0%BD');

        $this->assertSame('Иван', $decoded);
    }

    /**
     * CK-03 positive: `style: form + explode: true` for cookie arrays via
     * multiple Cookie header values with the same name. The Cookie header
     * `tags=php; tags=go` is parsed by CookieValidator::parseExplodedValues
     * into the array ["php", "go"].
     *
     * The `minItems: 2` constraint proves two items were actually collected.
     */
    #[Test]
    public function ck_03_cookie_style_form_explode_collects_repeated_keys_into_array(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: CK-03 Cookie Explode API
  version: 1.0.0
paths:
  /data:
    get:
      parameters:
        - name: tags
          in: cookie
          required: true
          style: form
          explode: true
          schema:
            type: array
            items:
              type: string
            minItems: 2
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory
            ->createServerRequest('GET', '/data')
            ->withHeader('Cookie', 'tags=php; tags=go');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/data', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    /**
     * CK-03 negative: explode:true with only one cookie value. The single
     * cookie "tags=php" does not trigger the explode path (hasMultipleCookies
     * returns false), so the value remains a string "php" rather than an
     * array. The `type: array` schema then rejects the string form.
     */
    #[Test]
    public function ck_03_cookie_style_form_explode_single_value_fails_array_schema(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: CK-03 Cookie Explode API
  version: 1.0.0
paths:
  /data:
    get:
      parameters:
        - name: tags
          in: cookie
          required: true
          style: form
          explode: true
          schema:
            type: array
            items:
              type: string
            minItems: 2
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $caught = null;

        try {
            $validator->validateRequest(
                $this->psrFactory
                    ->createServerRequest('GET', '/data')
                    ->withHeader('Cookie', 'tags=php'),
            );
            self::fail('Expected TypeMismatchError because single cookie value is not array-exploded');
        } catch (TypeMismatchError $e) {
            $caught = $e;
        }

        self::assertInstanceOf(TypeMismatchError::class, $caught);
        self::assertSame('type', $caught->keyword());
        self::assertSame('array', $caught->params()['expected']);
        self::assertSame('string', $caught->params()['actual']);
    }

    private function buildCookieRequest(string $path, array $cookies): ServerRequestInterface
    {
        return $this->psrFactory
            ->createServerRequest('GET', $path)
            ->withCookieParams($cookies);
    }
}
