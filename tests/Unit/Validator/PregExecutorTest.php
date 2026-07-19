<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Validator\Exception\PregRuntimeException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function ini_get;
use function ini_set;
use function str_repeat;

use const PREG_BACKTRACK_LIMIT_ERROR;
use const PREG_INTERNAL_ERROR;

#[CoversClass(PregExecutor::class)]
class PregExecutorTest extends TestCase
{
    private string $originalLimit;

    #[Override]
    protected function setUp(): void
    {
        $this->originalLimit = ini_get('pcre.backtrack_limit') ?: '1000000';
    }

    #[Override]
    protected function tearDown(): void
    {
        ini_set('pcre.backtrack_limit', $this->originalLimit);
    }

    #[Test]
    public function default_max_backtracks_constant_is_underscored_int(): void
    {
        self::assertSame(10_000, PregExecutor::DEFAULT_MAX_BACKTRACKS);
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
    public function restores_previous_backtrack_limit_on_compile_failure(): void
    {
        ini_set('pcre.backtrack_limit', '888888');
        $executor = new PregExecutor(maxBacktracks: 100);

        try {
            $executor->match('/[unclosed/', 'abc');
            self::fail('Expected PregRuntimeException on compile failure');
        } catch (PregRuntimeException) {
        }

        self::assertSame('888888', ini_get('pcre.backtrack_limit'));
    }

    #[Test]
    public function restores_previous_backtrack_limit_on_exception(): void
    {
        ini_set('pcre.backtrack_limit', '777777');
        $executor = new PregExecutor(maxBacktracks: 100);

        try {
            $executor->match('/^trigger$/', $this->throwDuringCall());
        } catch (RuntimeException) {
        }

        self::assertSame('777777', ini_get('pcre.backtrack_limit'));
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

    private function throwDuringCall(): string
    {
        throw new RuntimeException('probe');
    }
}
