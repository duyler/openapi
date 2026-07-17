<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Security;

use Duyler\OpenApi\Validator\Util\LogContextSanitizer;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;
use function strlen;

/**
 * P-037 regression: LogContextSanitizer must truncate long strings and
 * escape control characters before they enter PSR-3 log context, so a
 * plaintext logger (Monolog StreamHandler) cannot be injected with fake
 * log lines via newline injection or buried content.
 *
 * @internal
 */
final class LogInjectionSanitizationTest extends TestCase
{
    #[Override]
    protected function setUp(): void {}

    #[Test]
    public function log_context_truncates_long_strings(): void
    {
        $attack = str_repeat('a', 1024);

        $sanitized = LogContextSanitizer::truncate($attack);

        self::assertLessThan(
            400,
            strlen($sanitized),
            'Sanitized output must stay well under the original 1KB size (P-037)',
        );
        self::assertStringContainsString('truncated', $sanitized);
        self::assertStringContainsString('1024 bytes total', $sanitized);
    }

    #[Test]
    public function log_context_escapes_control_chars(): void
    {
        $attack = "fake-log-line\nINFO user logged in";

        $sanitized = LogContextSanitizer::truncate($attack);

        self::assertStringNotContainsString(
            "\n",
            $sanitized,
            'Newline must be escaped so the logger cannot be injected with fake lines (P-037)',
        );
        self::assertStringContainsString('\x0a', $sanitized);
    }

    #[Test]
    public function log_context_escapes_a_full_set_of_control_bytes(): void
    {
        $attack = "tab\there\x00null\x07bell\x1bescape\x7fdelete";

        $sanitized = LogContextSanitizer::truncate($attack);

        self::assertStringNotContainsString("\t", $sanitized);
        self::assertStringNotContainsString("\x00", $sanitized);
        self::assertStringNotContainsString("\x07", $sanitized);
        self::assertStringNotContainsString("\x1b", $sanitized);
        self::assertStringNotContainsString("\x7f", $sanitized);

        self::assertStringContainsString('\x09', $sanitized);
        self::assertStringContainsString('\x00', $sanitized);
        self::assertStringContainsString('\x07', $sanitized);
        self::assertStringContainsString('\x1b', $sanitized);
        self::assertStringContainsString('\x7f', $sanitized);
    }

    #[Test]
    public function log_context_preserves_short_clean_strings(): void
    {
        $sanitized = LogContextSanitizer::truncate('clean line');

        self::assertSame('clean line', $sanitized);
    }

    #[Test]
    public function log_context_preserves_multibyte_utf8_at_boundary(): void
    {
        $attack = str_repeat('я', 300);

        $sanitized = LogContextSanitizer::truncate($attack);

        self::assertStringContainsString('truncated', $sanitized);
        self::assertLessThan(
            600,
            strlen($sanitized),
            'Multibyte truncation must not produce broken UTF-8 sequences',
        );
    }
}
