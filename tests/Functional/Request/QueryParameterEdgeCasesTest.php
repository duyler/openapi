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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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

        $this->expectException(TypeMismatchError::class);
        $validator->validateRequest($request);
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

        $result = $this->coercer->coerce('', $param, true);

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
}
