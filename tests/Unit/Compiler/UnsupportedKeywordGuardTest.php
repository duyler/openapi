<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler;

use Duyler\OpenApi\Compiler\Exception\UnsupportedKeywordException;
use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UnsupportedKeywordGuardTest extends TestCase
{
    #[Test]
    public function compile_with_all_of_throws_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            allOf: [new Schema(type: 'string')],
        );

        $this->expectException(UnsupportedKeywordException::class);

        $compiler->compile($schema, 'AllOfSchema');
    }

    #[Test]
    public function compile_with_any_of_throws_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            anyOf: [new Schema(type: 'string')],
        );

        $this->expectException(UnsupportedKeywordException::class);

        $compiler->compile($schema, 'AnyOfSchema');
    }

    #[Test]
    public function compile_with_one_of_throws_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            oneOf: [new Schema(type: 'string')],
        );

        $this->expectException(UnsupportedKeywordException::class);

        $compiler->compile($schema, 'OneOfSchema');
    }

    #[Test]
    public function compile_with_not_throws_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            not: new Schema(type: 'string'),
        );

        $this->expectException(UnsupportedKeywordException::class);

        $compiler->compile($schema, 'NotSchema');
    }

    #[Test]
    public function compile_with_if_then_else_throws_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            if: new Schema(type: 'object'),
            then: new Schema(type: 'object'),
            else: new Schema(type: 'object'),
        );

        $this->expectException(UnsupportedKeywordException::class);

        $compiler->compile($schema, 'IfThenElseSchema');
    }

    #[Test]
    public function compile_with_pattern_properties_throws_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            patternProperties: ['^S_' => new Schema(type: 'string')],
        );

        $this->expectException(UnsupportedKeywordException::class);

        $compiler->compile($schema, 'PatternPropertiesSchema');
    }

    #[Test]
    public function compile_simple_schema_succeeds(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'age' => new Schema(type: 'integer'),
            ],
        );

        $code = $compiler->compile($schema, 'SimpleSchema');

        $this->assertStringContainsString('readonly class SimpleSchema', $code);
    }

    #[Test]
    public function unsupported_keyword_exception_contains_keyword_names(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            allOf: [new Schema(type: 'string')],
            anyOf: [new Schema(type: 'integer')],
        );

        try {
            $compiler->compile($schema, 'MultipleKeywords');
            $this->fail('Expected UnsupportedKeywordException');
        } catch (UnsupportedKeywordException $e) {
            $this->assertContains('allOf', $e->keywords);
            $this->assertContains('anyOf', $e->keywords);
            $this->assertStringContainsString('allOf', $e->getMessage());
            $this->assertStringContainsString('anyOf', $e->getMessage());
        }
    }

    #[Test]
    public function compile_with_unsupported_keyword_in_nested_property_throws_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'nested' => new Schema(
                    type: 'object',
                    allOf: [new Schema(type: 'string')],
                ),
            ],
        );

        $this->expectException(UnsupportedKeywordException::class);

        $compiler->compile($schema, 'NestedUnsupportedSchema');
    }

    #[Test]
    public function compile_with_unsupported_keyword_in_items_throws_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'object',
                oneOf: [new Schema(type: 'string')],
            ),
        );

        $this->expectException(UnsupportedKeywordException::class);

        $compiler->compile($schema, 'ItemsUnsupportedSchema');
    }

    #[Test]
    public function compile_with_only_if_throws_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            if: new Schema(type: 'object'),
        );

        $this->expectException(UnsupportedKeywordException::class);

        $compiler->compile($schema, 'IfOnlySchema');
    }

    #[Test]
    public function unsupported_keyword_exception_message_is_informative(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            not: new Schema(type: 'string'),
        );

        try {
            $compiler->compile($schema, 'NotMessageSchema');
            $this->fail('Expected UnsupportedKeywordException');
        } catch (UnsupportedKeywordException $e) {
            $this->assertStringContainsString('not', $e->getMessage());
            $this->assertStringContainsString('does not support', $e->getMessage());
        }
    }
}
