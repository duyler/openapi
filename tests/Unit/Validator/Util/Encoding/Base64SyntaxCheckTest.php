<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Util\Encoding;

use Duyler\OpenApi\Test\Support\CountingContentDecoder;
use Duyler\OpenApi\Validator\NativeContentDecoder;
use Duyler\OpenApi\Validator\Util\Encoding\Base64SyntaxCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;

#[CoversClass(Base64SyntaxCheck::class)]
final class Base64SyntaxCheckTest extends TestCase
{
    private Base64SyntaxCheck $check;

    #[Override]
    protected function setUp(): void
    {
        $this->check = new Base64SyntaxCheck(new NativeContentDecoder());
    }

    public static function validDataProvider(): array
    {
        return [
            'empty string' => [''],
            'rfc 4648 vector f padded' => ['Zg=='],
            'rfc 4648 vector fo padded' => ['Zm8='],
            'rfc 4648 vector foo' => ['Zm9v'],
            'rfc 4648 vector foobar full quantum unpadded' => ['Zm9vYmFy'],
            'hello world padded' => ['SGVsbG8gd29ybGQ='],
            'mixed case letters round-trip' => ['Zm9V'],
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
            'missing padding' => ['SGVsbG8'],
            'missing padding on two byte remainder' => ['Zm8'],
            'url safe hyphen outside base64 alphabet' => ['aGVsbG8-d29ybGQ='],
            'url safe underscore outside base64 alphabet' => ['aGVsbG8_d29ybGQ='],
            'letters and spaces' => ['hello world'],
            'exclamation garbage' => ['not-base64!!!'],
            'whitespace inside' => ["SGVs\nbG8="],
            'percent is outside the alphabet' => ['eyJmb28iOi%iYmFyIn0K'],
        ];
    }

    #[DataProvider('invalidDataProvider')]
    #[Test]
    public function invalid_data_fails(string $data): void
    {
        self::assertFalse($this->check->isValid($data));
    }

    #[Test]
    public function decode_seam_is_used_for_every_check(): void
    {
        $decoder = new CountingContentDecoder();
        $check = new Base64SyntaxCheck($decoder);

        self::assertFalse($check->isValid('not-base64!!!'));
        self::assertTrue($check->isValid('Zm9v'));
        self::assertSame(2, $decoder->calls);
    }
}
