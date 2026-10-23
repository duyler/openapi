<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Util\Encoding;

use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Util\Encoding\Base16SyntaxCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Validator\Util\Encoding\AbstractBaseEncodingSyntaxCheck;
use Override;

#[CoversClass(Base16SyntaxCheck::class)]
#[CoversClass(AbstractBaseEncodingSyntaxCheck::class)]
final class Base16SyntaxCheckTest extends TestCase
{
    private Base16SyntaxCheck $check;

    #[Override]
    protected function setUp(): void
    {
        $this->check = new Base16SyntaxCheck();
    }

    public static function validDataProvider(): array
    {
        return [
            'empty string' => [''],
            'rfc 4648 vector fo' => ['666F'],
            'rfc 4648 vector foobar' => ['666F6F626172'],
            'lowercase accepted since base16 is case-insensitive' => ['666f6f626172'],
            'mixed case' => ['666F6f626172'],
            'digits only' => ['00'],
            'all letters' => ['0123456789ABCDEF'],
            'long payload' => ['DEADBEEF00'],
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
            'odd length' => ['6'],
            'odd length long' => ['666'],
            'non hex letters' => ['GG'],
            'space inside' => ['66 6F'],
            'prefix marker' => ['0x66'],
            'padding is not part of base16' => ['66=='],
            'raw newline' => ["66\n6F"],
        ];
    }

    #[DataProvider('invalidDataProvider')]
    #[Test]
    public function invalid_data_fails(string $data): void
    {
        self::assertFalse($this->check->isValid($data));
    }

    #[Test]
    public function preg_backtrack_exhaustion_fails_closed(): void
    {
        $check = new Base16SyntaxCheck(new PregExecutor(maxBacktracks: 1, maxRecursionLimit: 1));

        self::assertFalse($check->isValid(str_repeat('666F', 500) . '!'));
    }
}
