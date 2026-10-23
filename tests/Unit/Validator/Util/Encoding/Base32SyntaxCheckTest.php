<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Util\Encoding;

use Duyler\OpenApi\Validator\Util\Encoding\Base32SyntaxCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;

#[CoversClass(Base32SyntaxCheck::class)]
final class Base32SyntaxCheckTest extends TestCase
{
    private Base32SyntaxCheck $check;

    #[Override]
    protected function setUp(): void
    {
        $this->check = new Base32SyntaxCheck();
    }

    public static function validDataProvider(): array
    {
        return [
            'empty string' => [''],
            'rfc 4648 vector f padded' => ['MY======'],
            'rfc 4648 vector foo padded' => ['MZXW6==='],
            'rfc 4648 vector foob padded' => ['MZXW6YQ='],
            'rfc 4648 vector fooba unpadded' => ['MZXW6YTB'],
            'rfc 4648 vector foobar padded' => ['MZXW6YTBOI======'],
            'full quantum unpadded' => ['MZXW6YTBOI'],
            'two quanta unpadded' => ['MZXW6YTBOIMZXW6YTBOI'],
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
            'lowercase is outside the alphabet' => ['mzxw6ytb'],
            'base32hex digit outside alphabet' => ['CPNMUOJ1'],
            'remainder of one char cannot form a byte' => ['M'],
            'remainder of three chars' => ['MZX'],
            'remainder of six chars' => ['MZXW6Y'],
            'missing padding' => ['MZXW6YTBOI='],
            'partial padding' => ['MZXW6YTBOI=='],
            'excess padding after full quantum' => ['MZXW6YTB==='],
            'padding in the middle' => ['MZX=W6YTB'],
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
