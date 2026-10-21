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

final class HeaderCoercionTest extends TestCase
{
    private const string HEADER_COERCION_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Header Coercion API
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

    #[Test]
    public function type_coercer_directly_converts_header_string_to_int(): void
    {
        $coercer = new TypeCoercer();
        $param = new Parameter(schema: new Schema(type: 'integer'));

        $result = $coercer->coerce('42', $param, enabled: true, strict: true);

        $this->assertSame(42, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function header_without_coercion_fails_const_check_on_string_value(): void
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

    #[Test]
    public function header_string_coerced_to_integer_passes_const_constraint(): void
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

    #[Test]
    public function header_non_numeric_string_fails_coercion_with_type_mismatch(): void
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

    #[Test]
    public function header_overflow_numeric_string_fails_coercion_with_type_mismatch(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Header Coercion Overflow API
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
