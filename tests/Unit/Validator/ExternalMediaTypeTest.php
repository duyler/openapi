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
}
