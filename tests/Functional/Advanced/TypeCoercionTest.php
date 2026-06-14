<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Advanced;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\CoercionContext;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Request\RequestBodyCoercer;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use Override;
use PHPUnit\Framework\Attributes\Test;

use function str_increment;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

final class TypeCoercionTest extends AdvancedFunctionalTestCase
{
    private const string INTEGER_COERCION_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Integer Coercion API
  version: 1.0.0
paths:
  /users:
    get:
      parameters:
        - name: age
          in: query
          required: true
          schema:
            type: integer
            minimum: 1
      responses:
        '200':
          description: OK
YAML;

    private const string INTEGER_OVERFLOW_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Integer Overflow API
  version: 1.0.0
paths:
  /items:
    get:
      parameters:
        - name: id
          in: query
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: OK
YAML;
    private string $specFile = '';
    private TypeCoercer $paramCoercer;
    private RequestBodyCoercer $bodyCoercer;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->specFile = __DIR__ . '/../../fixtures/advanced-specs/type-coercion.yaml';
        $this->paramCoercer = new TypeCoercer();
        $this->bodyCoercer = new RequestBodyCoercer();
    }

    #[Test]
    public function string_to_integer_coercion_valid(): void
    {
        $param = new Parameter(schema: new Schema(type: 'integer'));

        $result = $this->paramCoercer->coerce('30', $param, true);

        $this->assertSame(30, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function string_to_integer_coercion_strict_mode_invalid_throws_error(): void
    {
        $param = new Parameter(schema: new Schema(type: 'integer'));

        $this->expectException(TypeMismatchError::class);
        $this->paramCoercer->coerce('12.5', $param, true, true);
    }

    #[Test]
    public function string_to_float_coercion_valid(): void
    {
        $param = new Parameter(schema: new Schema(type: 'number'));

        $result = $this->paramCoercer->coerce('99.99', $param, true);

        $this->assertSame(99.99, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function string_to_float_coercion_strict_mode_invalid_throws_error(): void
    {
        $param = new Parameter(schema: new Schema(type: 'number'));

        $this->expectException(TypeMismatchError::class);
        $this->paramCoercer->coerce('abc', $param, true, true);
    }

    #[Test]
    public function integer_to_string_coercion_via_body_valid(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'id' => new Schema(type: 'string'),
            ],
        );

        $context = new CoercionContext(schema: $schema, enabled: true, strict: true);

        $result = $this->bodyCoercer->coerce(['id' => 123], $context);

        $this->assertSame('123', $result['id']);
        $this->assertIsString($result['id']);
    }

    #[Test]
    public function integer_to_string_coercion_disabled_preserves_integer(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'id' => new Schema(type: 'string'),
            ],
        );

        $context = new CoercionContext(schema: $schema, enabled: false, strict: true);

        $result = $this->bodyCoercer->coerce(['id' => 123], $context);

        $this->assertSame(123, $result['id']);
        $this->assertIsInt($result['id']);
    }

    #[Test]
    public function coercion_with_mixed_types_valid(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'data' => new Schema(
                    type: 'object',
                    properties: [
                        'id' => new Schema(type: 'string'),
                        'count' => new Schema(type: 'integer'),
                        'active' => new Schema(type: 'boolean'),
                        'tags' => new Schema(
                            type: 'array',
                            items: new Schema(type: 'string'),
                        ),
                    ],
                ),
            ],
        );

        $context = new CoercionContext(schema: $schema, enabled: true, strict: true);

        $body = [
            'data' => [
                'id' => 123,
                'count' => '5',
                'active' => 'yes',
                'tags' => ['tag1', 'tag2', 'tag3'],
            ],
        ];

        $result = $this->bodyCoercer->coerce($body, $context);

        $this->assertSame('123', $result['data']['id']);
        $this->assertIsString($result['data']['id']);

        $this->assertSame(5, $result['data']['count']);
        $this->assertIsInt($result['data']['count']);

        $this->assertSame(true, $result['data']['active']);
        $this->assertIsBool($result['data']['active']);

        $this->assertSame(['tag1', 'tag2', 'tag3'], $result['data']['tags']);
    }

    #[Test]
    public function coercion_with_mixed_types_invalid_throws_error(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'data' => new Schema(
                    type: 'object',
                    properties: [
                        'count' => new Schema(type: 'integer'),
                    ],
                ),
            ],
        );

        $context = new CoercionContext(schema: $schema, enabled: true, strict: true);

        $this->expectException(TypeMismatchError::class);
        $this->bodyCoercer->coerce(['data' => ['count' => 'abc']], $context);
    }

    #[Test]
    public function boolean_string_coercion_valid(): void
    {
        $param = new Parameter(schema: new Schema(type: 'boolean'));

        $result = $this->paramCoercer->coerce('true', $param, true);

        $this->assertSame(true, $result);
        $this->assertIsBool($result);
    }

    #[Test]
    public function boolean_integer_coercion_valid(): void
    {
        $param = new Parameter(schema: new Schema(type: 'boolean'));

        $result = $this->paramCoercer->coerce('1', $param, true);

        $this->assertSame(true, $result);
        $this->assertIsBool($result);
    }

    #[Test]
    public function boolean_false_string_coercion(): void
    {
        $param = new Parameter(schema: new Schema(type: 'boolean'));

        $result = $this->paramCoercer->coerce('false', $param, true);

        $this->assertSame(false, $result);
        $this->assertIsBool($result);
    }

    #[Test]
    public function boolean_false_integer_coercion(): void
    {
        $param = new Parameter(schema: new Schema(type: 'boolean'));

        $result = $this->paramCoercer->coerce('0', $param, true);

        $this->assertSame(false, $result);
        $this->assertIsBool($result);
    }

    #[Test]
    public function boolean_on_off_string_coercion(): void
    {
        $param = new Parameter(schema: new Schema(type: 'boolean'));

        $onResult = $this->paramCoercer->coerce('on', $param, true);
        $this->assertSame(true, $onResult);
        $this->assertIsBool($onResult);

        $offResult = $this->paramCoercer->coerce('off', $param, true);
        $this->assertSame(false, $offResult);
        $this->assertIsBool($offResult);
    }

    #[Test]
    public function boolean_fallback_coercion(): void
    {
        $param = new Parameter(schema: new Schema(type: 'boolean'));

        $result = $this->paramCoercer->coerce('maybe', $param, true);

        $this->assertSame(true, $result);
        $this->assertIsBool($result);
    }

    #[Test]
    public function coercion_enabled_valid(): void
    {
        $intParam = new Parameter(schema: new Schema(type: 'integer'));
        $numberParam = new Parameter(schema: new Schema(type: 'number'));
        $boolParam = new Parameter(schema: new Schema(type: 'boolean'));

        $ageResult = $this->paramCoercer->coerce('25', $intParam, true);
        $priceResult = $this->paramCoercer->coerce('100.50', $numberParam, true);
        $activeResult = $this->paramCoercer->coerce('yes', $boolParam, true);

        $this->assertSame(25, $ageResult);
        $this->assertIsInt($ageResult);

        $this->assertSame(100.50, $priceResult);
        $this->assertIsFloat($priceResult);

        $this->assertSame(true, $activeResult);
        $this->assertIsBool($activeResult);
    }

    #[Test]
    public function coercion_disabled_throws_error(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('GET', '/request/coercion?age=25');

        $this->expectException(TypeMismatchError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function coercion_with_validation_minimum(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile($this->specFile)
            ->enableCoercion()
            ->build();

        $request = $this->createRequest('GET', '/request/coercion-validation?age=17');

        try {
            $validator->validateRequest($request);
            $this->fail('Expected MinimumError was not thrown');
        } catch (MinimumError $e) {
            $this->assertSame('minimum', $e->keyword());
            $this->assertSame(18.0, $e->params()['minimum']);
            $this->assertSame(17.0, $e->params()['actual']);
        }
    }

    #[Test]
    public function nested_object_coercion_valid(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'user' => new Schema(
                    type: 'object',
                    properties: [
                        'age' => new Schema(type: 'integer'),
                        'active' => new Schema(type: 'boolean'),
                        'name' => new Schema(type: 'string'),
                    ],
                ),
                'items' => new Schema(
                    type: 'array',
                    items: new Schema(type: 'integer'),
                ),
            ],
        );

        $context = new CoercionContext(schema: $schema, enabled: true, strict: true);

        $body = [
            'user' => [
                'age' => '25',
                'active' => 'true',
            ],
            'items' => ['1', '2', '3'],
        ];

        $result = $this->bodyCoercer->coerce($body, $context);

        $this->assertSame(25, $result['user']['age']);
        $this->assertIsInt($result['user']['age']);

        $this->assertSame(true, $result['user']['active']);
        $this->assertIsBool($result['user']['active']);

        $this->assertSame([1, 2, 3], $result['items']);
    }

    #[Test]
    public function nested_object_coercion_invalid_type_throws_error(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'user' => new Schema(
                    type: 'object',
                    properties: [
                        'age' => new Schema(type: 'integer'),
                    ],
                ),
            ],
        );

        $context = new CoercionContext(schema: $schema, enabled: true, strict: true);

        $this->expectException(TypeMismatchError::class);
        $this->bodyCoercer->coerce(['user' => ['age' => 'abc']], $context);
    }

    #[Test]
    public function array_items_coercion_valid(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'numbers' => new Schema(
                    type: 'array',
                    items: new Schema(type: 'integer'),
                ),
                'booleans' => new Schema(
                    type: 'array',
                    items: new Schema(type: 'boolean'),
                ),
                'nested' => new Schema(
                    type: 'array',
                    items: new Schema(
                        type: 'object',
                        properties: [
                            'id' => new Schema(type: 'string'),
                            'value' => new Schema(type: 'number'),
                        ],
                    ),
                ),
            ],
        );

        $context = new CoercionContext(schema: $schema, enabled: true, strict: true);

        $body = [
            'numbers' => ['1', '2', '3'],
            'booleans' => ['true', 'false', '1'],
            'nested' => [
                ['id' => 1, 'value' => '10.5'],
                ['id' => 2, 'value' => '20.7'],
            ],
        ];

        $result = $this->bodyCoercer->coerce($body, $context);

        $this->assertSame([1, 2, 3], $result['numbers']);
        $this->assertIsInt($result['numbers'][0]);

        $this->assertSame([true, false, true], $result['booleans']);
        $this->assertIsBool($result['booleans'][0]);

        $this->assertSame('1', $result['nested'][0]['id']);
        $this->assertIsString($result['nested'][0]['id']);

        $this->assertSame(10.5, $result['nested'][0]['value']);
        $this->assertIsFloat($result['nested'][0]['value']);
    }

    #[Test]
    public function array_items_coercion_invalid_type_throws_error(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'numbers' => new Schema(
                    type: 'array',
                    items: new Schema(type: 'integer'),
                ),
            ],
        );

        $context = new CoercionContext(schema: $schema, enabled: true, strict: true);

        $this->expectException(TypeMismatchError::class);
        $this->bodyCoercer->coerce(['numbers' => ['1', 'abc', '3']], $context);
    }

    #[Test]
    public function coercion_with_nullable_valid(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'nullableInt' => new Schema(type: 'integer', nullable: true),
                'nullableString' => new Schema(type: 'string', nullable: true),
                'nullableBool' => new Schema(type: 'boolean', nullable: true),
            ],
        );

        $context = new CoercionContext(schema: $schema, enabled: true, strict: true, nullableAsType: true);

        $body = [
            'nullableInt' => '42',
            'nullableString' => null,
            'nullableBool' => 'yes',
        ];

        $result = $this->bodyCoercer->coerce($body, $context);

        $this->assertSame(42, $result['nullableInt']);
        $this->assertIsInt($result['nullableInt']);

        $this->assertNull($result['nullableString']);

        $this->assertSame(true, $result['nullableBool']);
        $this->assertIsBool($result['nullableBool']);
    }

    #[Test]
    public function coercion_with_nullable_invalid_type_throws_error(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'nullableInt' => new Schema(type: 'integer', nullable: true),
            ],
        );

        $context = new CoercionContext(schema: $schema, enabled: true, strict: true, nullableAsType: true);

        $this->expectException(TypeMismatchError::class);
        $this->bodyCoercer->coerce(['nullableInt' => 'abc'], $context);
    }

    #[Test]
    public function coercion_with_multiple_parameters_valid(): void
    {
        $intParam = new Parameter(schema: new Schema(type: 'integer'));
        $numberParam = new Parameter(schema: new Schema(type: 'number'));
        $boolParam = new Parameter(schema: new Schema(type: 'boolean'));
        $stringParam = new Parameter(schema: new Schema(type: 'string'));

        $ageResult = $this->paramCoercer->coerce('25', $intParam, true);
        $priceResult = $this->paramCoercer->coerce('100.50', $numberParam, true);
        $activeResult = $this->paramCoercer->coerce('true', $boolParam, true);
        $nameResult = $this->paramCoercer->coerce('test', $stringParam, true);

        $this->assertSame(25, $ageResult);
        $this->assertIsInt($ageResult);

        $this->assertSame(100.50, $priceResult);
        $this->assertIsFloat($priceResult);

        $this->assertSame(true, $activeResult);
        $this->assertIsBool($activeResult);

        $this->assertSame('test', $nameResult);
        $this->assertIsString($nameResult);
    }

    #[Test]
    public function coercion_with_multiple_parameters_invalid_throws_error(): void
    {
        $intParam = new Parameter(schema: new Schema(type: 'integer'));

        $this->expectException(TypeMismatchError::class);
        $this->paramCoercer->coerce('abc', $intParam, true, true);
    }

    #[Test]
    public function strict_body_coercion_invalid_integer_throws_error(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'count' => new Schema(type: 'integer'),
            ],
        );

        $context = new CoercionContext(schema: $schema, enabled: true, strict: true);

        $this->expectException(TypeMismatchError::class);
        $this->bodyCoercer->coerce(['count' => 'abc'], $context);
    }

    #[Test]
    public function integer_query_param_coercion_valid_age_passes(): void
    {
        $validator = $this->buildCoercionValidator(self::INTEGER_COERCION_SPEC);

        $request = $this->psrFactory->createServerRequest('GET', '/users?age=25');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/users', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function integer_query_param_coercion_invalid_format_throws_type_mismatch(): void
    {
        $validator = $this->buildCoercionValidator(self::INTEGER_COERCION_SPEC);

        $request = $this->psrFactory->createServerRequest('GET', '/users?age=abc');

        $this->expectException(TypeMismatchError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function integer_query_param_coercion_float_string_for_integer_throws_type_mismatch(): void
    {
        $validator = $this->buildCoercionValidator(self::INTEGER_COERCION_SPEC);

        $request = $this->psrFactory->createServerRequest('GET', '/users?age=12.5');

        $this->expectException(TypeMismatchError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function integer_query_param_coercion_small_id_valid(): void
    {
        $validator = $this->buildCoercionValidator(self::INTEGER_OVERFLOW_SPEC);

        $request = $this->psrFactory->createServerRequest('GET', '/items?id=42');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/items', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function integer_query_param_coercion_php_int_max_valid(): void
    {
        $validator = $this->buildCoercionValidator(self::INTEGER_OVERFLOW_SPEC);

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/items?id=' . (string) PHP_INT_MAX,
        );

        $operation = $validator->validateRequest($request);

        $this->assertSame('/items', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function integer_query_param_coercion_php_int_max_plus_one_throws_type_mismatch(): void
    {
        $validator = $this->buildCoercionValidator(self::INTEGER_OVERFLOW_SPEC);

        $overflow = str_increment((string) PHP_INT_MAX);
        $request = $this->psrFactory->createServerRequest('GET', '/items?id=' . $overflow);

        $this->expectException(TypeMismatchError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function integer_query_param_coercion_huge_overflow_throws_type_mismatch(): void
    {
        $validator = $this->buildCoercionValidator(self::INTEGER_OVERFLOW_SPEC);

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/items?id=99999999999999999999',
        );

        $this->expectException(TypeMismatchError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function coerce_to_integer_php_int_max_returns_exact_value(): void
    {
        $param = new Parameter(schema: new Schema(type: 'integer'));

        $result = $this->paramCoercer->coerce((string) PHP_INT_MAX, $param, true, true);

        $this->assertSame(PHP_INT_MAX, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_to_integer_php_int_min_returns_exact_value(): void
    {
        $param = new Parameter(schema: new Schema(type: 'integer'));

        $result = $this->paramCoercer->coerce((string) PHP_INT_MIN, $param, true, true);

        $this->assertSame(PHP_INT_MIN, $result);
        $this->assertIsInt($result);
    }

    #[Test]
    public function coerce_to_integer_overflow_throws_type_mismatch(): void
    {
        $param = new Parameter(schema: new Schema(type: 'integer'));

        $this->expectException(TypeMismatchError::class);
        $this->paramCoercer->coerce('99999999999999999999', $param, true, true);
    }

    #[Test]
    public function coerce_to_integer_php_int_max_plus_one_throws_type_mismatch(): void
    {
        $param = new Parameter(schema: new Schema(type: 'integer'));

        $overflow = str_increment((string) PHP_INT_MAX);

        $this->expectException(TypeMismatchError::class);
        $this->paramCoercer->coerce($overflow, $param, true, true);
    }

    private function buildCoercionValidator(string $yaml): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();
    }
}
