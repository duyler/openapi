<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Util\Encoding;

use Duyler\OpenApi\Validator\Util\Encoding\Base64UrlSyntaxCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Override;

#[CoversClass(Base64UrlSyntaxCheck::class)]
final class Base64UrlSyntaxCheckTest extends TestCase
{
    private Base64UrlSyntaxCheck $check;

    #[Override]
    protected function setUp(): void
    {
        $this->check = new Base64UrlSyntaxCheck();
    }

    public static function validDataProvider(): array
    {
        return [
            'empty string' => [''],
            'url safe alphabet pair' => ['-_'],
            'url safe alphabet pair padded' => ['-_8='],
            'url safe with hyphen' => ['aGVsbG8-d29ybGQ='],
            'url safe with underscore' => ['aGVsbG8_d29ybGQ='],
            'rfc 4648 vector foo' => ['Zm9v'],
            'rfc 4648 vector f padded' => ['Zg=='],
            'rfc 4648 vector fo padded' => ['Zm8='],
            'unpadded one byte remainder two chars' => ['Zg'],
            'unpadded two byte remainder three chars' => ['Zm8'],
            'unpadded full quantum' => ['Zm9vYmFy'],
            'mixed case letters' => ['Zm9V'],
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
            'base64 plus is outside the alphabet' => ['+/'],
            'base64 slash is outside the alphabet' => ['Zm9v+'],
            'base64 slash is outside the alphabet two' => ['aGVsbG8/v29ybGQ='],
            'remainder of one char cannot form a byte' => ['A'],
            'remainder of one char padded' => ['A=='],
            'partial padding' => ['Zg='],
            'excess padding' => ['Zg==='],
            'padding after full quantum' => ['Zm9v='],
            'padding in the middle' => ['Zm=9v'],
            'padding only' => ['=='],
            'space inside' => ['Zm9 v'],
        ];
    }

    #[DataProvider('invalidDataProvider')]
    #[Test]
    public function invalid_data_fails(string $data): void
    {
        self::assertFalse($this->check->isValid($data));
    }
}
