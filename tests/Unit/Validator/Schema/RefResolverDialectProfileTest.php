<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Validator\Dto\DialectProfile;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RefResolverDialectProfileTest extends TestCase
{
    #[Test]
    public function resolver_defaults_to_openapi_31_profile(): void
    {
        $resolver = new RefResolver();

        self::assertEquals(
            DialectProfile::forOpenApi31(),
            $resolver->dialectProfile(),
        );
    }

    #[Test]
    public function resolver_carries_injected_openapi_30_profile(): void
    {
        $profile = DialectProfile::forOpenApi30();

        $resolver = new RefResolver(dialectProfile: $profile);

        self::assertSame($profile, $resolver->dialectProfile());
    }
}
