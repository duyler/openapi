<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Compiler;

use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

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
    public function compile_class_exists(): void
    {
        self::assertTrue(class_exists(ValidatorCompiler::class));
    }
}
