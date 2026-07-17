<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function explode;
use function file_get_contents;
use function implode;
use function is_dir;
use function preg_match;
use function preg_replace;
use function strpos;
use function substr;

/**
 * Guards production-readiness invariants declared in
 * .ai/tasks/production-readiness-fixes/24-patternvalidator-at-operator-typeformatter.md
 *  - P-079: PatternValidator MUST NOT use the @ suppression operator
 *  - P-080: src/ MUST NOT contain any gettype() calls
 */
final class ProductionInvariantsTest extends TestCase
{
    private const string SRC_DIR = __DIR__ . '/../../../src';
    private const string PATTERN_VALIDATOR_PATH = __DIR__ . '/../../../src/Validator/SchemaValidator/PatternValidator.php';

    #[Test]
    public function pattern_validator_does_not_use_suppression_operator(): void
    {
        $source = file_get_contents(self::PATTERN_VALIDATOR_PATH);

        // Strip string literals and comments before scanning — a single quoted '@'
        // char in an exception message is not a suppression operator.
        $codeOnly = self::stripStringLiterals($source);
        $codeOnly = self::stripLineComments($codeOnly);
        $codeOnly = self::stripBlockComments($codeOnly);

        $hasSuppression = 1 === preg_match('/@\s*\w+\s*\(/', $codeOnly);

        self::assertFalse(
            $hasSuppression,
            'PatternValidator must not use the @ suppression operator (rule §3 / §11).',
        );
    }

    #[Test]
    public function pattern_validator_delegates_preg_match_to_preg_executor(): void
    {
        $source = file_get_contents(self::PATTERN_VALIDATOR_PATH);

        // The PregExecutor wrapper (Task 03) lowered pcre.backtrack_limit and
        // wraps the call in set_error_handler / restore_error_handler. Asserting
        // the delegation survives prevents regressions back to a bare call.
        self::assertStringContainsString('pregExecutor()', $source);
        self::assertStringContainsString('->match(', $source);
        self::assertStringNotContainsString('@preg_match', $source);
    }

    #[Test]
    public function no_gettype_calls_remain_in_src(): void
    {
        $files = self::collectPhpFiles(self::SRC_DIR);

        $violations = [];
        foreach ($files as $file) {
            $source = file_get_contents($file);

            $codeOnly = self::stripStringLiterals($source);
            $codeOnly = self::stripLineComments($codeOnly);
            $codeOnly = self::stripBlockComments($codeOnly);

            // TypeFormatter.php documents the legacy gettype() name in its
            // PHPDoc — that is allowed by rule §12 (PHPDoc on public API).
            // Only flag actual function-call invocations in non-doc code.
            if (1 === preg_match('/\bgettype\s*\(/', $codeOnly)) {
                $violations[] = $file;
            }
        }

        self::assertSame(
            [],
            $violations,
            'src/ must not call gettype() — use Duyler\OpenApi\Validator\TypeFormatter::format() instead (rule §7 / P-080).',
        );
    }

    /**
     * @return list<string>
     */
    private static function collectPhpFiles(string $dir): array
    {
        if (false === is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && 'php' === $file->getExtension()) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private static function stripStringLiterals(string $code): string
    {
        // Drop single- and double-quoted strings, treating escapes naively.
        // PCRE only — we intentionally keep this dependency-free.
        $pattern = '/
            (?:
                "(?:\\\\.|[^"\\\\])*"
                |
                \'(?:\\\\.|[^\'\\\\])*\'
            )
        /x';

        return (string) preg_replace($pattern, '""', $code);
    }

    private static function stripLineComments(string $code): string
    {
        $lines = explode("\n", $code);
        $out = [];
        foreach ($lines as $line) {
            // Naive: remove from // to end of line. Heredoc/nowdoc edge cases
            // are not relevant in the files this test scans.
            $idx = strpos($line, '//');
            if (false !== $idx) {
                $line = substr($line, 0, $idx);
            }
            $out[] = $line;
        }

        return implode("\n", $out);
    }

    private static function stripBlockComments(string $code): string
    {
        // Strip /* ... */ comments (covers PHPDoc blocks).
        return (string) preg_replace('/\/\*.*?\*\//s', '', $code);
    }
}
