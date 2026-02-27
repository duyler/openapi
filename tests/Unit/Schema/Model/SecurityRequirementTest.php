<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

use Duyler\OpenApi\Schema\Model\SecurityRequirement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecurityRequirement::class)]
final class SecurityRequirementTest extends TestCase
{
    #[Test]
    public function can_create_security_requirement(): void
    {
        $requirement = new SecurityRequirement(
            requirements: ['bearerAuth' => []],
        );

        self::assertSame(['bearerAuth' => []], $requirement->requirements);
        self::assertArrayHasKey('bearerAuth', $requirement->requirements);
    }

    #[Test]
    public function can_create_empty_security_requirement(): void
    {
        $requirement = new SecurityRequirement(
            requirements: [],
        );

        self::assertSame([], $requirement->requirements);
    }

    #[Test]
    public function json_serialize_includes_requirements(): void
    {
        $requirement = new SecurityRequirement(
            requirements: ['bearerAuth' => []],
        );

        $serialized = $requirement->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('bearerAuth', $serialized);
        self::assertIsArray($serialized['bearerAuth']);
    }
}
