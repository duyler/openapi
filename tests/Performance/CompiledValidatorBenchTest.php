<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Performance;

use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CompiledValidatorBenchTest extends TestCase
{
    #[Test]
    public function compilation_overhead_is_acceptable(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'field1' => new Schema(type: 'string'),
                'field2' => new Schema(type: 'integer'),
            ],
        );

        $compiler = new ValidatorCompiler();

        $start = microtime(true);
        for ($i = 0; $i < 100; $i++) {
            $compiler->compile($schema, 'BenchValidator');
        }
        $compilationTime = microtime(true) - $start;

        $this->assertLessThan(1.0, $compilationTime, '100 compilations should take less than 1 second');

        printf("\n100 compilations: %.4fs (%.4fs per compilation)\n", $compilationTime, $compilationTime / 100);
    }

    #[Test]
    public function compiled_code_is_generated_correctly(): void
    {
        $schema = new Schema(
            type: 'string',
            minLength: 1,
            maxLength: 100,
        );

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($schema, 'SimpleValidator');

        $this->assertStringContainsString('readonly class SimpleValidator', $code);
        $this->assertStringContainsString('public function validate(mixed $data): void', $code);
        $this->assertStringContainsString('is_string($data)', $code);
    }

    #[Test]
    public function compilation_generates_correct_code_for_complex_schemas(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string', minLength: 1),
                'age' => new Schema(type: 'integer', minimum: 0),
                'tags' => new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                ),
            ],
            required: ['name'],
        );

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($schema, 'ComplexValidator');

        $this->assertStringContainsString('readonly class ComplexValidator', $code);
        $this->assertStringContainsString("['name']", $code);
        $this->assertStringContainsString("['age']", $code);
        $this->assertStringContainsString("['tags']", $code);
        $this->assertStringContainsString('array_key_exists', $code);
    }
}
