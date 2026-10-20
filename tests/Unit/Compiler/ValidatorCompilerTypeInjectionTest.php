<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler;

use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\Model\Schema;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function token_get_all;

use const TOKEN_PARSE;

final class ValidatorCompilerTypeInjectionTest extends TestCase
{
    private const string MALICIOUS_TYPE = "string']; system('echo INJECTED'); /*";

    /**
     * A `type` value outside the JSON-Schema set must fail compilation with
     * an InvalidArgumentException. Previously the compiler silently emitted
     * an `is_string` check and embedded the untrusted string into the
     * generated source (escaped, but present); fail-closed removes that
     * surface entirely: no code is produced for an unknown type.
     */
    #[Test]
    public function malicious_type_value_is_rejected_at_compile_time(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: self::MALICIOUS_TYPE);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported scalar type');

        $compiler->compile($schema, 'EvilStaticValidator');
    }

    #[Test]
    public function malicious_type_in_union_is_rejected_at_compile_time(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: ['string', self::MALICIOUS_TYPE]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported scalar type');

        $compiler->compile($schema, 'EvilUnionValidator');
    }

    #[Test]
    public function skips_null_elements_in_type_array_without_crash(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: ['string', null]);

        $code = $compiler->compile($schema, 'NullElementFilterValidator');

        token_get_all($code, TOKEN_PARSE);

        self::assertStringContainsString('is_string($data)', $code);
    }

    #[Test]
    public function skips_null_elements_in_property_type_array_without_crash(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: ['name' => new Schema(type: ['string', null])],
        );

        $code = $compiler->compile($schema, 'NullElementFilterPropertyValidator');

        token_get_all($code, TOKEN_PARSE);

        self::assertStringContainsString('is_string(', $code);
    }
}
