<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Exception;

use Duyler\OpenApi\Validator\Exception\UnsupportedMediaTypeException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** @internal */
final class UnsupportedMediaTypeExceptionTest extends TestCase
{
    private const string ATTACKER_MEDIA_TYPE = 'application/evil-attacker-mime';

    /** @var list<string> */
    private const array SUPPORTED_TYPES = ['application/json', 'application/xml'];

    #[Test]
    public function get_message_omits_attacker_media_type(): void
    {
        $exception = new UnsupportedMediaTypeException(self::ATTACKER_MEDIA_TYPE, self::SUPPORTED_TYPES);

        self::assertStringNotContainsString(self::ATTACKER_MEDIA_TYPE, $exception->getMessage());
        self::assertStringNotContainsString('evil-attacker-mime', $exception->getMessage());
    }

    #[Test]
    public function get_message_keeps_spec_derived_supported_types(): void
    {
        $exception = new UnsupportedMediaTypeException(self::ATTACKER_MEDIA_TYPE, self::SUPPORTED_TYPES);

        self::assertStringContainsString('Unsupported media type', $exception->getMessage());
        self::assertStringContainsString('application/json', $exception->getMessage());
        self::assertStringContainsString('application/xml', $exception->getMessage());
    }

    #[Test]
    public function to_string_is_sanitized_via_trait(): void
    {
        $exception = new UnsupportedMediaTypeException(self::ATTACKER_MEDIA_TYPE, self::SUPPORTED_TYPES);

        self::assertSame($exception->getMessage(), (string) $exception);
        self::assertStringNotContainsString(self::ATTACKER_MEDIA_TYPE, (string) $exception);
        self::assertStringContainsString('application/json', (string) $exception);
    }

    #[Test]
    public function media_type_property_preserved_for_trusted_access(): void
    {
        $exception = new UnsupportedMediaTypeException(self::ATTACKER_MEDIA_TYPE, self::SUPPORTED_TYPES);

        self::assertSame(self::ATTACKER_MEDIA_TYPE, $exception->mediaType);
    }

    #[Test]
    public function supported_types_property_preserved_for_trusted_access(): void
    {
        $exception = new UnsupportedMediaTypeException(self::ATTACKER_MEDIA_TYPE, self::SUPPORTED_TYPES);

        self::assertSame(self::SUPPORTED_TYPES, $exception->supportedTypes);
    }

    #[Test]
    public function message_uses_supported_types_when_attacker_payload_present(): void
    {
        $exception = new UnsupportedMediaTypeException(
            'application/<script>alert(1)</script>',
            ['application/json'],
        );

        self::assertStringNotContainsString('<script>', $exception->getMessage());
        self::assertStringNotContainsString('alert', $exception->getMessage());
        self::assertStringContainsString('application/json', $exception->getMessage());
    }

    #[Test]
    public function propagates_code_and_previous(): void
    {
        $previous = new RuntimeException('downstream');
        $exception = new UnsupportedMediaTypeException(
            'application/evil',
            ['application/json'],
            415,
            $previous,
        );

        self::assertSame(415, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function extends_runtime_exception(): void
    {
        $exception = new UnsupportedMediaTypeException('application/evil', ['application/json']);

        self::assertInstanceOf(RuntimeException::class, $exception);
    }
}
