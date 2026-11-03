<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request\Internal;

use Duyler\OpenApi\Validator\Exception\InvalidContentRangeException;
use Duyler\OpenApi\Validator\Request\Internal\ByteRange;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ByteRangeTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: int|null, 2: int|null, 3: int|null}>
     */
    public static function provide_valid_headers(): array
    {
        return [
            'sized complete range' => ['bytes 0-100/1000', 0, 100, 1000],
            'single byte range' => ['bytes 0-0/1', 0, 0, 1],
            'unsized complete range' => ['bytes 512-1023/*', 512, 1023, null],
            'multi digit range' => ['bytes 1000-1999/5000', 1000, 1999, 5000],
        ];
    }

    #[Test]
    #[DataProvider('provide_valid_headers')]
    public function valid_header_resolves_named_components(
        string $header,
        ?int $first,
        ?int $last,
        ?int $complete,
    ): void {
        $range = ByteRange::fromHeader($header);

        self::assertSame($first, $range->first);
        self::assertSame($last, $range->last);
        self::assertSame($complete, $range->complete);
    }

    #[Test]
    public function unsized_range_carries_only_the_complete_length(): void
    {
        $range = ByteRange::fromHeader('bytes */1000');

        self::assertNull($range->first);
        self::assertNull($range->last);
        self::assertSame(1000, $range->complete);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function provide_invalid_headers(): array
    {
        return [
            'missing complete part' => ['bytes 0-100'],
            'missing bytes prefix' => ['*/1000'],
            'garbage numbers' => ['bytes x-y/z'],
            'trailing garbage' => ['bytes 0-100/1000junk'],
            'trailing newline' => ["bytes 0-100/1000\n"],
            'leading whitespace' => [' bytes 0-100/1000'],
            'inverted range' => ['bytes 200-100/1000'],
            'empty header' => [''],
            'missing space' => ['bytes0-100/1000'],
            'uppercase prefix rejected' => ['BYTES 0-100/1000'],
            'negative first' => ['bytes -1-5/10'],
            'double dash' => ['bytes 0--5/10'],
            'wildcard first' => ['bytes *-5/10'],
            'missing dash' => ['bytes 0/10'],
            'unterminated complete' => ['bytes 0-100/'],
            'extra range' => ['bytes 0-100/1000 bytes 0-1/2'],
        ];
    }

    #[Test]
    #[DataProvider('provide_invalid_headers')]
    public function invalid_header_raises_the_domain_validation_error(string $header): void
    {
        $this->expectException(InvalidContentRangeException::class);

        ByteRange::fromHeader($header);
    }

    #[Test]
    public function syntax_error_carries_format_keyword(): void
    {
        try {
            ByteRange::fromHeader('bytes 0-100');

            self::fail('Expected InvalidContentRangeException');
        } catch (InvalidContentRangeException $exception) {
            self::assertSame('format', $exception->keyword());
            self::assertSame('/headers', $exception->schemaPath());
            self::assertSame('Content-Range', $exception->params()['header']);
        }
    }

    #[Test]
    public function inverted_range_reports_both_positions(): void
    {
        try {
            ByteRange::fromHeader('bytes 200-100/1000');

            self::fail('Expected InvalidContentRangeException');
        } catch (InvalidContentRangeException $exception) {
            self::assertSame('format', $exception->keyword());
            self::assertSame(200, $exception->params()['first']);
            self::assertSame(100, $exception->params()['last']);
        }
    }
}
