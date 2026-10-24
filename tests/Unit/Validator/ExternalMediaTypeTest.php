<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Validator\ExternalMediaType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExternalMediaType::class)]
final class ExternalMediaTypeTest extends TestCase
{
    #[Test]
    public function defined_when_content_type_from_encoding_is_present(): void
    {
        $this->assertTrue(ExternalMediaType::isDefined('application/json', null));
    }

    #[Test]
    public function defined_when_content_map_key_is_present(): void
    {
        $this->assertTrue(ExternalMediaType::isDefined(null, 'application/json'));
    }

    #[Test]
    public function defined_when_both_sources_are_present(): void
    {
        $this->assertTrue(ExternalMediaType::isDefined('application/json', 'multipart/form-data'));
    }

    #[Test]
    public function undefined_when_both_sources_are_absent(): void
    {
        $this->assertFalse(ExternalMediaType::isDefined(null, null));
    }

    #[Test]
    public function defined_when_content_type_list_has_element(): void
    {
        $this->assertTrue(ExternalMediaType::isDefined(['text/css', 'image/*'], null));
    }

    #[Test]
    public function undefined_when_content_type_list_is_empty(): void
    {
        $this->assertFalse(ExternalMediaType::isDefined([], null));
    }

    #[Test]
    public function defined_when_content_type_list_is_empty_but_map_key_is_present(): void
    {
        $this->assertTrue(ExternalMediaType::isDefined([], 'application/json'));
    }

    #[Test]
    public function primary_returns_string_content_type_as_is(): void
    {
        $this->assertSame('application/jsonl', ExternalMediaType::primary('application/jsonl', 'text/html'));
    }

    #[Test]
    public function primary_returns_first_element_of_content_type_list(): void
    {
        $this->assertSame('text/css', ExternalMediaType::primary(['text/css', 'image/*'], 'text/html'));
    }

    #[Test]
    public function primary_returns_fallback_for_null_content_type(): void
    {
        $this->assertSame('text/html', ExternalMediaType::primary(null, 'text/html'));
    }

    #[Test]
    public function primary_returns_fallback_for_empty_content_type_list(): void
    {
        $this->assertSame('text/html', ExternalMediaType::primary([], 'text/html'));
    }
}
