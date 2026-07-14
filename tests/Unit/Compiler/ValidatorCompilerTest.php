<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler;

use Duyler\OpenApi\Compiler\CompilationCacheInterface;
use Duyler\OpenApi\Compiler\Exception\UnsupportedKeywordException;
use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ConstCorrectValidator;
use ConstIntValidator;
use ConstWrongValidator;
use EnumInlineValuesValidator;
use MultipleOfInvalidValidator;
use MultipleOfValidValidator;
use NestedRejectExtraValidator;
use NumberIntValidator;
use NumberRejectStringValidator;
use RejectExtraPropValidator;
use UnionAcceptsIntegerValidator;
use UnionAcceptsStringValidator;
use UnionRejectsFloatValidator;
use DeepNestInvalidValidator;
use DeepNestValidValidator;
use TenLevelsValidator;
use TwentyLevelsValidator;

use const TOKEN_PARSE;

final class ValidatorCompilerTest extends TestCase
{
    #[Test]
    public function compile_generates_valid_php_code(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string');
        $code = $compiler->compile($schema, 'TestValidator');

        $this->assertStringContainsString('readonly class TestValidator', $code);
        $this->assertStringContainsString('public function validate(mixed $data): void', $code);
    }

    #[Test]
    public function compile_generates_type_check(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string');
        $code = $compiler->compile($schema, 'StringValidator');

        $this->assertStringContainsString('is_string($data)', $code);
    }

    #[Test]
    public function compile_generates_enum_check(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string', enum: ['a', 'b', 'c']);
        $code = $compiler->compile($schema, 'EnumValidator');

        $this->assertStringContainsString('in_array($data', $code);
    }

    #[Test]
    public function compile_generates_length_check(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string', minLength: 1, maxLength: 100);
        $code = $compiler->compile($schema, 'LengthValidator');

        $this->assertStringContainsString("mb_strlen(\$data, 'UTF-8')", $code);
    }

    #[Test]
    public function compile_generates_pattern_check(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string', pattern: '^[a-z]+$');
        $code = $compiler->compile($schema, 'PatternValidator');

        $this->assertStringContainsString('preg_match', $code);
    }

    #[Test]
    public function compile_generates_number_range_check(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'number', minimum: 0, maximum: 100);
        $code = $compiler->compile($schema, 'RangeValidator');

        $this->assertStringContainsString('$data <', $code);
        $this->assertStringContainsString('$data >', $code);
    }

    #[Test]
    public function compile_schema_with_all_validators(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'string',
            minLength: 5,
            maxLength: 100,
            pattern: '^[a-zA-Z]+$',
            enum: ['a', 'b', 'c'],
        );

        $code = $compiler->compile($schema, 'AllValidators');

        $this->assertStringContainsString('is_string($data)', $code);
        $this->assertStringContainsString("mb_strlen(\$data, 'UTF-8')", $code);
        $this->assertStringContainsString('preg_match', $code);
        $this->assertStringContainsString('in_array($data', $code);
    }

    #[Test]
    public function compile_schema_with_nested_schemas(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'age' => new Schema(type: 'integer'),
                'address' => new Schema(
                    type: 'object',
                    properties: [
                        'street' => new Schema(type: 'string'),
                        'city' => new Schema(type: 'string'),
                    ],
                ),
            ],
        );

        $code = $compiler->compile($schema, 'NestedSchema');

        $this->assertStringContainsString("is_array(\$data)", $code);
        $this->assertStringContainsString("\$data['name']", $code);
        $this->assertStringContainsString("\$data['age']", $code);
        $this->assertStringContainsString("\$data['address']", $code);
    }

    #[Test]
    public function compile_schema_with_refs(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'user' => new Schema(ref: '#/components/schemas/User'),
            ],
        );

        $document = new OpenApiDocument(
            openapi: '3.0.3',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'User' => new Schema(
                        type: 'object',
                        properties: [
                            'name' => new Schema(type: 'string'),
                        ],
                    ),
                ],
            ),
        );

        $code = $compiler->compileWithRefResolution($schema, 'RefSchema', $document);

        $this->assertStringContainsString('is_array($data)', $code);
    }

    #[Test]
    public function compile_schema_with_discriminator(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            discriminator: new Discriminator(propertyName: 'type'),
            properties: [
                'type' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $code = $compiler->compile($schema, 'DiscriminatorSchema');

        $this->assertStringContainsString('is_array($data)', $code);
    }

    #[Test]
    public function compile_schema_returns_compiled_validators(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string');

        $code = $compiler->compile($schema, 'CompiledValidator');

        $this->assertIsString($code);
        $this->assertNotEmpty($code);
    }

    #[Test]
    public function compile_schema_with_dependencies(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
            ],
            required: ['name'],
        );

        $code = $compiler->compile($schema, 'DependencySchema');

        $this->assertStringContainsString("array_key_exists('name'", $code);
    }

    #[Test]
    public function compile_schema_empty_schema(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema();

        $code = $compiler->compile($schema, 'EmptySchema');

        $this->assertStringContainsString('readonly class EmptySchema', $code);
        $this->assertStringContainsString('public function validate(mixed $data): void', $code);
    }

    #[Test]
    public function compile_schema_with_arrays(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'string'),
            minItems: 1,
            maxItems: 10,
            uniqueItems: true,
        );

        $code = $compiler->compile($schema, 'ArraySchema');

        $this->assertStringContainsString('is_array($data)', $code);
        $this->assertStringContainsString('count($data) < 1', $code);
        $this->assertStringContainsString('count($data) > 10', $code);
        $this->assertStringContainsString('json_encode', $code);
    }

    #[Test]
    public function compile_schema_with_objects(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'age' => new Schema(type: 'integer'),
            ],
            required: ['name'],
        );

        $code = $compiler->compile($schema, 'ObjectSchema');

        $this->assertStringContainsString('is_array($data)', $code);
        $this->assertStringContainsString("\$data['name']", $code);
        $this->assertStringContainsString("\$data['age']", $code);
        $this->assertStringContainsString("array_key_exists('name'", $code);
    }

    #[Test]
    public function compile_throws_exception_for_invalid_schema(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'user' => new Schema(ref: '#/invalid/ref'),
            ],
        );

        $document = new OpenApiDocument(
            openapi: '3.0.3',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(),
        );

        $this->expectException(RuntimeException::class);

        $compiler->compileWithRefResolution($schema, 'InvalidSchema', $document);
    }

    #[Test]
    public function compile_with_cache_hit(): void
    {
        $compiler = new ValidatorCompiler();
        $cache = $this->createMock(CompilationCacheInterface::class);
        $schema = new Schema(type: 'string');

        $cache
            ->expects($this->once())
            ->method('generateKey')
            ->willReturn('cache_key');

        $cache
            ->expects($this->once())
            ->method('get')
            ->with('cache_key')
            ->willReturn('cached_code');

        $code = $compiler->compileWithCache($schema, 'CachedValidator', $cache);

        $this->assertSame('cached_code', $code);
    }

    #[Test]
    public function compile_with_cache_miss(): void
    {
        $compiler = new ValidatorCompiler();
        $cache = $this->createMock(CompilationCacheInterface::class);
        $schema = new Schema(type: 'string');

        $cache
            ->expects($this->once())
            ->method('generateKey')
            ->willReturn('cache_key');

        $cache
            ->expects($this->once())
            ->method('get')
            ->with('cache_key')
            ->willReturn(null);

        $cache
            ->expects($this->once())
            ->method('set')
            ->with('cache_key', $this->anything());

        $code = $compiler->compileWithCache($schema, 'CachedValidator', $cache);

        $this->assertStringContainsString('is_string($data)', $code);
    }

    #[Test]
    public function compile_with_circular_ref_throws_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(ref: '#/components/schemas/Circular');

        $document = new OpenApiDocument(
            openapi: '3.0.3',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'Circular' => new Schema(ref: '#/components/schemas/Circular'),
                ],
            ),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular reference detected');

        $compiler->compileWithRefResolution($schema, 'CircularSchema', $document);
    }

    #[Test]
    public function compile_class_exists(): void
    {
        self::assertTrue(class_exists(ValidatorCompiler::class));
    }

    #[Test]
    public function compile_generates_exclusive_minimum_check(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'number',
            exclusiveMinimum: 10,
        );
        $code = $compiler->compile($schema, 'ExclusiveMinValidator');

        $this->assertStringContainsString('is_float($data)', $code);
    }

    #[Test]
    public function compile_generates_exclusive_maximum_check(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'number',
            exclusiveMaximum: 100,
        );
        $code = $compiler->compile($schema, 'ExclusiveMaxValidator');

        $this->assertStringContainsString('is_float($data)', $code);
    }

    #[Test]
    public function compile_generates_union_type_check(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: ['string', 'integer']);
        $code = $compiler->compile($schema, 'UnionTypeValidator');

        $this->assertStringContainsString('is_string($data)', $code);
        $this->assertStringContainsString('is_int($data)', $code);
    }

    #[Test]
    public function compile_without_cache(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string');

        $code = $compiler->compileWithCache($schema, 'NoCacheValidator');

        $this->assertStringContainsString('is_string($data)', $code);
    }

    #[Test]
    public function compile_with_nested_ref_resolution(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'user' => new Schema(
                    type: 'object',
                    properties: [
                        'profile' => new Schema(ref: '#/components/schemas/Profile'),
                    ],
                ),
            ],
        );

        $document = new OpenApiDocument(
            openapi: '3.0.3',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'Profile' => new Schema(
                        type: 'object',
                        properties: [
                            'name' => new Schema(type: 'string'),
                            'email' => new Schema(type: 'string'),
                        ],
                    ),
                ],
            ),
        );

        $code = $compiler->compileWithRefResolution($schema, 'NestedRefSchema', $document);

        $this->assertStringContainsString('is_array($data)', $code);
    }

    #[Test]
    public function compile_with_array_item_ref(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'array',
            items: new Schema(ref: '#/components/schemas/Item'),
        );

        $document = new OpenApiDocument(
            openapi: '3.0.3',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'Item' => new Schema(
                        type: 'object',
                        properties: [
                            'id' => new Schema(type: 'integer'),
                            'name' => new Schema(type: 'string'),
                        ],
                    ),
                ],
            ),
        );

        $code = $compiler->compileWithRefResolution($schema, 'ArrayItemRefSchema', $document);

        $this->assertStringContainsString('is_array($data)', $code);
    }

    #[Test]
    public function compile_with_ref_in_nested_property(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'parent' => new Schema(
                    type: 'object',
                    properties: [
                        'child' => new Schema(ref: '#/components/schemas/Child'),
                    ],
                ),
            ],
        );

        $document = new OpenApiDocument(
            openapi: '3.0.3',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'Child' => new Schema(
                        type: 'object',
                        properties: [
                            'name' => new Schema(type: 'string'),
                        ],
                    ),
                ],
            ),
        );

        $code = $compiler->compileWithRefResolution($schema, 'NestedRefPropertySchema', $document);

        $this->assertStringContainsString('is_array($data)', $code);
    }

    #[Test]
    public function compile_with_exclusive_min_and_max(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'number',
            minimum: 10,
            maximum: 100,
            exclusiveMinimum: 15,
            exclusiveMaximum: 95,
        );

        $code = $compiler->compile($schema, 'ExclusiveRangeValidator');

        $this->assertStringContainsString('$data < 10', $code);
        $this->assertStringContainsString('$data > 100', $code);
    }

    #[Test]
    public function compile_with_all_array_constraints(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'integer'),
            minItems: 1,
            maxItems: 10,
            uniqueItems: true,
        );

        $code = $compiler->compile($schema, 'AllArrayConstraintsValidator');

        $this->assertStringContainsString('count($data) < 1', $code);
        $this->assertStringContainsString('count($data) > 10', $code);
        $this->assertStringContainsString('json_encode', $code);
    }

    #[Test]
    public function compile_with_all_string_constraints(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'string',
            minLength: 5,
            maxLength: 100,
            pattern: '^[a-zA-Z0-9]+$',
        );

        $code = $compiler->compile($schema, 'AllStringConstraintsValidator');

        $this->assertStringContainsString("mb_strlen(\$data, 'UTF-8') < 5", $code);
        $this->assertStringContainsString("mb_strlen(\$data, 'UTF-8') > 100", $code);
        $this->assertStringContainsString('preg_match', $code);
    }

    #[Test]
    public function compile_with_all_number_constraints(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'number',
            minimum: 0,
            maximum: 1000,
            exclusiveMinimum: 10,
            exclusiveMaximum: 990,
        );

        $code = $compiler->compile($schema, 'AllNumberConstraintsValidator');

        $this->assertStringContainsString('$data < 0', $code);
        $this->assertStringContainsString('$data > 1000', $code);
        $this->assertStringContainsString('$data <= 10', $code);
        $this->assertStringContainsString('$data >= 990', $code);
    }

    #[Test]
    public function compile_with_multiple_required_properties(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'email' => new Schema(type: 'string'),
                'age' => new Schema(type: 'integer'),
            ],
            required: ['name', 'email'],
        );

        $code = $compiler->compile($schema, 'MultipleRequiredValidator');

        $this->assertStringContainsString("array_key_exists('name'", $code);
        $this->assertStringContainsString("array_key_exists('email'", $code);
    }

    #[Test]
    public function compile_with_all_types(): void
    {
        $compiler = new ValidatorCompiler();

        $stringSchema = new Schema(type: 'string');
        $numberSchema = new Schema(type: 'number');
        $integerSchema = new Schema(type: 'integer');
        $booleanSchema = new Schema(type: 'boolean');
        $arraySchema = new Schema(type: 'array', items: new Schema(type: 'string'));
        $objectSchema = new Schema(type: 'object');
        $nullSchema = new Schema(type: 'null');

        $this->assertStringContainsString('is_string($data)', $compiler->compile($stringSchema, 'StringType'));
        $this->assertStringContainsString('is_float($data)', $compiler->compile($numberSchema, 'NumberType'));
        $this->assertStringContainsString('is_int($data)', $compiler->compile($numberSchema, 'NumberType'));
        $this->assertStringContainsString('is_int($data)', $compiler->compile($integerSchema, 'IntegerType'));
        $this->assertStringContainsString('is_bool($data)', $compiler->compile($booleanSchema, 'BooleanType'));
        $this->assertStringContainsString('is_array($data)', $compiler->compile($arraySchema, 'ArrayType'));
        $this->assertStringContainsString('is_array($data)', $compiler->compile($objectSchema, 'ObjectType'));
        $this->assertStringContainsString('is_null($data)', $compiler->compile($nullSchema, 'NullType'));
    }

    #[Test]
    public function compile_with_mixed_type_union(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: ['string', 'number', 'boolean', 'null']);

        $code = $compiler->compile($schema, 'MixedTypeUnion');

        $this->assertStringContainsString('is_string($data)', $code);
        $this->assertStringContainsString('is_float($data)', $code);
        $this->assertStringContainsString('is_int($data)', $code);
        $this->assertStringContainsString('is_bool($data)', $code);
        $this->assertStringContainsString('is_null($data)', $code);
    }

    #[Test]
    public function compile_enum_check_uses_inline_values_not_undefined_variable(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string', enum: ['active', 'inactive']);
        $code = $compiler->compile($schema, 'EnumInlineValuesValidator');

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);

        $validator = new EnumInlineValuesValidator();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Value must be one of');

        $validator->validate('unknown');
    }

    #[Test]
    public function compile_generates_code_with_actual_newline_after_php_tag(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string');

        $code = $compiler->compile($schema, 'NewlineCheckValidator');

        $this->assertStringStartsWith("<?php\n", $code);
    }

    #[Test]
    public function compile_with_cache_returns_same_code_on_repeated_call(): void
    {
        $compiler = new ValidatorCompiler();
        $cache = $this->createMock(CompilationCacheInterface::class);
        $schema = new Schema(type: 'string');

        $callCount = 0;
        $cache
            ->method('generateKey')
            ->willReturnCallback(function () use (&$callCount): string {
                ++$callCount;

                return 'key_' . $callCount;
            });

        $cache
            ->method('get')
            ->willReturnOnConsecutiveCalls(null, 'compiled_code_from_cache');

        $cache
            ->expects($this->once())
            ->method('set');

        $firstCode = $compiler->compileWithCache($schema, 'RepeatedCallValidator', $cache);
        $this->assertStringContainsString('is_string($data)', $firstCode);

        $secondCode = $compiler->compileWithCache($schema, 'RepeatedCallValidator', $cache);
        $this->assertSame('compiled_code_from_cache', $secondCode);
    }

    #[Test]
    public function compile_with_ref_resolution_schema_not_found(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(ref: '#/components/schemas/Missing');

        $document = new OpenApiDocument(
            openapi: '3.0.3',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(schemas: []),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Schema not found: Missing');

        $compiler->compileWithRefResolution($schema, 'MissingSchema', $document);
    }

    #[Test]
    public function compile_with_ref_resolution_unsupported_ref_format(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(ref: '#/paths/some/path');

        $document = new OpenApiDocument(
            openapi: '3.0.3',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported $ref: #/paths/some/path');

        $compiler->compileWithRefResolution($schema, 'UnsupportedRef', $document);
    }

    #[Test]
    public function compile_with_cache_null_does_not_use_cache(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'integer');

        $code = $compiler->compileWithCache($schema, 'NullCacheValidator', null);

        $this->assertStringContainsString('is_int($data)', $code);
    }

    #[Test]
    public function compile_generates_multiple_of_check(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'number', multipleOf: 0.5);
        $code = $compiler->compile($schema, 'MultipleOfValidator');

        $this->assertStringContainsString('is_float($data)', $code);
        $this->assertStringContainsString('is_int($data)', $code);
    }

    #[Test]
    public function compile_with_ref_resolution_resolves_properties(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'address' => new Schema(ref: '#/components/schemas/Address'),
            ],
        );

        $document = new OpenApiDocument(
            openapi: '3.0.3',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'Address' => new Schema(
                        type: 'object',
                        properties: [
                            'street' => new Schema(type: 'string'),
                            'city' => new Schema(type: 'string'),
                        ],
                    ),
                ],
            ),
        );

        $code = $compiler->compileWithRefResolution($schema, 'ResolvedPropertySchema', $document);

        $this->assertStringContainsString('is_array($data)', $code);
        $this->assertStringContainsString("is_string", $code);
    }

    #[Test]
    public function compile_with_items_ref_resolution(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'array',
            items: new Schema(ref: '#/components/schemas/Tag'),
        );

        $document = new OpenApiDocument(
            openapi: '3.0.3',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
            components: new Components(
                schemas: [
                    'Tag' => new Schema(
                        type: 'string',
                    ),
                ],
            ),
        );

        $code = $compiler->compileWithRefResolution($schema, 'ItemsRefSchema', $document);

        $this->assertStringContainsString('is_array($data)', $code);
    }

    #[Test]
    public function compile_object_without_properties_generates_type_check(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'object');

        $code = $compiler->compile($schema, 'PlainObjectValidator');

        $this->assertStringContainsString('is_array($data)', $code);
    }

    #[Test]
    public function compile_generates_declare_strict_types(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string');

        $code = $compiler->compile($schema, 'StrictTypesValidator');

        $this->assertStringContainsString('declare(strict_types=1);', $code);
    }

    #[Test]
    public function compile_number_type_generates_is_float_or_is_int(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'number');

        $code = $compiler->compile($schema, 'NumberTypeValidator');

        $this->assertStringContainsString('is_float($data)', $code);
        $this->assertStringContainsString('is_int($data)', $code);
        $this->assertStringNotContainsString('is_number', $code);
    }

    #[Test]
    public function compile_number_type_validates_integer_value(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'number');

        $code = $compiler->compile($schema, 'NumberIntValidator');

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);

        $validator = new NumberIntValidator();

        $validator->validate(42);
        $validator->validate(3.14);
        $this->assertTrue(true);
    }

    #[Test]
    public function compile_number_type_rejects_string(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'number');

        $code = $compiler->compile($schema, 'NumberRejectStringValidator');

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);

        $validator = new NumberRejectStringValidator();

        $this->expectException(RuntimeException::class);
        $validator->validate('not a number');
    }

    #[Test]
    public function compile_const_check_generates_validation(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string', const: 'fixed', hasConst: true);

        $code = $compiler->compile($schema, 'ConstValidator');

        $this->assertStringContainsString("'fixed' !== \$data", $code);
        $this->assertStringContainsString('Value must be const', $code);
    }

    #[Test]
    public function compile_const_check_validates_correct_value(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string', const: 'hello', hasConst: true);

        $code = $compiler->compile($schema, 'ConstCorrectValidator');

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);

        $validator = new ConstCorrectValidator();
        $validator->validate('hello');

        $this->assertTrue(true);
    }

    #[Test]
    public function compile_const_check_rejects_wrong_value(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string', const: 'hello', hasConst: true);

        $code = $compiler->compile($schema, 'ConstWrongValidator');

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);

        $validator = new ConstWrongValidator();

        $this->expectException(RuntimeException::class);
        $validator->validate('world');
    }

    #[Test]
    public function compile_const_with_integer_value(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'integer', const: 42, hasConst: true);

        $code = $compiler->compile($schema, 'ConstIntValidator');

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);

        $validator = new ConstIntValidator();
        $validator->validate(42);

        $this->assertTrue(true);
    }

    #[Test]
    public function compile_multiple_of_check_uses_relative_epsilon_instead_of_fmod(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'number', multipleOf: 0.5);

        $code = $compiler->compile($schema, 'MultipleOfGeneratedValidator');

        $this->assertStringContainsString('$quotient = (float) $data', $code);
        $this->assertStringContainsString('$rounded = round($quotient)', $code);
        $this->assertStringContainsString('$epsilon = 1.0E-9 * max(1.0, abs($quotient))', $code);
        $this->assertStringNotContainsString('fmod((float) $data', $code);
        $this->assertStringContainsString('Value must be a multiple of 0.5', $code);
    }

    #[Test]
    public function compile_multiple_of_integer_uses_int_modulus_path(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'number', multipleOf: 2);

        $code = $compiler->compile($schema, 'MultipleOfIntegerValidator');

        $this->assertStringContainsString('if (is_int($data))', $code);
        $this->assertStringContainsString('0 !== ($data % 2)', $code);
        $this->assertStringContainsString('$quotient = (float) $data', $code);
        $this->assertStringNotContainsString('fmod((float) $data', $code);
    }

    #[Test]
    public function compile_multiple_of_validates_correct_value(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'number', multipleOf: 0.5);

        $code = $compiler->compile($schema, 'MultipleOfValidValidator');

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);

        $validator = new MultipleOfValidValidator();
        $validator->validate(1.0);
        $validator->validate(2.5);

        $this->assertTrue(true);
    }

    #[Test]
    public function compile_multiple_of_rejects_invalid_value(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'number', multipleOf: 3);

        $code = $compiler->compile($schema, 'MultipleOfInvalidValidator');

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);

        $validator = new MultipleOfInvalidValidator();

        $this->expectException(RuntimeException::class);
        $validator->validate(7);
    }

    #[Test]
    public function compile_additional_properties_false_generates_check(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'age' => new Schema(type: 'integer'),
            ],
            additionalProperties: false,
        );

        $code = $compiler->compile($schema, 'NoAdditionalPropsValidator');

        $this->assertStringContainsString('array_keys($data)', $code);
        $this->assertStringContainsString('Additional property not allowed', $code);
    }

    #[Test]
    public function compile_additional_properties_false_rejects_extra_property(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
            ],
            additionalProperties: false,
        );

        $code = $compiler->compile($schema, 'RejectExtraPropValidator');

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);

        $validator = new RejectExtraPropValidator();
        $validator->validate(['name' => 'John']);

        $this->expectException(RuntimeException::class);
        $validator->validate(['name' => 'John', 'extra' => 'field']);
    }

    #[Test]
    public function compile_additional_properties_true_allows_extra_properties(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
            ],
            additionalProperties: true,
        );

        $code = $compiler->compile($schema, 'AllowExtraPropsValidator');

        $this->assertStringNotContainsString('Additional property not allowed', $code);
    }

    #[Test]
    public function compile_additional_properties_nested_object_rejects_extra(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'address' => new Schema(
                    type: 'object',
                    properties: [
                        'city' => new Schema(type: 'string'),
                    ],
                    additionalProperties: false,
                ),
            ],
        );

        $code = $compiler->compile($schema, 'NestedRejectExtraValidator');

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);

        $validator = new NestedRejectExtraValidator();

        $validator->validate(['address' => ['city' => 'NYC']]);

        $this->expectException(RuntimeException::class);
        $validator->validate(['address' => ['city' => 'NYC', 'zip' => '10001']]);
    }

    #[Test]
    public function compile_schema_without_const_does_not_generate_check(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string');

        $code = $compiler->compile($schema, 'NoConstValidator');

        $this->assertStringNotContainsString('Value must be const', $code);
    }

    #[Test]
    public function compile_schema_without_multiple_of_does_not_generate_check(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'number');

        $code = $compiler->compile($schema, 'NoMultipleOfValidator');

        $this->assertStringNotContainsString('fmod', $code);
    }

    #[Test]
    public function compile_generated_code_is_valid_php(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'number',
            minimum: 0,
            maximum: 100,
            multipleOf: 0.5,
        );

        $code = $compiler->compile($schema, 'ValidPhpOutputValidator');

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);

        $this->assertTrue(class_exists('ValidPhpOutputValidator', false));
    }

    #[Test]
    public function compile_union_type_accepts_string_value(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: ['string', 'integer']);

        $code = $compiler->compile($schema, 'UnionAcceptsStringValidator');

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);

        $validator = new UnionAcceptsStringValidator();
        $validator->validate('hello');

        $this->assertTrue(true);
    }

    #[Test]
    public function compile_union_type_accepts_integer_value(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: ['string', 'integer']);

        $code = $compiler->compile($schema, 'UnionAcceptsIntegerValidator');

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);

        $validator = new UnionAcceptsIntegerValidator();
        $validator->validate(42);

        $this->assertTrue(true);
    }

    #[Test]
    public function compile_union_type_rejects_invalid_value(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: ['string', 'integer']);

        $code = $compiler->compile($schema, 'UnionRejectsFloatValidator');

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);

        $validator = new UnionRejectsFloatValidator();

        $this->expectException(RuntimeException::class);
        $validator->validate(3.14);
    }

    #[Test]
    public function compile_with_min_properties_throws_unsupported_keyword_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: ['name' => new Schema(type: 'string')],
            minProperties: 2,
        );

        $caught = null;
        try {
            $compiler->compile($schema, 'MinPropertiesValidator');
        } catch (UnsupportedKeywordException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertContains('minProperties', $caught->keywords);
    }

    #[Test]
    public function compile_object_without_min_properties_succeeds(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: ['name' => new Schema(type: 'string')],
        );

        $code = $compiler->compile($schema, 'NoMinPropertiesValidator');

        $this->assertStringContainsString('readonly class NoMinPropertiesValidator', $code);
        $this->assertStringNotContainsString('minProperties', $code);
    }

    #[Test]
    public function compile_with_max_properties_throws_unsupported_keyword_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: ['name' => new Schema(type: 'string')],
            maxProperties: 5,
        );

        $caught = null;
        try {
            $compiler->compile($schema, 'MaxPropertiesValidator');
        } catch (UnsupportedKeywordException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertContains('maxProperties', $caught->keywords);
    }

    #[Test]
    public function compile_object_without_max_properties_succeeds(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: ['name' => new Schema(type: 'string')],
        );

        $code = $compiler->compile($schema, 'NoMaxPropertiesValidator');

        $this->assertStringContainsString('readonly class NoMaxPropertiesValidator', $code);
        $this->assertStringNotContainsString('maxProperties', $code);
    }

    #[Test]
    public function compile_with_additional_properties_as_schema_throws_unsupported_keyword_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: ['name' => new Schema(type: 'string')],
            additionalProperties: new Schema(type: 'string'),
        );

        $caught = null;
        try {
            $compiler->compile($schema, 'AdditionalPropertiesSchemaValidator');
        } catch (UnsupportedKeywordException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertContains('additionalProperties', $caught->keywords);
    }

    #[Test]
    public function compile_with_additional_properties_false_succeeds(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: ['name' => new Schema(type: 'string')],
            additionalProperties: false,
        );

        $code = $compiler->compile($schema, 'AdditionalPropertiesFalseValidator');

        $this->assertStringContainsString('readonly class AdditionalPropertiesFalseValidator', $code);
        $this->assertStringContainsString('Additional property not allowed', $code);
    }

    #[Test]
    public function compile_with_additional_properties_true_succeeds(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: ['name' => new Schema(type: 'string')],
            additionalProperties: true,
        );

        $code = $compiler->compile($schema, 'AdditionalPropertiesTrueValidator');

        $this->assertStringContainsString('readonly class AdditionalPropertiesTrueValidator', $code);
        $this->assertStringNotContainsString('Additional property not allowed', $code);
    }

    #[Test]
    public function compile_with_min_and_max_properties_throws_exception_with_both_keywords(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: ['name' => new Schema(type: 'string')],
            minProperties: 2,
            maxProperties: 5,
        );

        $caught = null;
        try {
            $compiler->compile($schema, 'MinMaxPropertiesValidator');
        } catch (UnsupportedKeywordException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertContains('minProperties', $caught->keywords);
        $this->assertContains('maxProperties', $caught->keywords);
        $this->assertCount(2, $caught->keywords);
    }

    #[Test]
    public function compile_with_min_properties_and_additional_properties_schema_lists_all_keywords(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: ['name' => new Schema(type: 'string')],
            minProperties: 1,
            additionalProperties: new Schema(type: 'string'),
        );

        $caught = null;
        try {
            $compiler->compile($schema, 'CombinedUnsupportedValidator');
        } catch (UnsupportedKeywordException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertContains('minProperties', $caught->keywords);
        $this->assertContains('additionalProperties', $caught->keywords);
        $this->assertCount(2, $caught->keywords);
    }

    #[Test]
    public function compile_unsupported_keyword_exception_message_contains_keyword_name(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: ['name' => new Schema(type: 'string')],
            minProperties: 3,
        );

        $caught = null;
        try {
            $compiler->compile($schema, 'MessageCheckValidator');
        } catch (UnsupportedKeywordException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertStringContainsString('minProperties', $caught->getMessage());
        $this->assertStringContainsString('Unsupported keywords', $caught->getMessage());
    }

    #[Test]
    public function compile_nested_property_with_min_properties_throws_unsupported_keyword_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'address' => new Schema(
                    type: 'object',
                    properties: ['city' => new Schema(type: 'string')],
                    minProperties: 2,
                ),
            ],
        );

        $caught = null;
        try {
            $compiler->compile($schema, 'NestedMinPropertiesValidator');
        } catch (UnsupportedKeywordException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertContains('minProperties', $caught->keywords);
    }

    #[Test]
    public function compile_array_items_with_max_properties_throws_unsupported_keyword_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'object',
                properties: ['name' => new Schema(type: 'string')],
                maxProperties: 3,
            ),
        );

        $caught = null;
        try {
            $compiler->compile($schema, 'ArrayItemsMaxPropertiesValidator');
        } catch (UnsupportedKeywordException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertContains('maxProperties', $caught->keywords);
    }

    /**
     * CP-04 (fail-closed): `prefixItems` is in the unsupported-keyword set,
     * so `compile()` throws `UnsupportedKeywordException` instead of
     * silently producing a validator that ignores positional items.
     */
    #[Test]
    public function compile_schema_with_prefix_items_throws_unsupported_keyword_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'array',
            prefixItems: [
                new Schema(type: 'string'),
                new Schema(type: 'integer'),
            ],
            items: new Schema(type: 'string'),
            minItems: 2,
        );

        $caught = null;
        try {
            $compiler->compile($schema, 'PrefixItemsValidator');
        } catch (UnsupportedKeywordException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertContains('prefixItems', $caught->keywords);
    }

    /**
     * CP-04 (no silent positional checks): Because `prefixItems` is
     * rejected as unsupported, no positional `$data[N]` access can be
     * emitted. The exception is thrown before code generation runs.
     */
    #[Test]
    public function compile_schema_with_prefix_items_rejects_before_positional_checks(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'array',
            prefixItems: [
                new Schema(type: 'string'),
                new Schema(type: 'integer'),
                new Schema(type: 'boolean'),
            ],
            items: new Schema(type: 'string'),
        );

        $caught = null;
        try {
            $compiler->compile($schema, 'PrefixItemsNoPositionalValidator');
        } catch (UnsupportedKeywordException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertContains('prefixItems', $caught->keywords);
    }

    /**
     * CP-04 (items does not bypass prefixItems rejection): When both
     * `items` and `prefixItems` are present, the compiler rejects the
     * schema — the homogeneous `items` schema alone cannot substitute for
     * positional validation, so compilation never reaches `items` handling.
     */
    #[Test]
    public function compile_schema_with_prefix_items_and_items_throws_unsupported_keyword_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'array',
            prefixItems: [
                new Schema(type: 'string'),
                new Schema(type: 'integer'),
            ],
            items: new Schema(type: 'string'),
        );

        $caught = null;
        try {
            $compiler->compile($schema, 'PrefixItemsStillValidatesItems');
        } catch (UnsupportedKeywordException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertContains('prefixItems', $caught->keywords);
    }

    /**
     * CP-04 (prefixItems alone, without items): A schema with
     * `prefixItems` and no `items` previously compiled to an empty
     * `validate()` body that accepted any value. It is now rejected with
     * `UnsupportedKeywordException` (fail-closed).
     */
    #[Test]
    public function compile_schema_with_prefix_items_without_items_throws_unsupported_keyword_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'array',
            prefixItems: [
                new Schema(type: 'string'),
                new Schema(type: 'integer'),
            ],
        );

        $caught = null;
        try {
            $compiler->compile($schema, 'PrefixItemsOnlyValidator');
        } catch (UnsupportedKeywordException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertContains('prefixItems', $caught->keywords);
    }

    /**
     * CP-04 (detection): `prefixItems` IS in the unsupported-keyword set,
     * so the compiler reports it in `UnsupportedKeywordException::keywords`.
     */
    #[Test]
    public function compile_schema_with_prefix_items_is_in_unsupported_keywords(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'array',
            prefixItems: [new Schema(type: 'string')],
            items: new Schema(type: 'string'),
        );

        $caught = null;
        try {
            $compiler->compile($schema, 'PrefixItemsNotUnsupported');
        } catch (UnsupportedKeywordException $e) {
            $caught = $e;
        }

        $this->assertNotNull($caught);
        $this->assertSame(['prefixItems'], $caught->keywords);
    }

    /**
     * CP-05: A schema nested more than 10 levels deep must compile without
     * a stack overflow and produce a working validator.
     *
     * We generate a 12-level schema (object -> nested object -> ...) and
     * verify the compile step. Runtime execution is covered by the
     * dedicated tests below.
     */
    #[Test]
    public function compile_deeply_nested_schema_over_ten_levels(): void
    {
        $compiler = new ValidatorCompiler();

        $depth = 12;
        $schema = $this->buildNestedSchema($depth);

        $code = $compiler->compile($schema, 'DeepNestValidator');

        $this->assertStringContainsString('readonly class DeepNestValidator', $code);
        // The 12-level schema must produce deeply nested $data['nested']
        // references in the compiled code (at least one per level).
        $this->assertGreaterThan(
            $depth,
            substr_count($code, "['nested']"),
        );
    }

    /**
     * CP-05 (positive execution): The compiled validator accepts a valid
     * deeply-nested payload without stack overflow.
     */
    #[Test]
    public function compile_deeply_nested_schema_validates_valid_payload(): void
    {
        $compiler = new ValidatorCompiler();
        $depth = 12;
        $schema = $this->buildNestedSchema($depth);

        $code = $compiler->compile($schema, 'DeepNestValidValidator');

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);

        $validator = new DeepNestValidValidator();
        $payload = $this->buildNestedPayload($depth, 42);

        $validator->validate($payload);

        self::assertTrue(class_exists('DeepNestValidValidator', false));
    }

    /**
     * CP-05 (negative execution): The compiled validator rejects a payload
     * with a type error at the deepest level.
     */
    #[Test]
    public function compile_deeply_nested_schema_rejects_invalid_payload_at_depth(): void
    {
        $compiler = new ValidatorCompiler();
        $depth = 12;
        $schema = $this->buildNestedSchema($depth);

        $code = $compiler->compile($schema, 'DeepNestInvalidValidator');

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);

        $validator = new DeepNestInvalidValidator();

        // Wrong leaf type: string instead of integer.
        $payload = $this->buildNestedPayload($depth, 'not-an-integer');

        $this->expectException(RuntimeException::class);
        $validator->validate($payload);
    }

    /**
     * CP-05 (boundary): Exactly 10 levels must also work (boundary case).
     */
    #[Test]
    public function compile_exactly_ten_levels_nested_schema(): void
    {
        $compiler = new ValidatorCompiler();
        $depth = 10;
        $schema = $this->buildNestedSchema($depth);

        $code = $compiler->compile($schema, 'TenLevelsValidator');

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);

        $validator = new TenLevelsValidator();
        $validator->validate($this->buildNestedPayload($depth, 1));

        self::assertTrue(class_exists('TenLevelsValidator', false));
    }

    /**
     * CP-05 (stress): 20 levels must also work to verify the compiler has
     * no practical nesting limit.
     */
    #[Test]
    public function compile_twenty_levels_nested_schema(): void
    {
        $compiler = new ValidatorCompiler();
        $depth = 20;
        $schema = $this->buildNestedSchema($depth);

        $code = $compiler->compile($schema, 'TwentyLevelsValidator');

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));
        eval($evalCode);

        $validator = new TwentyLevelsValidator();
        $validator->validate($this->buildNestedPayload($depth, 7));

        self::assertTrue(class_exists('TwentyLevelsValidator', false));
    }

    /**
     * EI-026 (Critical, security): property names come from untrusted JSON
     * strings. A name containing an apostrophe (`it's`) previously produced
     * `$data['it's']` in the generated code — a PHP syntax error and a
     * potential code-injection vector. The compiler must now emit a valid
     * single-quoted literal built via `var_export`, so the generated source
     * parses cleanly.
     */
    #[Test]
    public function compile_with_apostrophe_in_property_name_generates_parseable_php(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: ["it's" => new Schema(type: 'string')],
        );

        $code = $compiler->compile($schema, 'ApostrophePropertyValidator');

        token_get_all($code, TOKEN_PARSE);

        $this->assertStringContainsString('$data[\'it\\\'s\']', $code);
        $this->assertStringNotContainsString('$data[\'it\'s\']', $code);
    }

    /**
     * EI-026 (Critical, security): the required-property check used to
     * interpolate the property name into both the `array_key_exists` key
     * argument and the exception message. With an apostrophe in the name
     * this broke both the literal key and the message string. After the
     * fix the key uses `var_export` and the message uses `addslashes`, so
     * the generated source parses cleanly.
     */
    #[Test]
    public function compile_with_apostrophe_in_required_property_generates_parseable_php(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: ["it's" => new Schema(type: 'string')],
            required: ["it's"],
        );

        $code = $compiler->compile($schema, 'ApostropheRequiredValidator');

        token_get_all($code, TOKEN_PARSE);

        $this->assertStringContainsString('array_key_exists(\'it\\\'s\'', $code);
        $this->assertStringContainsString('Required property missing: it\\\'s', $code);
    }

    /**
     * EI-026 (Critical, security): a deeply nested object with an apostrophe
     * in a property name must still produce parseable PHP at every nesting
     * level, because each level rebuilds the property access via
     * `var_export`.
     */
    #[Test]
    public function compile_with_apostrophe_in_nested_property_name_generates_parseable_php(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                "it's" => new Schema(
                    type: 'object',
                    properties: ["it's" => new Schema(type: 'string')],
                ),
            ],
        );

        $code = $compiler->compile($schema, 'NestedApostropheValidator');

        token_get_all($code, TOKEN_PARSE);

        $this->assertStringContainsString('$data[\'it\\\'s\'][\'it\\\'s\']', $code);
    }

    /**
     * EI-027 (Major, security): a namespaced class name (`My\Validator`)
     * previously produced `readonly class My\Validator` — a syntax error.
     * The compiler must now split the name, emit a `namespace My;` block,
     * and declare `readonly class Validator` using the short name.
     */
    #[Test]
    public function compile_with_namespaced_class_name_generates_namespace_block(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string');

        $code = $compiler->compile($schema, 'My\\Validator');

        token_get_all($code, TOKEN_PARSE);
        $this->assertStringContainsString('namespace My;', $code);
        $this->assertStringContainsString('readonly class Validator', $code);
        $this->assertStringNotContainsString('readonly class My\\Validator', $code);
    }

    /**
     * EI-027: a multi-segment namespace must be emitted verbatim between
     * the `namespace` keyword and the `readonly class` declaration.
     */
    #[Test]
    public function compile_with_deeply_namespaced_class_name_generates_full_namespace_block(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string');

        $code = $compiler->compile($schema, 'App\\Dto\\UserValidator');

        token_get_all($code, TOKEN_PARSE);
        $this->assertStringContainsString('namespace App\\Dto;', $code);
        $this->assertStringContainsString('readonly class UserValidator', $code);
    }

    /**
     * EI-027: a class name starting with a digit is not a valid PHP
     * identifier and must be rejected before any code is generated.
     */
    #[Test]
    public function compile_with_class_name_starting_with_digit_throws_invalid_argument_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid class name part: "123Bad"');

        $compiler->compile($schema, '123Bad');
    }

    /**
     * EI-027: an empty class name is not a valid PHP identifier (each
     * namespace segment must match the identifier regex).
     */
    #[Test]
    public function compile_with_empty_class_name_throws_invalid_argument_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string');

        $this->expectException(InvalidArgumentException::class);

        $compiler->compile($schema, '');
    }

    /**
     * EI-027: a single namespace segment that is not a valid identifier
     * must be rejected, even when other segments are valid.
     */
    #[Test]
    public function compile_with_namespace_segment_starting_with_digit_throws_invalid_argument_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid class name part: "1Bad"');

        $compiler->compile($schema, 'My\\1Bad\\Validator');
    }

    /**
     * EI-027: a leading backslash produces an empty first segment after
     * `explode('\\', ...)`, which must be rejected — the compiler expects
     * the canonical form without a leading FQN separator.
     */
    #[Test]
    public function compile_with_leading_backslash_in_class_name_throws_invalid_argument_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string');

        $this->expectException(InvalidArgumentException::class);

        $compiler->compile($schema, '\\Validator');
    }

    /**
     * EI-027: a class name containing a character outside the identifier
     * grammar (a hyphen) must be rejected.
     */
    #[Test]
    public function compile_with_hyphen_in_class_name_throws_invalid_argument_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid class name part: "Bad-Name"');

        $compiler->compile($schema, 'Bad-Name');
    }

    /**
     * EI-027 (positive): a class name containing a leading underscore or
     * digits after the first character is a legal PHP identifier and must
     * pass validation without an exception.
     */
    #[Test]
    public function compile_with_valid_underscore_class_name_succeeds(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string');

        $code = $compiler->compile($schema, 'Valid_Name123');

        token_get_all($code, TOKEN_PARSE);
        $this->assertStringContainsString('readonly class Valid_Name123', $code);
    }

    /**
     * EI-027 (positive): a single underscore is a legal PHP identifier
     * (used by some libraries as a placeholder class name).
     */
    #[Test]
    public function compile_with_single_underscore_class_name_succeeds(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string');

        $code = $compiler->compile($schema, '_');

        token_get_all($code, TOKEN_PARSE);
        $this->assertStringContainsString('readonly class _', $code);
    }

    /**
     * EI-027: class-name validation must run before ref resolution, so a
     * caller cannot smuggle an invalid name through `compileWithRefResolution`.
     */
    #[Test]
    public function compile_with_ref_resolution_rejects_invalid_class_name(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string');
        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test', version: '1.0.0'),
        );

        $this->expectException(InvalidArgumentException::class);

        $compiler->compileWithRefResolution($schema, '0Invalid', $document);
    }

    /**
     * EI-027: class-name validation must run before cache lookup, so an
     * invalid name cannot poison the cache key path.
     */
    #[Test]
    public function compile_with_cache_rejects_invalid_class_name(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string');

        $this->expectException(InvalidArgumentException::class);

        $compiler->compileWithCache($schema, '0Invalid');
    }

    /**
     * Build a schema nested \$depth levels deep. The outermost is an object
     * with a single `nested` property which drills down \$depth-1 levels
     * to a final `value: integer`.
     */
    private function buildNestedSchema(int $depth): Schema
    {
        $schema = new Schema(type: 'integer');
        for ($i = 1; $i < $depth; $i++) {
            $schema = new Schema(
                type: 'object',
                properties: ['nested' => $schema],
            );
        }

        return $schema;
    }

    /**
     * Build a payload matching {@see buildNestedSchema()} with \$depth levels
     * and the given leaf value.
     *
     * For depth=1 the leaf is returned directly; for depth>=2 the leaf is
     * wrapped in depth-1 levels of `['nested' => ...]`.
     *
     * @return array<string, mixed>|int
     */
    private function buildNestedPayload(int $depth, mixed $leafValue): array|int
    {
        $payload = $leafValue;
        for ($i = 1; $i < $depth; $i++) {
            $payload = ['nested' => $payload];
        }

        return $payload;
    }
}
