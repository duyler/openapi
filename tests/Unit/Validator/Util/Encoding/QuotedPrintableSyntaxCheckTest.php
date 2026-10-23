<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Util\Encoding;

use Duyler\OpenApi\Validator\Util\Encoding\QuotedPrintableSyntaxCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;

#[CoversClass(QuotedPrintableSyntaxCheck::class)]
final class QuotedPrintableSyntaxCheckTest extends TestCase
{
    private QuotedPrintableSyntaxCheck $check;

    #[Override]
    protected function setUp(): void
    {
        $this->check = new QuotedPrintableSyntaxCheck();
    }

    public static function validDataProvider(): array
    {
        return [
            'empty string' => [''],
            'plain ascii text' => ['hello world'],
            'quoted uppercase hex pair' => ['=E1'],
            'quoted hex digits' => ['=3D'],
            'text around quoted pair' => ['a=E1b'],
            'quoted crlf octets' => ['=0D=0A'],
            'soft line break' => ["first line=\r\nsecond line"],
            'soft break after trailing whitespace' => ["line with spaces  =\r\nnext"],
            'trailing space is tolerated by decoders' => ['trailing space '],
            'trailing tab is tolerated by decoders' => ["trailing tab\t"],
            'hard line break' => ["first line\r\nsecond line"],
            'tab literal' => ["column\tof\ttext"],
            'multiple soft breaks' => ["a=\r\nb=\r\nc"],
            'text after quoted pair' => ['=E1 is the only valid escape shape here'],
        ];
    }

    #[DataProvider('validDataProvider')]
    #[Test]
    public function valid_data_passes(string $data): void
    {
        self::assertTrue($this->check->isValid($data));
    }

    public static function invalidDataProvider(): array
    {
        return [
            'non hex escape' => ['=X1'],
            'lowercase hex is forbidden' => ['=e1'],
            'single hex digit escape' => ['=1'],
            'truncated escape at end' => ['value='],
            'truncated escape before text' => ['value=next'],
            'dangling equals after soft break cr' => ["line=\r"],
            'bare lf is not a line break' => ["first\nsecond"],
            'bare cr is not a line break' => ["first\rsecond"],
            'raw high byte must be quoted' => ["caf\xC3"],
            'nul byte must be quoted' => ["a\x00b"],
            'control byte must be quoted' => ["a\x01b"],
            'equals with bare lf soft break' => ["line=\nnext"],
        ];
    }

    #[DataProvider('invalidDataProvider')]
    #[Test]
    public function invalid_data_fails(string $data): void
    {
        self::assertFalse($this->check->isValid($data));
    }
}
