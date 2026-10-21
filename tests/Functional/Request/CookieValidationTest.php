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

final class CookieValidationTest extends TestCase
{
    private Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    #[Test]
    public function type_coercer_directly_converts_cookie_string_to_int_25(): void
    {
        $coercer = new TypeCoercer();
        $param = new Parameter(schema: new Schema(type: 'integer'));

        $result = $coercer->coerce('25', $param, enabled: true, strict: true);

        $this->assertSame(25, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function cookie_integer_value_coerced_passes_const_constraint(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Cookie Coercion API
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

    #[Test]
    public function cookie_integer_without_coercion_fails_const_check(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Cookie Coercion API
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

    #[Test]
    public function cookie_non_numeric_string_throws_type_mismatch(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Cookie Coercion API
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

    #[Test]
    public function type_coercer_strict_mode_rejects_non_numeric_string_with_type_mismatch(): void
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

    #[Test]
    public function cookie_url_encoded_utf8_value_decoded_to_string(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Cookie Non-ASCII API
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

    #[Test]
    public function cookie_url_encoded_utf8_value_decoded_does_not_match_other_const(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Cookie Non-ASCII API
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

    #[Test]
    public function raw_url_decode_of_cyrillic_cookie_value_yields_utf8(): void
    {
        $decoded = rawurldecode('%D0%98%D0%B2%D0%B0%D0%BD');

        $this->assertSame('Иван', $decoded);
    }

    #[Test]
    public function cookie_style_form_explode_collects_repeated_keys_into_array(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Cookie Explode API
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

    #[Test]
    public function cookie_style_form_explode_single_value_fails_array_schema(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Cookie Explode API
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
