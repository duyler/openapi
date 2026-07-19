<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Advanced;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\CoercionContext;
use Duyler\OpenApi\Validator\Exception\MaxItemsError;
use Duyler\OpenApi\Validator\Exception\MinItemsError;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Request\RequestBodyCoercer;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use Duyler\OpenApi\Validator\Response\ResponseTypeCoercer;
use Override;
use PHPUnit\Framework\Attributes\Test;

use function str_increment;

use function sprintf;

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

    private const string TC04_ARRAY_QUERY_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Array Query API
  version: 1.0.0
paths:
  /search:
    get:
      parameters:
        - name: tags
          in: query
          required: true
          style: form
          explode: true
          schema:
            type: array
            items:
              type: string
            minItems: 3
            maxItems: 3
      responses:
        '200':
          description: OK
YAML;

    private const string TC04_ARRAY_QUERY_CSV_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Array Query CSV API
  version: 1.0.0
paths:
  /search:
    get:
      parameters:
        - name: tags
          in: query
          required: true
          style: form
          explode: false
          schema:
            type: array
            items:
              type: string
            minItems: 3
            maxItems: 3
      responses:
        '200':
          description: OK
YAML;

    private const string TC05_ARRAY_BODY_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Array Body API
  version: 1.0.0
paths:
  /items:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: array
              items:
                type: object
                properties:
                  id:
                    type: integer
                    minimum: 1
                required:
                  - id
      responses:
        '200':
          description: OK
YAML;

    private const string TC06_RESPONSE_COERCION_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Response Coercion API
  version: 1.0.0
paths:
  /stats:
    get:
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                type: object
                properties:
                  count:
                    type: integer
                    minimum: 40
                required:
                  - count
YAML;

    private const string TC09_BOOLEAN_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Boolean Edge API
  version: 1.0.0
paths:
  /users:
    get:
      parameters:
        - name: active
          in: query
          required: true
          schema:
            type: boolean
      responses:
        '200':
          description: OK
YAML;

    private const string TC09_BOOLEAN_CONST_TRUE_SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Boolean Edge API
  version: 1.0.0
paths:
  /users:
    get:
      parameters:
        - name: active
          in: query
          required: true
          schema:
            type: boolean
            const: true
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

    #[Test]
    public function tc04_query_array_coercion_preserves_string_items_unchanged(): void
    {
        $param = new Parameter(schema: new Schema(
            type: 'array',
            items: new Schema(type: 'string'),
        ));

        $result = $this->paramCoercer->coerce(['a', 'b', 'c'], $param, true, true);

        $this->assertSame(['a', 'b', 'c'], $result);
    }

    #[Test]
    public function tc04_query_array_with_string_items_validates_full_cycle(): void
    {
        $validator = $this->buildCoercionValidator(self::TC04_ARRAY_QUERY_SPEC);

        $request = $this->psrFactory->createServerRequest('GET', '/search?tags[]=a&tags[]=b&tags[]=c');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/search', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function tc04_query_array_exceeding_max_items_throws_max_items_error(): void
    {
        $validator = $this->buildCoercionValidator(self::TC04_ARRAY_QUERY_SPEC);

        $request = $this->psrFactory->createServerRequest('GET', '/search?tags[]=a&tags[]=b&tags[]=c&tags[]=d');

        try {
            $validator->validateRequest($request);
            $this->fail('Expected MaxItemsError for 4 items with maxItems: 3');
        } catch (MaxItemsError $e) {
            $this->assertSame('maxItems', $e->keyword());
            $this->assertSame(3, $e->params()['maxItems']);
            $this->assertSame(4, $e->params()['actual']);
        }
    }

    #[Test]
    public function tc04_query_array_csv_explode_false_validates_full_cycle(): void
    {
        $validator = $this->buildCoercionValidator(self::TC04_ARRAY_QUERY_CSV_SPEC);

        $request = $this->psrFactory->createServerRequest('GET', '/search?tags=a,b,c');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/search', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function tc04_query_array_csv_explode_false_without_coercion_validates_full_cycle(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::TC04_ARRAY_QUERY_CSV_SPEC)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/search?tags=a,b,c');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/search', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function tc04_query_array_csv_explode_false_exceeding_max_items_throws_max_items_error(): void
    {
        $validator = $this->buildCoercionValidator(self::TC04_ARRAY_QUERY_CSV_SPEC);

        $request = $this->psrFactory->createServerRequest('GET', '/search?tags=a,b,c,d');

        try {
            $validator->validateRequest($request);
            $this->fail('Expected MaxItemsError for 4 CSV items with maxItems: 3');
        } catch (MaxItemsError $e) {
            $this->assertSame('maxItems', $e->keyword());
            $this->assertSame(3, $e->params()['maxItems']);
            $this->assertSame(4, $e->params()['actual']);
        }
    }

    #[Test]
    public function tc04_query_array_csv_explode_false_too_few_items_throws_min_items_error(): void
    {
        $validator = $this->buildCoercionValidator(self::TC04_ARRAY_QUERY_CSV_SPEC);

        $request = $this->psrFactory->createServerRequest('GET', '/search?tags=a,b');

        try {
            $validator->validateRequest($request);
            $this->fail('Expected MinItemsError for 2 CSV items with minItems: 3');
        } catch (MinItemsError $e) {
            $this->assertSame('minItems', $e->keyword());
            $this->assertSame(3, $e->params()['minItems']);
            $this->assertSame(2, $e->params()['actual']);
        }
    }

    #[Test]
    public function tc05_top_level_array_body_coerces_nested_object_id_to_integer(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'object',
                properties: [
                    'id' => new Schema(type: 'integer'),
                ],
            ),
        );

        $context = new CoercionContext(schema: $schema, enabled: true, strict: true);

        $result = $this->bodyCoercer->coerce([['id' => '1'], ['id' => '2']], $context);

        $this->assertSame(1, $result[0]['id']);
        $this->assertIsInt($result[0]['id']);

        $this->assertSame(2, $result[1]['id']);
        $this->assertIsInt($result[1]['id']);
    }

    #[Test]
    public function tc05_top_level_array_body_validates_full_cycle_with_coercion(): void
    {
        $validator = $this->buildCoercionValidator(self::TC05_ARRAY_BODY_SPEC);

        $request = $this->createRequest('POST', '/items', [['id' => '1'], ['id' => '2']]);

        $operation = $validator->validateRequest($request);

        $this->assertSame('/items', $operation->path);
        $this->assertSame('POST', $operation->method);
    }

    #[Test]
    public function tc05_top_level_array_body_invalid_id_throws_type_mismatch(): void
    {
        $validator = $this->buildCoercionValidator(self::TC05_ARRAY_BODY_SPEC);

        $request = $this->createRequest('POST', '/items', [['id' => 'abc']]);

        try {
            $validator->validateRequest($request);
            $this->fail('Expected TypeMismatchError for non-numeric string id in array body');
        } catch (TypeMismatchError $e) {
            $this->assertSame('integer', $e->params()['expected']);
            $this->assertSame('abc', $e->params()['actual']);
        }
    }

    #[Test]
    public function tc06_response_body_coerces_string_count_to_integer(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'count' => new Schema(type: 'integer'),
            ],
        );

        $context = new CoercionContext(schema: $schema, enabled: true, strict: true);

        $coercer = new ResponseTypeCoercer();
        $result = $coercer->coerce(['count' => '42'], $context);

        $this->assertSame(42, $result['count']);
        $this->assertIsInt($result['count']);
    }

    #[Test]
    public function tc06_response_body_string_integer_validates_full_cycle_with_coercion(): void
    {
        $validator = $this->buildCoercionValidator(self::TC06_RESPONSE_COERCION_SPEC);

        $request = $this->psrFactory->createServerRequest('GET', '/stats');
        $operation = $validator->validateRequest($request);

        $response = $this->createResponse(200, ['count' => '42']);

        $succeeded = false;
        try {
            $validator->validateResponse($response, $operation);
            $succeeded = true;
        } catch (TypeMismatchError|MinimumError $e) {
            $this->fail(sprintf(
                'Expected response coercion to convert "42" to int 42, got %s: %s',
                $e::class,
                $e->getMessage(),
            ));
        }

        $this->assertSame(true, $succeeded);
    }

    #[Test]
    public function tc06_response_body_non_numeric_count_throws_type_mismatch(): void
    {
        $validator = $this->buildCoercionValidator(self::TC06_RESPONSE_COERCION_SPEC);

        $request = $this->psrFactory->createServerRequest('GET', '/stats');
        $operation = $validator->validateRequest($request);

        $response = $this->createResponse(200, ['count' => 'abc']);

        try {
            $validator->validateResponse($response, $operation);
            $this->fail('Expected TypeMismatchError for non-numeric count in response body');
        } catch (TypeMismatchError $e) {
            $this->assertSame('integer', $e->params()['expected']);
            $this->assertSame('abc', $e->params()['actual']);
        }
    }

    #[Test]
    public function tc09_boolean_coercion_true_string_passes_const_true_schema(): void
    {
        $validator = $this->buildCoercionValidator(self::TC09_BOOLEAN_CONST_TRUE_SPEC);

        $request = $this->psrFactory->createServerRequest('GET', '/users?active=true');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/users', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function tc09_boolean_coercion_null_literal_string_throws_type_mismatch(): void
    {
        $validator = $this->buildCoercionValidator(self::TC09_BOOLEAN_SPEC);

        $request = $this->psrFactory->createServerRequest('GET', '/users?active=null');

        try {
            $validator->validateRequest($request);
            $this->fail('Expected TypeMismatchError for literal string "null" against boolean schema');
        } catch (TypeMismatchError $e) {
            $this->assertSame('boolean', $e->params()['expected']);
            $this->assertSame('null', $e->params()['actual']);
        }
    }

    #[Test]
    public function tc09_boolean_coercion_empty_string_throws_type_mismatch_in_strict_mode(): void
    {
        $validator = $this->buildCoercionValidator(self::TC09_BOOLEAN_SPEC);

        $request = $this->psrFactory->createServerRequest('GET', '/users?active=');

        try {
            $validator->validateRequest($request);
            $this->fail('Expected TypeMismatchError for empty string against boolean schema in strict mode');
        } catch (TypeMismatchError $e) {
            $this->assertSame('boolean', $e->params()['expected']);
            $this->assertSame('', $e->params()['actual']);
        }
    }

    #[Test]
    public function tc09_boolean_coercion_object_literal_string_throws_type_mismatch(): void
    {
        $validator = $this->buildCoercionValidator(self::TC09_BOOLEAN_SPEC);

        $request = $this->psrFactory->createServerRequest('GET', '/users?active=%7B%7D');

        try {
            $validator->validateRequest($request);
            $this->fail('Expected TypeMismatchError for object literal string "{}" against boolean schema');
        } catch (TypeMismatchError $e) {
            $this->assertSame('boolean', $e->params()['expected']);
            $this->assertSame('{}', $e->params()['actual']);
        }
    }

    #[Test]
    public function tc09_boolean_coercion_array_value_throws_type_mismatch(): void
    {
        $validator = $this->buildCoercionValidator(self::TC09_BOOLEAN_SPEC);

        $request = $this->psrFactory->createServerRequest('GET', '/users?active[]=x');

        $caught = null;
        try {
            $validator->validateRequest($request);
            $this->fail('Expected TypeMismatchError for array value against boolean schema');
        } catch (TypeMismatchError $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame('boolean', $caught->params()['expected']);
    }

    #[Test]
    public function disable_strict_coercion_allows_legacy_lax_boolean_cast_for_unknown_string(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::TC09_BOOLEAN_SPEC)
            ->enableCoercion()
            ->disableStrictCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/users?active=admin');

        $operation = $validator->validateRequest($request);

        $this->assertSame('/users', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function strict_coercion_default_throws_type_mismatch_for_unknown_boolean_string(): void
    {
        $validator = $this->buildCoercionValidator(self::TC09_BOOLEAN_SPEC);

        $request = $this->psrFactory->createServerRequest('GET', '/users?active=admin');

        try {
            $validator->validateRequest($request);
            $this->fail('Expected TypeMismatchError for "admin" against boolean schema in strict mode');
        } catch (TypeMismatchError $e) {
            $this->assertSame('boolean', $e->params()['expected']);
            $this->assertSame('admin', $e->params()['actual']);
        }
    }

    private function buildCoercionValidator(string $yaml): OpenApiValidatorInterface
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();
    }
}
