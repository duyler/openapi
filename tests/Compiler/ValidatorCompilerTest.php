<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Compiler;

use Duyler\OpenApi\Compiler\CompilationCache;
use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

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

        $this->assertStringContainsString('strlen($data)', $code);
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
        $this->assertStringContainsString('strlen($data)', $code);
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
        $this->assertStringContainsString('array_unique', $code);
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
    public function compile_schema_with_format_validators(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'string',
            format: 'email',
        );

        $code = $compiler->compile($schema, 'FormatSchema');

        $this->assertStringContainsString('is_string($data)', $code);
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
        $cache = $this->createMock(CompilationCache::class);
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
        $cache = $this->createMock(CompilationCache::class);
        $schema = new Schema(type: 'string');

        $cache
            ->expects($this->exactly(2))
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
        $this->assertStringContainsString('array_unique', $code);
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

        $this->assertStringContainsString('strlen($data) < 5', $code);
        $this->assertStringContainsString('strlen($data) > 100', $code);
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
        $this->assertStringContainsString('is_bool($data)', $code);
        $this->assertStringContainsString('is_null($data)', $code);
    }
}
