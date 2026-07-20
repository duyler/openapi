<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Request;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\ConstError;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Request\ParameterDeserializer;
use Duyler\OpenApi\Validator\Request\QueryParser;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;

final class QueryParameterEdgeCasesTest extends TestCase
{
    private Psr17Factory $psrFactory;

    private QueryParser $queryParser;

    private ParameterDeserializer $deserializer;

    private TypeCoercer $coercer;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
        $this->queryParser = new QueryParser();
        $this->deserializer = new ParameterDeserializer();
        $this->coercer = new TypeCoercer();
    }

    #[Test]
    public function deep_object_flat_keys_parse_to_associative_array(): void
    {
        $result = $this->queryParser->parse('filters[name]=John&filters[age]=30');

        $this->assertSame(
            ['filters' => ['name' => 'John', 'age' => '30']],
            $result,
        );
    }

    #[Test]
    public function deep_object_flat_keys_deserialize_preserves_object(): void
    {
        $param = new Parameter(
            name: 'filters',
            in: 'query',
            style: 'deepObject',
            explode: true,
            schema: new Schema(
                type: 'object',
                properties: [
                    'name' => new Schema(type: 'string'),
                    'age' => new Schema(type: 'string'),
                ],
            ),
        );

        $result = $this->deserializer->deserialize(['name' => 'John', 'age' => '30'], $param);

        $this->assertSame(['name' => 'John', 'age' => '30'], $result);
    }

    #[Test]
    public function deep_object_two_level_nesting_parses_correctly(): void
    {
        $result = $this->queryParser->parse('filters[a][b]=value');

        $this->assertSame(
            ['filters' => ['a' => ['b' => 'value']]],
            $result,
        );
    }

    #[Test]
    public function deep_object_two_level_deserialize_preserves_structure(): void
    {
        $param = new Parameter(
            name: 'filters',
            in: 'query',
            style: 'deepObject',
            explode: true,
        );

        $result = $this->deserializer->deserialize(['a' => ['b' => 'value']], $param);

        $this->assertSame(['a' => ['b' => 'value']], $result);
    }

    #[Test]
    public function deep_object_five_level_nesting_parses_correctly(): void
    {
        $result = $this->queryParser->parse('filters[a][b][c][d][e]=v');

        $this->assertSame(
            ['filters' => ['a' => ['b' => ['c' => ['d' => ['e' => 'v']]]]]],
            $result,
        );
    }

    #[Test]
    public function deep_object_five_level_deserialize_preserves_full_structure(): void
    {
        $param = new Parameter(
            name: 'filters',
            in: 'query',
            style: 'deepObject',
            explode: true,
        );

        $result = $this->deserializer->deserialize(
            ['a' => ['b' => ['c' => ['d' => ['e' => 'v']]]]],
            $param,
        );

        $this->assertSame(
            ['a' => ['b' => ['c' => ['d' => ['e' => 'v']]]]],
            $result,
        );
    }

    #[Test]
    public function deep_object_full_validation_cycle_with_flat_keys(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Deep Object API
  version: 1.0.0
paths:
  /items/{itemId}:
    get:
      parameters:
        - name: itemId
          in: path
          required: true
          schema:
            type: string
        - name: filters
          in: query
          style: deepObject
          explode: true
          schema:
            type: object
            properties:
              name:
                type: string
              status:
                type: string
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/items/abc?filters[name]=John&filters[status]=active',
        );

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/items/{itemId}', $operation->path);
    }

    #[Test]
    public function deep_object_nested_property_type_mismatch_throws_type_mismatch(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Deep Object API
  version: 1.0.0
paths:
  /items/{itemId}:
    get:
      parameters:
        - name: itemId
          in: path
          required: true
          schema:
            type: string
        - name: filters
          in: query
          style: deepObject
          explode: true
          schema:
            type: object
            properties:
              a:
                type: string
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/items/abc?filters[a][b]=value',
        );

        $caught = null;

        try {
            $validator->validateRequest($request);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e->getErrors()[0] ?? null;
        }

        self::assertInstanceOf(TypeMismatchError::class, $caught);
    }

    #[Test]
    public function deep_object_unknown_property_with_additional_properties_false_throws_validation_error(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Deep Object API
  version: 1.0.0
paths:
  /items/{itemId}:
    get:
      parameters:
        - name: itemId
          in: path
          required: true
          schema:
            type: string
        - name: filters
          in: query
          style: deepObject
          explode: true
          schema:
            type: object
            properties:
              name:
                type: string
            additionalProperties: false
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/items/abc?filters[unknown]=x',
        );

        $this->expectException(ValidationException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function form_explode_bracket_syntax_parses_to_numeric_array(): void
    {
        $result = $this->queryParser->parse('tags[]=php&tags[]=go');

        $this->assertSame(['tags' => ['php', 'go']], $result);
    }

    #[Test]
    public function form_explode_bracket_syntax_three_values_parses_to_numeric_array(): void
    {
        $result = $this->queryParser->parse('tags[]=php&tags[]=go&tags[]=java');

        $this->assertSame(['tags' => ['php', 'go', 'java']], $result);
    }

    #[Test]
    public function form_explode_duplicate_keys_parse_to_array(): void
    {
        $result = $this->queryParser->parse('tags=php&tags=go');

        $this->assertSame(['tags' => ['php', 'go']], $result);
    }

    #[Test]
    public function form_explode_deserialize_preserves_array(): void
    {
        $param = new Parameter(
            name: 'tags',
            in: 'query',
            style: 'form',
            explode: true,
            schema: new Schema(
                type: 'array',
                items: new Schema(type: 'string'),
            ),
        );

        $result = $this->deserializer->deserialize(['php', 'go', 'java'], $param);

        $this->assertSame(['php', 'go', 'java'], $result);
    }

    #[Test]
    public function form_explode_full_validation_cycle(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Form Explode API
  version: 1.0.0
paths:
  /items/{itemId}:
    get:
      parameters:
        - name: itemId
          in: path
          required: true
          schema:
            type: string
        - name: tags
          in: query
          style: form
          explode: true
          schema:
            type: array
            items:
              type: string
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/items/abc?tags[]=php&tags[]=go&tags[]=java',
        );

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/items/{itemId}', $operation->path);
    }

    #[Test]
    public function form_explode_comma_separated_value_does_not_split(): void
    {
        $parsed = $this->queryParser->parse('tags=php,go');

        $this->assertSame(['tags' => 'php,go'], $parsed);

        $param = new Parameter(
            name: 'tags',
            in: 'query',
            style: 'form',
            explode: true,
            schema: new Schema(
                type: 'array',
                items: new Schema(type: 'string'),
            ),
        );

        $deserialized = $this->deserializer->deserialize('php,go', $param);

        $this->assertSame('php,go', $deserialized);
    }

    #[Test]
    public function form_explode_comma_separated_value_full_cycle_throws_type_mismatch(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Form Explode API
  version: 1.0.0
paths:
  /items/{itemId}:
    get:
      parameters:
        - name: itemId
          in: path
          required: true
          schema:
            type: string
        - name: tags
          in: query
          style: form
          explode: true
          schema:
            type: array
            items:
              type: string
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/items/abc?tags=php,go',
        );

        $this->expectException(TypeMismatchError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function form_explode_duplicate_keys_full_validation_cycle(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Form Explode API
  version: 1.0.0
paths:
  /items/{itemId}:
    get:
      parameters:
        - name: itemId
          in: path
          required: true
          schema:
            type: string
        - name: tags
          in: query
          style: form
          explode: true
          schema:
            type: array
            items:
              type: string
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest(
            'GET',
            '/items/abc?tags=php&tags=go',
        );

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
        $this->assertSame('/items/{itemId}', $operation->path);
    }

    #[Test]
    public function boolean_coercion_true_string_yields_true(): void
    {
        $param = new Parameter(schema: new Schema(type: 'boolean'));

        $result = $this->coercer->coerce('true', $param, true);

        $this->assertSame(true, $result);
    }

    #[Test]
    public function boolean_coercion_false_string_yields_false(): void
    {
        $param = new Parameter(schema: new Schema(type: 'boolean'));

        $result = $this->coercer->coerce('false', $param, true);

        $this->assertSame(false, $result);
    }

    #[Test]
    public function boolean_coercion_one_string_yields_true(): void
    {
        $param = new Parameter(schema: new Schema(type: 'boolean'));

        $result = $this->coercer->coerce('1', $param, true);

        $this->assertSame(true, $result);
    }

    #[Test]
    public function boolean_coercion_zero_string_yields_false(): void
    {
        $param = new Parameter(schema: new Schema(type: 'boolean'));

        $result = $this->coercer->coerce('0', $param, true);

        $this->assertSame(false, $result);
    }

    #[Test]
    public function boolean_coercion_yes_string_yields_true(): void
    {
        $param = new Parameter(schema: new Schema(type: 'boolean'));

        $result = $this->coercer->coerce('yes', $param, true);

        $this->assertSame(true, $result);
    }

    #[Test]
    public function boolean_coercion_no_string_yields_false(): void
    {
        $param = new Parameter(schema: new Schema(type: 'boolean'));

        $result = $this->coercer->coerce('no', $param, true);

        $this->assertSame(false, $result);
    }

    #[Test]
    public function boolean_coercion_on_string_yields_true(): void
    {
        $param = new Parameter(schema: new Schema(type: 'boolean'));

        $result = $this->coercer->coerce('on', $param, true);

        $this->assertSame(true, $result);
    }

    #[Test]
    public function boolean_coercion_off_string_yields_false(): void
    {
        $param = new Parameter(schema: new Schema(type: 'boolean'));

        $result = $this->coercer->coerce('off', $param, true);

        $this->assertSame(false, $result);
    }

    #[Test]
    public function boolean_coercion_strict_rejects_invalid_string(): void
    {
        $param = new Parameter(schema: new Schema(type: 'boolean'));

        $this->expectException(TypeMismatchError::class);

        $this->coercer->coerce('invalid', $param, true, true);
    }

    #[Test]
    public function boolean_coercion_strict_rejects_admin_string(): void
    {
        $param = new Parameter(schema: new Schema(type: 'boolean'));

        $this->expectException(TypeMismatchError::class);

        $this->coercer->coerce('admin', $param, true, true);
    }

    #[Test]
    public function boolean_coercion_strict_accepts_true_string(): void
    {
        $param = new Parameter(schema: new Schema(type: 'boolean'));

        $result = $this->coercer->coerce('true', $param, true, true);

        $this->assertSame(true, $result);
    }

    #[Test]
    public function boolean_coercion_strict_accepts_false_string(): void
    {
        $param = new Parameter(schema: new Schema(type: 'boolean'));

        $result = $this->coercer->coerce('false', $param, true, true);

        $this->assertSame(false, $result);
    }

    #[Test]
    public function boolean_coercion_empty_string_falls_back_to_falsy(): void
    {
        $param = new Parameter(schema: new Schema(type: 'boolean'));

        $result = $this->coercer->coerce('', $param, true, false);

        $this->assertSame(false, $result);
    }

    #[Test]
    public function boolean_coercion_disabled_keeps_string_throws_type_mismatch(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Boolean API
  version: 1.0.0
paths:
  /flag:
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

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/flag?active=true');

        $this->expectException(TypeMismatchError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function boolean_coercion_yes_passes_const_true_schema(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Boolean API
  version: 1.0.0
paths:
  /flag:
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

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/flag?active=yes');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function boolean_coercion_yes_fails_const_false_schema(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Boolean API
  version: 1.0.0
paths:
  /flag:
    get:
      parameters:
        - name: active
          in: query
          required: true
          schema:
            type: boolean
            const: false
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/flag?active=yes');

        $this->expectException(ConstError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function boolean_coercion_off_passes_const_false_schema(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Boolean API
  version: 1.0.0
paths:
  /flag:
    get:
      parameters:
        - name: active
          in: query
          required: true
          schema:
            type: boolean
            const: false
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/flag?active=off');

        $operation = $validator->validateRequest($request);

        $this->assertSame('GET', $operation->method);
    }

    #[Test]
    public function boolean_coercion_off_fails_const_true_schema(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Boolean API
  version: 1.0.0
paths:
  /flag:
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

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/flag?active=off');

        $this->expectException(ConstError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function boolean_coercion_invalid_string_rejected_in_full_cycle(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Boolean API
  version: 1.0.0
paths:
  /flag:
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

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/flag?active=invalid');

        $this->expectException(TypeMismatchError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function boolean_coercion_strict_rejects_invalid_string_in_request_body(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Boolean API
  version: 1.0.0
paths:
  /flag:
    post:
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                active:
                  type: boolean
              required:
                - active
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('POST', '/flag')
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $this->psrFactory->createStream('{"active":"invalid"}'),
            );

        $this->expectException(TypeMismatchError::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function boolean_coercion_off_rejected_by_enum_true_schema(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Boolean API
  version: 1.0.0
paths:
  /flag:
    get:
      parameters:
        - name: active
          in: query
          required: true
          schema:
            type: boolean
            enum: [true]
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->enableCoercion()
            ->build();

        $request = $this->psrFactory->createServerRequest('GET', '/flag?active=off');

        $this->expectException(EnumError::class);
        $validator->validateRequest($request);
    }

    /**
     * QP-02: Duplicate scalar keys `?a=1&a=2` — actual behavior is form-style
     * implode, NOT PHP's pre-8.5 last-value-wins and NOT array form.
     *
     * QueryParser collects duplicate scalar pairs into an indexed array
     * `['1', '2']` (see QueryParser::resolveGroup allScalar branch). Then
     * ParameterDeserializer::deserializeForm with explode=false implodes
     * the array to the comma-separated string "1,2". The resulting string
     * validates against `type: string`.
     *
     * This test documents the value via a `const: '1,2'` constraint:
     * successful validation proves the duplicate keys were collapsed to
     * the string "1,2" (form-style), not "2" (last-value) and not the
     * array ["1","2"].
     */
    #[Test]
    public function qp_02_duplicate_scalar_keys_collapse_to_form_imploded_string(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: QP-02 Duplicate API
  version: 1.0.0
paths:
  /search:
    get:
      parameters:
        - name: a
          in: query
          required: true
          schema:
            type: string
            const: '1,2'
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $operation = $validator->validateRequest(
            $this->psrFactory->createServerRequest('GET', '/search?a=1&a=2'),
        );

        $this->assertSame('/search', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    /**
     * QP-02 negative: const='2' (last-value semantics) — confirms the
     * validator does NOT use last-value-wins. The actual stored value is
     * "1,2" (comma-joined), not "2".
     */
    #[Test]
    public function qp_02_duplicate_scalar_keys_do_not_use_last_value_semantics(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: QP-02 Duplicate API
  version: 1.0.0
paths:
  /search:
    get:
      parameters:
        - name: a
          in: query
          required: true
          schema:
            type: string
            const: '2'
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
                $this->psrFactory->createServerRequest('GET', '/search?a=1&a=2'),
            );
            self::fail('Expected ConstError for last-value semantics on duplicate query keys');
        } catch (ConstError $error) {
            $caught = $error;
        }

        self::assertInstanceOf(ConstError::class, $caught);
        self::assertSame('const', $caught->keyword());
        self::assertSame('1,2', $caught->params()['actual']);
        self::assertSame('2', $caught->params()['expected']);
    }

    /**
     * QP-02 negative: type=array with duplicate keys. The form-style
     * implode collapses the array back to a string "1,2", which fails
     * the `type: array` check with TypeMismatchError.
     */
    #[Test]
    public function qp_02_duplicate_scalar_keys_with_array_schema_throws_type_mismatch(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: QP-02 Duplicate API
  version: 1.0.0
paths:
  /search:
    get:
      parameters:
        - name: a
          in: query
          required: true
          schema:
            type: array
            items:
              type: string
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
                $this->psrFactory->createServerRequest('GET', '/search?a=1&a=2'),
            );
            self::fail('Expected TypeMismatchError for duplicate scalar keys against array schema');
        } catch (TypeMismatchError $error) {
            $caught = $error;
        }

        self::assertInstanceOf(TypeMismatchError::class, $caught);
        self::assertSame('type', $caught->keyword());
        self::assertSame('array', $caught->params()['expected']);
        self::assertSame('string', $caught->params()['actual']);
    }

    /**
     * QP-04: `style: form` with comma-separated value `?color=black,white`
     * against `type: array` — ParameterDeserializer::deserializeForm splits
     * the string into the array ["black", "white"], which then validates
     * against the array schema.
     *
     * The actual split into array form is observed through `items.enum`:
     * each split value must be in `[black, white]`. Successful validation
     * proves the comma-separated value was deserialized to a 2-element
     * array.
     */
    #[Test]
    public function qp_04_form_style_comma_separated_value_splits_to_array(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: QP-04 Form Object API
  version: 1.0.0
paths:
  /search:
    get:
      parameters:
        - name: color
          in: query
          style: form
          required: true
          schema:
            type: array
            items:
              type: string
              enum: [black, white]
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $operation = $validator->validateRequest(
            $this->psrFactory->createServerRequest('GET', '/search?color=black,white'),
        );

        $this->assertSame('/search', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    /**
     * QP-04 negative: comma-separated value contains an item not in the
     * items.enum — EnumError on the offending item at items index 1.
     */
    #[Test]
    public function qp_04_form_style_invalid_item_value_throws_enum_error(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: QP-04 Form Object API
  version: 1.0.0
paths:
  /search:
    get:
      parameters:
        - name: color
          in: query
          style: form
          required: true
          schema:
            type: array
            items:
              type: string
              enum: [black, white]
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
                $this->psrFactory->createServerRequest('GET', '/search?color=black,red'),
            );
            self::fail('Expected EnumError for item value not in allowed enum');
        } catch (ValidationException $error) {
            $caught = $error;
        }

        $errors = $caught->getErrors();

        self::assertGreaterThan(0, count($errors));
        self::assertInstanceOf(EnumError::class, $errors[0]);
        self::assertSame('enum', $errors[0]->keyword());
        self::assertSame(['black', 'white'], $errors[0]->params()['allowed']);
        self::assertSame('red', $errors[0]->params()['actual']);
    }

    /**
     * QP-04 negative: `style: form` against `type: object`.
     *
     * ParameterDeserializer splits the comma-separated value to an indexed
     * array ["black", "white"]. JSON Schema treats indexed arrays and
     * objects as distinct types, so `type: object` rejects the array form
     * with TypeMismatchError.
     *
     * This documents the actual implementation: form-style object
     * serialization is not supported by the deserializer.
     */
    #[Test]
    public function qp_04_form_style_against_object_schema_throws_type_mismatch(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: QP-04 Form Object API
  version: 1.0.0
paths:
  /search:
    get:
      parameters:
        - name: color
          in: query
          style: form
          required: true
          schema:
            type: object
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
                $this->psrFactory->createServerRequest('GET', '/search?color=black,white'),
            );
            self::fail('Expected TypeMismatchError for form-style value against object schema');
        } catch (TypeMismatchError $error) {
            $caught = $error;
        }

        self::assertInstanceOf(TypeMismatchError::class, $caught);
        self::assertSame('type', $caught->keyword());
        self::assertSame('object', $caught->params()['expected']);
        self::assertSame('array', $caught->params()['actual']);
    }

    /**
     * QP-06: Special characters in query values are URL-decoded by the
     * QueryParser (via `urldecode($rawValue)`). The decoded value is then
     * validated against the schema constraint.
     *
     * PSR-7 preserves the raw encoded form in `getUri()->getQuery()`. The
     * QueryParser decodes both `%XX` sequences and `+` (plus sign) to
     * space. This is verified by `const` constraints that match the
     * decoded form, not the raw form.
     *
     * @param non-empty-string $rawQueryValue the raw value placed in the URL (already URL-encoded where needed)
     * @param non-empty-string $expectedDecodedValue the value the validator should see after URL-decoding
     */
    #[Test]
    #[DataProvider('provideSpecialCharacterCases')]
    public function qp_06_special_characters_in_query_value_url_decoded_correctly(
        string $rawQueryValue,
        string $expectedDecodedValue,
    ): void {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: QP-06 Special Characters API
  version: 1.0.0
paths:
  /search:
    get:
      parameters:
        - name: q
          in: query
          required: true
          schema:
            type: string
            const: '{$expectedDecodedValue}'
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $operation = $validator->validateRequest(
            $this->psrFactory->createServerRequest('GET', '/search?q=' . $rawQueryValue),
        );

        $this->assertSame('/search', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    /**
     * QP-06 negative: raw encoded value does NOT satisfy a const that
     * matches the raw (encoded) form. The validator sees the decoded
     * form only, so a const against the encoded form must fail.
     *
     * Uses `?q=hello%20world` (encoded space) against `const: 'hello%20world'`
     * (literal percent-twenty-zero). ConstError actual='hello world'
     * (decoded) vs expected='hello%20world' (raw).
     */
    #[Test]
    public function qp_06_url_encoded_value_does_not_match_raw_const(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: QP-06 Special Characters API
  version: 1.0.0
paths:
  /search:
    get:
      parameters:
        - name: q
          in: query
          required: true
          schema:
            type: string
            const: 'hello%20world'
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
                $this->psrFactory->createServerRequest('GET', '/search?q=hello%20world'),
            );
            self::fail('Expected ConstError because the validator sees the decoded form, not the raw encoded form');
        } catch (ConstError $error) {
            $caught = $error;
        }

        self::assertInstanceOf(ConstError::class, $caught);
        self::assertSame('const', $caught->keyword());
        self::assertSame('hello world', $caught->params()['actual']);
        self::assertSame('hello%20world', $caught->params()['expected']);
    }

    /**
     * QP-06 unit-level: QueryParser URL-decodes both `%XX` sequences and
     * `+` to space, matching the application/x-www-form-urlencoded spec
     * (RFC 1866 §8.2.1). This complements the full-cycle test by
     * verifying the parser behavior in isolation.
     *
     * @param non-empty-string $rawQuery the raw query string passed to the parser
     * @param non-empty-string $expectedValue the expected decoded value for key 'q'
     */
    #[Test]
    #[DataProvider('provideSpecialCharacterParserCases')]
    public function qp_06_query_parser_url_decodes_special_characters(string $rawQuery, string $expectedValue): void
    {
        $result = $this->queryParser->parse($rawQuery);

        $this->assertSame(['q' => $expectedValue], $result);
    }

    /**
     * QP-07: Brackets in keys without `style: deepObject` — `?arr[0]=a`.
     *
     * QueryParser uses a segment-based parser (insertNested + assignSegments)
     * for bracket keys (resolveGroup allScalar=false branch), producing
     * `['arr' => ['a']]`. For a schema with `type: string`,
     * ParameterDeserializer::deserializeForm implodes the single-element
     * array to "a", which satisfies the `const: 'a'` constraint.
     *
     * This documents the actual behavior: bracket notation without
     * deepObject is parsed to array form, then collapsed by form-style
     * implode when the schema type is string.
     */
    #[Test]
    public function qp_07_bracket_key_without_deep_object_collapses_to_string(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: QP-07 Brackets API
  version: 1.0.0
paths:
  /search:
    get:
      parameters:
        - name: arr
          in: query
          required: true
          schema:
            type: string
            const: 'a'
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $operation = $validator->validateRequest(
            $this->psrFactory->createServerRequest('GET', '/search?arr[0]=a'),
        );

        $this->assertSame('/search', $operation->path);
        $this->assertSame('GET', $operation->method);
    }

    /**
     * QP-07 detail: multiple indexed brackets `?arr[0]=a&arr[1]=b`
     * collapse to the form-imploded string "a,b" against a string schema.
     */
    #[Test]
    public function qp_07_multiple_bracket_keys_collapse_to_form_imploded_string(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: QP-07 Brackets API
  version: 1.0.0
paths:
  /search:
    get:
      parameters:
        - name: arr
          in: query
          required: true
          schema:
            type: string
            const: 'a,b'
      responses:
        '200':
          description: OK
YAML;
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $operation = $validator->validateRequest(
            $this->psrFactory->createServerRequest('GET', '/search?arr[0]=a&arr[1]=b'),
        );

        $this->assertSame('/search', $operation->path);
    }

    /**
     * QP-07 negative: bracket keys against `type: array` schema.
     *
     * The form-style implode collapses the array back to a string, which
     * fails the `type: array` check. To use bracket notation for array
     * query parameters, the schema must use `style: deepObject` or the
     * request must use bracket-explode syntax `?arr[]=a` (covered by
     * `form_explode_*` tests above).
     */
    #[Test]
    public function qp_07_bracket_keys_with_array_schema_throws_type_mismatch(): void
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: QP-07 Brackets API
  version: 1.0.0
paths:
  /search:
    get:
      parameters:
        - name: arr
          in: query
          required: true
          schema:
            type: array
            items:
              type: string
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
                $this->psrFactory->createServerRequest('GET', '/search?arr[0]=a'),
            );
            self::fail('Expected TypeMismatchError for bracket key against array schema without deepObject');
        } catch (TypeMismatchError $error) {
            $caught = $error;
        }

        self::assertInstanceOf(TypeMismatchError::class, $caught);
        self::assertSame('type', $caught->keyword());
        self::assertSame('array', $caught->params()['expected']);
        self::assertSame('string', $caught->params()['actual']);
    }

    /**
     * QP-07 unit-level: QueryParser parses bracket keys via segment-based
     * parser (insertNested + assignSegments), producing nested array
     * structures. This complements the full-cycle test by verifying the
     * parser behavior in isolation.
     */
    #[Test]
    public function qp_07_query_parser_bracket_key_produces_nested_array(): void
    {
        $result = $this->queryParser->parse('arr[0]=a');

        $this->assertSame(['arr' => ['a']], $result);
    }

    /**
     * @return array<non-empty-string, array{non-empty-string, non-empty-string}>
     */
    public static function provideSpecialCharacterCases(): array
    {
        return [
            'url-encoded space %20' => ['hello%20world', 'hello world'],
            'plus treated as space' => ['a+b', 'a b'],
            'url-encoded plus' => ['hello%2Bworld', 'hello+world'],
            'url-encoded percent' => ['100%25', '100%'],
            'url-encoded slash' => ['foo%2Fbar', 'foo/bar'],
            'url-encoded ampersand' => ['a%26b', 'a&b'],
            'url-encoded euro sign' => ['%E2%82%AC', '€'],
        ];
    }

    /**
     * @return array<non-empty-string, array{non-empty-string, non-empty-string}>
     */
    public static function provideSpecialCharacterParserCases(): array
    {
        return [
            'url-encoded space' => ['q=hello%20world', 'hello world'],
            'plus as space' => ['q=a+b', 'a b'],
            'url-encoded plus' => ['q=hello%2Bworld', 'hello+world'],
            'url-encoded cyrillic' => ['q=%D0%98%D0%B2%D0%B0%D0%BD', 'Иван'],
            'raw space (unencoded)' => ['q=hello world', 'hello world'],
        ];
    }
}
