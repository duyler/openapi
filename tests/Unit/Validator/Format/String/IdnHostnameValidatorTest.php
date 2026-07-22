<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\IdnHostnameValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function extension_loaded;

#[CoversClass(IdnHostnameValidator::class)]
final class IdnHostnameValidatorTest extends TestCase
{
    private IdnHostnameValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new IdnHostnameValidator();
    }

    #[Test]
    public function idn_hostname_validator_accepts_unicode_hostname(): void
    {
        if (!extension_loaded('intl')) {
            self::markTestSkipped('ext-intl required for IDNA hostname validation');
        }

        $this->expectNotToPerformAssertions();
        $this->validator->validate('例え.テスト');
        $this->validator->validate('münchen.de');
        $this->validator->validate('北京.cn');
    }

    #[Test]
    public function idn_hostname_validator_accepts_ascii_hostname(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('example.com');
        $this->validator->validate('sub.example.co.uk');
        $this->validator->validate('xn--mnchen-3ya.de');
    }

    #[Test]
    public function idn_hostname_validator_rejects_invalid_format(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('not a hostname');
    }

    #[Test]
    public function idn_hostname_validator_rejects_empty_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('');
    }

    #[Test]
    public function idn_hostname_validator_format_name_is_correct(): void
    {
        $exception = null;

        try {
            $this->validator->validate('-invalid-.hostname');
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception);
        $this->assertSame('idn-hostname', $exception->format);
    }

    #[Test]
    public function idn_hostname_validator_rejects_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(['array']);
    }
}
