<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler;

use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NestedObjectValidationTest extends TestCase
{
    #[Test]
    public function compiled_validator_handles_nested_objects(): void
    {
        $addressSchema = new Schema(
            type: 'object',
            properties: [
                'street' => new Schema(type: 'string'),
                'city' => new Schema(type: 'string'),
            ],
            required: ['street'],
        );

        $userSchema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'address' => $addressSchema,
            ],
        );

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($userSchema, 'UserValidator');

        self::assertStringContainsString('function validate(mixed $data): void', $code);
        self::assertStringContainsString('street', $code);
        self::assertStringContainsString('city', $code);
    }

    #[Test]
    public function compiled_validator_validates_required_properties(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'email' => new Schema(type: 'string'),
            ],
            required: ['name', 'email'],
        );

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($schema, 'RequiredValidator');

        self::assertStringContainsString('Required property missing', $code);
        self::assertStringContainsString('array_key_exists', $code);
    }

    #[Test]
    public function compiled_validator_handles_deeply_nested_objects(): void
    {
        $inner = new Schema(
            type: 'object',
            properties: [
                'value' => new Schema(type: 'string'),
            ],
        );

        $middle = new Schema(
            type: 'object',
            properties: [
                'inner' => $inner,
            ],
        );

        $outer = new Schema(
            type: 'object',
            properties: [
                'middle' => $middle,
            ],
        );

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($outer, 'DeepNestingValidator');

        self::assertStringContainsString("['middle']", $code);
        self::assertStringContainsString("['inner']", $code);
        self::assertStringContainsString("['value']", $code);
    }

    #[Test]
    public function compiled_validator_validates_property_types(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'age' => new Schema(type: 'integer'),
                'active' => new Schema(type: 'boolean'),
            ],
        );

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($schema, 'PropertyTypeValidator');

        self::assertStringContainsString('is_int($data[\'age\'])', $code);
        self::assertStringContainsString('is_bool($data[\'active\'])', $code);
    }

    #[Test]
    public function compiled_validator_allows_optional_properties(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'required' => new Schema(type: 'string'),
                'optional' => new Schema(type: 'string'),
            ],
            required: ['required'],
        );

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($schema, 'OptionalPropertyValidator');

        self::assertStringContainsString("if (isset(\$data['optional'])", $code);
    }

    #[Test]
    public function compiled_validator_checks_object_type(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'field' => new Schema(type: 'string'),
            ],
        );

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($schema, 'ObjectTypeValidator');

        self::assertStringContainsString('is_array($data)', $code);
        self::assertStringContainsString("['field']", $code);
    }
}
