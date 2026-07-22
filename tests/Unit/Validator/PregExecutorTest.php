<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Validator\Exception\PregRuntimeException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

use function fclose;
use function fwrite;
use function ini_get;
use function ini_set;
use function proc_close;
use function proc_open;
use function stream_get_contents;
use function stream_get_meta_data;
use function str_repeat;
use function tmpfile;

use function sprintf;

use function dirname;

use const PREG_BACKTRACK_LIMIT_ERROR;
use const PREG_INTERNAL_ERROR;

#[CoversClass(PregExecutor::class)]
class PregExecutorTest extends TestCase
{
    private string $originalBacktrackLimit;

    private string $originalRecursionLimit;

    #[Override]
    protected function setUp(): void
    {
        $this->originalBacktrackLimit = ini_get('pcre.backtrack_limit') ?: '1000000';
        $this->originalRecursionLimit = ini_get('pcre.recursion_limit') ?: '100000';
    }

    #[Override]
    protected function tearDown(): void
    {
        ini_set('pcre.backtrack_limit', $this->originalBacktrackLimit);
        ini_set('pcre.recursion_limit', $this->originalRecursionLimit);
    }

    #[Test]
    public function default_max_backtracks_constant_is_underscored_int(): void
    {
        self::assertSame(10_000, PregExecutor::DEFAULT_MAX_BACKTRACKS);
    }

    #[Test]
    public function default_max_recursion_constant_is_512(): void
    {
        self::assertSame(512, PregExecutor::DEFAULT_MAX_RECURSION);
    }

    #[Test]
    public function default_max_recursion_via_reflection_is_512(): void
    {
        $executor = new PregExecutor();

        $property = new ReflectionProperty(PregExecutor::class, 'maxRecursionLimit');

        self::assertSame(512, $property->getValue($executor));
    }

    #[Test]
    public function match_returns_int_one_for_matching_pattern(): void
    {
        $executor = new PregExecutor();

        $result = $executor->match('/^hello$/', 'hello');

        self::assertSame(1, $result);
    }

    #[Test]
    public function match_returns_zero_for_non_matching_pattern(): void
    {
        $executor = new PregExecutor();

        $result = $executor->match('/^hello$/', 'world');

        self::assertSame(0, $result);
    }

    #[Test]
    public function match_throws_preg_runtime_exception_on_compile_error(): void
    {
        $executor = new PregExecutor();

        $this->expectException(PregRuntimeException::class);
        $this->expectExceptionMessage('PCRE error ');

        $executor->match('/[unclosed/', 'anything');
    }

    #[Test]
    public function match_throws_on_compile_error_with_internal_error_code(): void
    {
        $executor = new PregExecutor();

        try {
            $executor->match('/[unclosed/', 'anything');
            self::fail('Expected PregRuntimeException on compile error');
        } catch (PregRuntimeException $exception) {
            self::assertSame(PREG_INTERNAL_ERROR, $exception->error);
        }
    }

    #[Test]
    public function match_populates_named_groups_by_reference(): void
    {
        $executor = new PregExecutor();
        $matches = null;

        $executor->match('/^(?<year>\d{4})$/', '2026', $matches);

        self::assertSame('2026', $matches['year'] ?? null);
    }

    #[Test]
    public function match_all_returns_int_count(): void
    {
        $executor = new PregExecutor();

        $result = $executor->matchAll('/\d/', 'a1b2c3');

        self::assertSame(3, $result);
    }

    #[Test]
    public function match_all_throws_on_compile_error(): void
    {
        $executor = new PregExecutor();

        $this->expectException(PregRuntimeException::class);

        $executor->matchAll('/[unclosed/', 'abc');
    }

    #[Test]
    public function restores_previous_backtrack_limit_on_success(): void
    {
        ini_set('pcre.backtrack_limit', '999999');
        $executor = new PregExecutor(maxBacktracks: 100);

        $executor->match('/^abc$/', 'abc');

        self::assertSame('999999', ini_get('pcre.backtrack_limit'));
    }

    #[Test]
    public function restores_previous_recursion_limit_on_success(): void
    {
        ini_set('pcre.recursion_limit', '999999');
        $executor = new PregExecutor(maxRecursionLimit: 100);

        $executor->match('/^abc$/', 'abc');

        self::assertSame('999999', ini_get('pcre.recursion_limit'));
    }

    #[Test]
    public function restores_previous_recursion_limit_on_compile_failure(): void
    {
        ini_set('pcre.recursion_limit', '888888');
        $executor = new PregExecutor(maxRecursionLimit: 100);

        try {
            $executor->match('/[unclosed/', 'abc');
            self::fail('Expected PregRuntimeException on compile failure');
        } catch (PregRuntimeException) {
        }

        self::assertSame('888888', ini_get('pcre.recursion_limit'));
    }

    #[Test]
    public function restores_previous_recursion_limit_on_exception(): void
    {
        ini_set('pcre.recursion_limit', '777777');
        $executor = new PregExecutor(maxRecursionLimit: 100);

        try {
            $executor->match('/^trigger$/', $this->throwDuringCall());
        } catch (RuntimeException) {
        }

        self::assertSame('777777', ini_get('pcre.recursion_limit'));
    }

    #[Test]
    public function match_all_restores_recursion_limit_on_success(): void
    {
        ini_set('pcre.recursion_limit', '666666');
        $executor = new PregExecutor(maxRecursionLimit: 100);

        $executor->matchAll('/\d/', 'a1b2');

        self::assertSame('666666', ini_get('pcre.recursion_limit'));
    }

    #[Test]
    public function match_all_restores_recursion_limit_on_compile_failure(): void
    {
        ini_set('pcre.recursion_limit', '555555');
        $executor = new PregExecutor(maxRecursionLimit: 100);

        try {
            $executor->matchAll('/[unclosed/', 'abc');
            self::fail('Expected PregRuntimeException on compile failure');
        } catch (PregRuntimeException) {
        }

        self::assertSame('555555', ini_get('pcre.recursion_limit'));
    }

    #[Test]
    public function catastrophic_pattern_throws_on_backtrack_limit_under_low_limit(): void
    {
        $executor = new PregExecutor(maxBacktracks: 10);

        try {
            $executor->match('/^(a+)+$/', str_repeat('a', 30) . 'b');
            self::fail('Expected PregRuntimeException on backtrack limit exhaustion');
        } catch (PregRuntimeException $exception) {
            self::assertSame(PREG_BACKTRACK_LIMIT_ERROR, $exception->error);
        }
    }

    #[Test]
    public function catastrophic_pattern_completes_quickly_with_default_limit(): void
    {
        $executor = new PregExecutor();

        $start = hrtime(true);

        try {
            $executor->match('/^(a+)+$/', str_repeat('a', 30) . 'b');
        } catch (PregRuntimeException) {
        }

        $elapsedMs = (hrtime(true) - $start) / 1_000_000;

        self::assertLessThan(200, $elapsedMs);
    }

    #[Test]
    public function legitimate_complex_pattern_matches_under_default_limit(): void
    {
        $executor = new PregExecutor();

        $result = $executor->match('/^[a-z0-9_-]{3,16}$/', 'foo-bar_baz');

        self::assertSame(1, $result);
    }

    #[Test]
    public function pattern_length_just_within_default_limit_does_not_raise_pcre_error(): void
    {
        $executor = new PregExecutor();

        $result = $executor->match('/^' . str_repeat('a', 32) . '$/', str_repeat('a', 32));

        self::assertSame(1, $result);
    }

    #[Test]
    public function sets_recursion_limit_during_match(): void
    {
        $captured = $this->captureRecursionLimitDuringMatch(matchAll: false, maxRecursionLimit: 333);

        self::assertSame('333', $captured);
    }

    #[Test]
    public function sets_recursion_limit_during_match_all(): void
    {
        $captured = $this->captureRecursionLimitDuringMatch(matchAll: true, maxRecursionLimit: 333);

        self::assertSame('333', $captured);
    }

    private function captureRecursionLimitDuringMatch(bool $matchAll, int $maxRecursionLimit): string
    {
        $autoloadPath = dirname(__DIR__, 3) . '/vendor/autoload.php';

        $script = <<<PHP
<?php

namespace Duyler\\OpenApi\\Validator {
    function preg_match(string \$pattern, string \$subject, ?array &\$matches = null, int \$flags = 0, int \$offset = 0): int|false {
        echo "RECURSION=" . ini_get('pcre.recursion_limit') . "\\n";

        return \\preg_match(\$pattern, \$subject, \$matches, \$flags, \$offset);
    }

    function preg_match_all(string \$pattern, string \$subject, ?array &\$matches = null, int \$flags = 0, int \$offset = 0): int|false {
        echo "RECURSION=" . ini_get('pcre.recursion_limit') . "\\n";

        return \\preg_match_all(\$pattern, \$subject, \$matches, \$flags, \$offset);
    }
}

namespace {
    require '{$autoloadPath}';
    \$executor = new \\Duyler\\OpenApi\\Validator\\PregExecutor(maxRecursionLimit: {$maxRecursionLimit});

PHP;

        if ($matchAll) {
            $script .= "    \$executor->matchAll('/^a\$/', 'a');\n";
        } else {
            $script .= "    \$executor->match('/^a\$/', 'a');\n";
        }

        $script .= "}\n";

        $handle = tmpfile();
        self::assertNotFalse($handle, 'Failed to create temporary file for subprocess isolation');
        $path = stream_get_meta_data($handle)['uri'];
        fwrite($handle, $script);

        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open(['php', $path], $descriptor, $pipes);

        self::assertIsResource($process, 'Failed to spawn subprocess for observability test');

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        fclose($handle);

        if (1 !== preg_match('/^RECURSION=(.*)$/m', $stdout, $matches)) {
            self::fail(sprintf('Subprocess did not capture recursion limit. stdout was: %s', $stdout));
        }

        return $matches[1];
    }

    private function throwDuringCall(): string
    {
        throw new RuntimeException('probe');
    }
}
