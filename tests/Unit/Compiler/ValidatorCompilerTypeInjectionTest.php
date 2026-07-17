<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler;

use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Throwable;

use function bin2hex;
use function count;
use function in_array;
use function is_array;
use function random_bytes;
use function sprintf;
use function str_replace;
use function strtolower;
use function substr;
use function token_get_all;

use const TOKEN_PARSE;
use const T_STRING;

final class ValidatorCompilerTypeInjectionTest extends TestCase
{
    private const string MALICIOUS_TYPE = "string']; system('echo INJECTED'); /*";

    private const array DANGEROUS_FUNCTIONS = [
        'system',
        'exec',
        'shell_exec',
        'passthru',
        'proc_open',
        'popen',
        'pcntl_exec',
    ];

    #[Test]
    public function generates_safe_code_for_malicious_type_value(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: self::MALICIOUS_TYPE);

        $code = $compiler->compile($schema, 'EvilStaticValidator');

        self::assertStringNotContainsString("system('", $code);
        self::assertStringNotContainsString("'; system(", $code);
        self::assertStringNotContainsString("echo INJECTED');", $code);

        self::assertStringContainsString(
            "'string\\']; system(\\'echo INJECTED\\'); /*'",
            $code,
        );

        $this->assertNoDangerousFunctionCall($code);

        token_get_all($code, TOKEN_PARSE);
    }

    #[Test]
    public function compiles_and_runs_validator_without_rce(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: self::MALICIOUS_TYPE);

        $className = 'EvilRuntimeValidator_' . bin2hex(random_bytes(8));

        $code = $compiler->compile($schema, $className);

        token_get_all($code, TOKEN_PARSE);
        $this->assertNoDangerousFunctionCall($code);

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));

        /** @var string $evalCode */
        eval($evalCode);

        $reflection = new ReflectionClass($className);
        $validator = $reflection->newInstance();
        $validateMethod = $reflection->getMethod('validate');

        try {
            $validateMethod->invoke($validator, 'hello');
        } catch (Throwable $e) {
            self::fail(sprintf(
                'Generated validator threw %s when validating a benign value: %s',
                $e::class,
                $e->getMessage(),
            ));
        }

        self::assertTrue(true);
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

    private function assertNoDangerousFunctionCall(string $code): void
    {
        $tokens = token_get_all($code);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (false === is_array($token)) {
                continue;
            }

            if (T_STRING !== $token[0]) {
                continue;
            }

            $identifier = strtolower($token[1]);

            if (false === in_array($identifier, self::DANGEROUS_FUNCTIONS, true)) {
                continue;
            }

            $next = $tokens[$i + 1] ?? null;

            if ('(' === $next) {
                self::fail(sprintf(
                    'Generated code contains a dangerous function call: %s()',
                    $token[1],
                ));
            }
        }

        self::assertTrue(true);
    }
}
