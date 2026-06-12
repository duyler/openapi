<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Bugfix;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Responses;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\Webhooks;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\ConstError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\MultipleOfKeywordError;
use Duyler\OpenApi\Validator\Exception\UndefinedResponseException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Request\ParameterDeserializer;
use Duyler\OpenApi\Validator\Request\RequestValidator;
use Duyler\OpenApi\Validator\Request\CookieValidator;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use Duyler\OpenApi\Validator\Response\ResponseValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\AdditionalPropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ConstValidator;
use Duyler\OpenApi\Validator\SchemaValidator\NumericRangeValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Webhook\WebhookValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use NumberRejectStringB19;
use NumberTypeValidatorB19;
use PatternExportValidatorB13;
use UnicodeLengthValidatorB12;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use stdClass;

final class BugfixBatchTest extends TestCase
{
    #[Test]
    public function b09_response_definition_null_throws_exception(): void
    {
        $this->expectException(UndefinedResponseException::class);

        $pool = new ValidatorPool();
        $formatRegistry = BuiltinFormats::create();

        $validator = new ResponseValidatorWithContext(
            pool: $pool,
            document: new OpenApiDocument(
                openapi: '3.0.3',
                info: new InfoObject(title: 'Test', version: '1.0.0'),
            ),
            statelessValidators: new StatelessValidatorRegistry($pool, $formatRegistry),
            refResolver: new RefResolver(),
            formatRegistry: $formatRegistry,
        );

        $factory = new Psr17Factory();
        $response = $factory->createResponse(404);
        $operation = new Operation(
            responses: new Responses([]),
        );

        $validator->validate($response, $operation);
    }

    #[Test]
    public function b09_response_definition_null_after_resolve_throws_exception(): void
    {
        $this->expectException(UndefinedResponseException::class);

        $pool = new ValidatorPool();
        $formatRegistry = BuiltinFormats::create();

        $validator = new ResponseValidatorWithContext(
            pool: $pool,
            document: new OpenApiDocument(
                openapi: '3.0.3',
                info: new InfoObject(title: 'Test', version: '1.0.0'),
            ),
            statelessValidators: new StatelessValidatorRegistry($pool, $formatRegistry),
            refResolver: new RefResolver(),
            formatRegistry: $formatRegistry,
        );

        $factory = new Psr17Factory();
        $response = $factory->createResponse(404);

        $responses = new Responses(['default' => new stdClass()]);

        $operation = new Operation(
            responses: $responses,
        );

        $validator->validate($response, $operation);
    }

    #[Test]
    public function b09_response_definition_found_for_exact_status(): void
    {
        $pool = new ValidatorPool();
        $formatRegistry = BuiltinFormats::create();

        $validator = new ResponseValidatorWithContext(
            pool: $pool,
            document: new OpenApiDocument(
                openapi: '3.0.3',
                info: new InfoObject(title: 'Test', version: '1.0.0'),
            ),
            statelessValidators: new StatelessValidatorRegistry($pool, $formatRegistry),
            refResolver: new RefResolver(),
            formatRegistry: $formatRegistry,
        );

        $factory = new Psr17Factory();
        $response = $factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream('{"status":"ok"}'));

        $operation = new Operation(
            responses: new Responses([
                '200' => new Response(description: 'OK'),
            ]),
        );

        $validator->validate($response, $operation);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function b12_compiler_generates_mb_strlen_for_string_length(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string', minLength: 1, maxLength: 10);
        $code = $compiler->compile($schema, 'MbStrlenValidator');

        $this->assertStringContainsString("mb_strlen(\$data, 'UTF-8')", $code);
        $this->assertStringNotContainsString('strlen($data)', $code);
    }

    #[Test]
    public function b12_compiler_mb_strlen_validates_unicode_correctly(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string', minLength: 1, maxLength: 6);
        $code = $compiler->compile($schema, 'UnicodeLengthValidatorB12');

        $evalCode = substr($code, 5);
        eval($evalCode);

        $validator = new UnicodeLengthValidatorB12();
        $validator->validate('Привет');

        $this->expectException(RuntimeException::class);
        $validator->validate('Приветмир');
    }

    #[Test]
    public function b13_compiler_uses_var_export_for_pattern(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string', pattern: '^[a-z]+\.(special)$');
        $code = $compiler->compile($schema, 'PatternExportValidatorB13');

        $this->assertStringContainsString('preg_match', $code);
        $this->assertStringNotContainsString('addslashes', $code);

        $evalCode = substr($code, 5);
        eval($evalCode);

        $validator = new PatternExportValidatorB13();
        $validator->validate('test.special');
    }

    #[Test]
    public function b14_const_null_validates_null_data(): void
    {
        $pool = new ValidatorPool();
        $validator = new ConstValidator($pool, BuiltinFormats::create());

        $schema = new Schema(type: 'null', const: null, hasConst: true);

        $validator->validate(null, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function b14_const_null_rejects_non_null_data(): void
    {
        $pool = new ValidatorPool();
        $validator = new ConstValidator($pool, BuiltinFormats::create());

        $schema = new Schema(type: 'string', const: null, hasConst: true);

        $this->expectException(ConstError::class);

        $validator->validate('not-null', $schema);
    }

    #[Test]
    public function b14_const_not_set_skips_validation(): void
    {
        $pool = new ValidatorPool();
        $validator = new ConstValidator($pool, BuiltinFormats::create());

        $schema = new Schema(type: 'string', const: null, hasConst: false);

        $validator->validate('anything', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function b15_additional_properties_ignores_pattern_properties(): void
    {
        $pool = new ValidatorPool();
        $validator = new AdditionalPropertiesValidator($pool, BuiltinFormats::create());

        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
            ],
            patternProperties: [
                '^x-' => new Schema(type: 'string'),
            ],
            additionalProperties: false,
        );

        $validator->validate([
            'name' => 'John',
            'x-custom' => 'value',
        ], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function b15_additional_properties_rejects_non_pattern_extra_keys(): void
    {
        $pool = new ValidatorPool();
        $validator = new AdditionalPropertiesValidator($pool, BuiltinFormats::create());

        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
            ],
            patternProperties: [
                '^x-' => new Schema(type: 'string'),
            ],
            additionalProperties: false,
        );

        $this->expectException(ValidationException::class);

        $validator->validate([
            'name' => 'John',
            'unknown' => 'value',
        ], $schema);
    }

    #[Test]
    public function b17_json_serialize_preserves_null_default(): void
    {
        $schema = new Schema(
            type: 'string',
            default: null,
            hasDefault: true,
        );

        $serialized = $schema->jsonSerialize();

        $this->assertArrayHasKey('default', $serialized);
        $this->assertNull($serialized['default']);
    }

    #[Test]
    public function b17_json_serialize_omits_default_when_not_set(): void
    {
        $schema = new Schema(
            type: 'string',
            default: null,
            hasDefault: false,
        );

        $serialized = $schema->jsonSerialize();

        $this->assertArrayNotHasKey('default', $serialized);
    }

    #[Test]
    public function b17_json_serialize_const_null(): void
    {
        $schema = new Schema(
            type: 'null',
            const: null,
            hasConst: true,
        );

        $serialized = $schema->jsonSerialize();

        $this->assertArrayHasKey('const', $serialized);
        $this->assertNull($serialized['const']);
    }

    #[Test]
    public function b18_multiple_of_zero_throws_error(): void
    {
        $pool = new ValidatorPool();
        $validator = new NumericRangeValidator($pool, BuiltinFormats::create());

        $schema = new Schema(type: 'number', multipleOf: 0.0);

        $this->expectException(MultipleOfKeywordError::class);

        $validator->validate(42, $schema);
    }

    #[Test]
    public function b19_compiler_number_type_accepts_integer(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'number');
        $code = $compiler->compile($schema, 'NumberTypeValidatorB19');

        $this->assertStringContainsString('is_float($data)', $code);
        $this->assertStringContainsString('is_int($data)', $code);

        $evalCode = substr($code, 5);
        eval($evalCode);

        $validator = new NumberTypeValidatorB19();

        $validator->validate(3.14);
        $validator->validate(42);
        $validator->validate(0);
    }

    #[Test]
    public function b19_compiler_number_type_rejects_string(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'number');
        $code = $compiler->compile($schema, 'NumberRejectStringB19');

        $evalCode = substr($code, 5);
        eval($evalCode);

        $validator = new NumberRejectStringB19();

        $this->expectException(RuntimeException::class);
        $validator->validate('not-a-number');
    }

    #[Test]
    public function b20_webhook_validator_uses_request_path(): void
    {
        $requestValidator = $this->createMock(RequestValidator::class);
        $requestValidator->expects($this->once())->method('validate');

        $webhookValidator = new WebhookValidator($requestValidator);

        $factory = new Psr17Factory();
        $request = $factory->createServerRequest('POST', '/webhooks/payment');

        $operation = new Operation(
            responses: new Responses([]),
        );

        $pathItem = new PathItem(post: $operation);

        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            webhooks: new Webhooks(['payment.webhook' => $pathItem]),
        );

        $webhookValidator->validate($request, 'payment.webhook', $document);
    }

    #[Test]
    public function b07_type_coercer_throws_on_null_when_not_nullable(): void
    {
        $coercer = new TypeCoercer();
        $param = new Parameter(
            name: 'test',
            in: 'query',
            schema: new Schema(type: 'string'),
        );

        $this->expectException(TypeMismatchError::class);

        $coercer->coerce(null, $param, true);
    }

    #[Test]
    public function b07_type_coercer_returns_null_when_nullable(): void
    {
        $coercer = new TypeCoercer();
        $param = new Parameter(
            name: 'test',
            in: 'query',
            schema: new Schema(type: 'string', nullable: true),
        );

        $result = $coercer->coerce(null, $param, true);

        $this->assertNull($result);
    }

    #[Test]
    public function b07_type_coercer_returns_null_when_type_includes_null(): void
    {
        $coercer = new TypeCoercer();
        $param = new Parameter(
            name: 'test',
            in: 'query',
            schema: new Schema(type: ['string', 'null']),
        );

        $result = $coercer->coerce(null, $param, true);

        $this->assertNull($result);
    }

    #[Test]
    public function b08_cookie_validator_uses_separate_strict_flag(): void
    {
        $pool = new ValidatorPool();
        $schemaValidator = new SchemaValidator($pool, BuiltinFormats::create());
        $deserializer = new ParameterDeserializer();
        $coercer = new TypeCoercer();

        $cookieValidator = new CookieValidator($schemaValidator, $deserializer, $coercer, true);

        $cookies = ['page' => '42'];
        $parameterSchemas = [
            new Parameter(
                name: 'page',
                in: 'cookie',
                schema: new Schema(type: 'integer'),
            ),
        ];

        $cookieValidator->validate($cookies, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function b08_abstract_parameter_validator_uses_separate_strict_flag(): void
    {
        $pool = new ValidatorPool();
        $schemaValidator = new SchemaValidator($pool, BuiltinFormats::create());
        $deserializer = new ParameterDeserializer();
        $coercer = new TypeCoercer();

        $cookieValidator = new CookieValidator($schemaValidator, $deserializer, $coercer, true);

        $data = ['count' => '5'];
        $parameterSchemas = [
            new Parameter(
                name: 'count',
                in: 'cookie',
                schema: new Schema(type: 'integer'),
            ),
        ];

        $cookieValidator->validate($data, $parameterSchemas);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function b14_const_null_from_parsed_yaml_validates(): void
    {
        $yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: OK
components:
  schemas:
    NullConstSchema:
      type: null
      const: null
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $validator->validateSchema(null, '#/components/schemas/NullConstSchema');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function b17_default_null_from_parsed_yaml_serialized(): void
    {
        $yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: OK
components:
  schemas:
    DefaultNullSchema:
      type: string
      default: null
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $document = $validator->document;
        $schema = $document->components?->schemas['DefaultNullSchema'] ?? null;

        $this->assertNotNull($schema);
        $this->assertTrue($schema->hasDefault);
        $this->assertNull($schema->default);

        $serialized = $schema->jsonSerialize();
        $this->assertArrayHasKey('default', $serialized);
        $this->assertNull($serialized['default']);
    }

    #[Test]
    public function b17_default_not_set_has_default_false(): void
    {
        $yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: OK
components:
  schemas:
    NoDefaultSchema:
      type: string
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $document = $validator->document;
        $schema = $document->components?->schemas['NoDefaultSchema'] ?? null;

        $this->assertNotNull($schema);
        $this->assertFalse($schema->hasDefault);

        $serialized = $schema->jsonSerialize();
        $this->assertArrayNotHasKey('default', $serialized);
    }

    #[Test]
    public function b14_const_not_set_has_const_false(): void
    {
        $yaml = <<<'YAML'
openapi: 3.0.3
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: OK
components:
  schemas:
    NoConstSchema:
      type: string
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $document = $validator->document;
        $schema = $document->components?->schemas['NoConstSchema'] ?? null;

        $this->assertNotNull($schema);
        $this->assertFalse($schema->hasConst);

        $serialized = $schema->jsonSerialize();
        $this->assertArrayNotHasKey('const', $serialized);
    }

    #[Test]
    public function t38_deprecation_logger_receives_user_logger(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with($this->stringContains('example'));

        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: OK
components:
  schemas:
    TestSchema:
      type: string
      example: "test value"
YAML;

        OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withLogger($logger)
            ->build();
    }

    #[Test]
    public function t38_deprecation_logger_without_user_logger_works(): void
    {
        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: OK
components:
  schemas:
    TestSchema:
      type: string
      example: "test value"
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $this->assertNotNull($validator);
    }

    #[Test]
    public function t38_deprecation_logger_logs_allow_empty_value(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with($this->stringContains('allowEmptyValue'));

        $yaml = <<<'YAML'
openapi: 3.2.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      parameters:
        - name: q
          in: query
          allowEmptyValue: true
          schema:
            type: string
      responses:
        '200':
          description: OK
YAML;

        OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->withLogger($logger)
            ->build();
    }

    #[Test]
    public function b14_ref_resolver_preserves_has_const_for_null(): void
    {
        $refResolver = new RefResolver();

        $schema = new Schema(
            ref: '#/components/schemas/NullConst',
            hasConst: false,
        );

        $document = new OpenApiDocument(
            openapi: '3.0.3',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'NullConst' => new Schema(type: 'null', const: null, hasConst: true),
                ],
            ),
        );

        $resolved = $refResolver->resolveSchemaWithOverride($schema, $document);

        $this->assertTrue($resolved->hasConst);
        $this->assertNull($resolved->const);
    }

    #[Test]
    public function b17_ref_resolver_preserves_has_default_for_null(): void
    {
        $refResolver = new RefResolver();

        $schema = new Schema(
            ref: '#/components/schemas/NullDefault',
            hasDefault: false,
        );

        $document = new OpenApiDocument(
            openapi: '3.0.3',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'NullDefault' => new Schema(type: 'string', default: null, hasDefault: true),
                ],
            ),
        );

        $resolved = $refResolver->resolveSchemaWithOverride($schema, $document);

        $this->assertTrue($resolved->hasDefault);
        $this->assertNull($resolved->default);
    }
}
