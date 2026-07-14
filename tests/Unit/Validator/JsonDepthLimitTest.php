<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Validator\JsonDepthLimit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonDepthLimit::class)]
class JsonDepthLimitTest extends TestCase
{
    #[Test]
    public function untrusted_depth_is_strict_for_user_controlled_request_bodies(): void
    {
        self::assertSame(128, JsonDepthLimit::Untrusted->value);
    }

    #[Test]
    public function trusted_depth_matches_php_default_for_openapi_specs(): void
    {
        self::assertSame(512, JsonDepthLimit::Trusted->value);
    }

    #[Test]
    public function untrusted_depth_is_strictly_lower_than_trusted(): void
    {
        self::assertLessThan(
            JsonDepthLimit::Trusted->value,
            JsonDepthLimit::Untrusted->value,
            'Untrusted depth must stay below Trusted depth to keep the DoS guard meaningful.',
        );
    }
}
