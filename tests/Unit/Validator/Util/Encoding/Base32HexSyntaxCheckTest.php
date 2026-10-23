<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Util\Encoding;

use Duyler\OpenApi\Validator\Util\Encoding\Base32HexSyntaxCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;

#[CoversClass(Base32HexSyntaxCheck::class)]
final class Base32HexSyntaxCheckTest extends TestCase
{
    private Base32HexSyntaxCheck $check;

    #[Override]
    protected function setUp(): void
    {
        $this->check = new Base32HexSyntaxCheck();
    }

    public static function validDataProvider(): array
    {
        return [
            'empty string' => [''],
            'extended hex alphabet' => ['0123456789ABCDEFGHIJKLMNOPQRSTUV'],
            'rfc 4648 vector f padded' => ['CO======'],
            'rfc 4648 vector foo padded' => ['CPNMU==='],
            'rfc 4648 vector foob padded' => ['CPNMUOG='],
            'rfc 4648 vector fooba unpadded' => ['CPNMUOJ1'],
            'rfc 4648 vector foobar padded' => ['CPNMUOJ1E8======'],
            'full quantum unpadded' => ['CPNMUOJ1E8'],
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
            'letters beyond v are outside the alphabet' => ['CPNMUOJ1W'],
            'base32 letter x outside alphabet' => ['MZXW6YTB'],
            'lowercase is outside the alphabet' => ['cpmuoj1'],
            'remainder of one char cannot form a byte' => ['C'],
            'remainder of three chars' => ['CPN'],
            'remainder of six chars' => ['CPNMUO'],
            'missing padding' => ['CPNMUOJ1E8='],
            'partial padding' => ['CPNMUOJ1E8=='],
            'excess padding after full quantum' => ['CO======='],
            'padding in the middle' => ['CPN=MUOJ1'],
            'padding only' => ['======'],
        ];
    }

    #[DataProvider('invalidDataProvider')]
    #[Test]
    public function invalid_data_fails(string $data): void
    {
        self::assertFalse($this->check->isValid($data));
    }
}
