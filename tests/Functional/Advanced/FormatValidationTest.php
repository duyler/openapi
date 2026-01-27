<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Advanced;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Override;
use PHPUnit\Framework\Attributes\Test;

final class FormatValidationTest extends AdvancedFunctionalTestCase
{
    private string $specFile = '';

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->specFile = __DIR__ . '/../../fixtures/advanced-specs/format-validation.yaml';
    }

    #[Test]
    public function email_format_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->psrFactory->createServerRequest('GET', '/formats/query?email=test@example.com');

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function email_format_invalid_throws_error(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->psrFactory->createServerRequest('GET', '/formats/query?email=not-an-email');

        $this->expectException(InvalidFormatException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function uuid_v4_format_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->psrFactory->createServerRequest('GET', '/formats/query?uuid=550e8400-e29b-41d4-a716-446655440000');

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function uuid_format_invalid_throws_error(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->psrFactory->createServerRequest('GET', '/formats/query?uuid=not-a-uuid');

        $this->expectException(InvalidFormatException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function uri_format_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->psrFactory->createServerRequest('GET', '/formats/query?uri=https://example.com/path?query=value');

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function uri_format_invalid_throws_error(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->psrFactory->createServerRequest('GET', '/formats/query?uri=not-a-uri');

        $this->expectException(InvalidFormatException::class);
        $validator->validateRequest($request);
    }

    #[Test]
    public function date_format_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->psrFactory->createServerRequest('GET', '/formats/query?date=2024-01-01');

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function time_format_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->psrFactory->createServerRequest('GET', '/formats/query?time=12:30:45Z');

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function hostname_format_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->psrFactory->createServerRequest('GET', '/formats/query?hostname=example.com');

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function ipv4_format_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->psrFactory->createServerRequest('GET', '/formats/query?ipv4=192.168.1.1');

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function ipv6_format_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->psrFactory->createServerRequest('GET', '/formats/query?ipv6=2001:0db8:85a3:0000:0000:8a2e:0370:7334');

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function datetime_format_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/formats/body', [
            'email' => 'test@example.com',
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'dateTime' => '2024-01-01T00:00:00Z',
            'date' => '2024-01-01',
            'time' => '12:30:45Z',
            'uri' => 'https://example.com',
            'hostname' => 'example.com',
            'ipv4' => '192.168.1.1',
            'ipv6' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
            'byte' => 'SGVsbG8gV29ybGQ=',
            'password' => 'Secret123',
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function int32_format_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/formats/numeric', [
            'int32Value' => 2147483647,
            'int64Value' => 9223372036854775807,
            'floatValue' => 3.14159,
            'doubleValue' => 3.14159,
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function int64_format_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/formats/numeric', [
            'int32Value' => 2147483647,
            'int64Value' => 9223372036854775807,
            'floatValue' => 3.14159,
            'doubleValue' => 3.14159,
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function float_format_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/formats/numeric', [
            'int32Value' => 2147483647,
            'int64Value' => 9223372036854775807,
            'floatValue' => 3.14159,
            'doubleValue' => 3.14159,
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function byte_format_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/formats/body', [
            'email' => 'test@example.com',
            'uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'dateTime' => '2024-01-01T00:00:00Z',
            'date' => '2024-01-01',
            'time' => '12:30:45Z',
            'uri' => 'https://example.com',
            'hostname' => 'example.com',
            'ipv4' => '192.168.1.1',
            'ipv6' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
            'byte' => 'SGVsbG8gV29ybGQ=',
            'password' => 'Secret123',
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function multiple_formats_in_one_schema_valid(): void
    {
        $validator = $this->createValidator($this->specFile);
        $request = $this->createRequest('POST', '/formats/mixed', [
            'user' => [
                'email' => 'test@example.com',
                'website' => 'https://example.com',
            ],
            'items' => [
                [
                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                    'created' => '2024-01-01T00:00:00Z',
                ],
            ],
        ]);

        $validator->validateRequest($request);
        $this->expectNotToPerformAssertions();
    }
}
