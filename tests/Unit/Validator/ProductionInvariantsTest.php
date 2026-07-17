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
        return (string) preg_replace('/\/\*.*?\*\//s', '', $code);
    }
}
